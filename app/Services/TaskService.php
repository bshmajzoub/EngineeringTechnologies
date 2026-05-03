<?php

namespace App\Services;

use App\Enums\AssignmentStatus;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Exceptions\AssignmentConflictException;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\User;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class TaskService
{
    public function __construct(private readonly FcmService $fcmService) {}

    /**
     * @return Collection<int, Task>
     */
    public function listFor(User $user): Collection
    {
        $query = Task::query()
            ->with(['assignments.user', 'creator'])
            ->latest('id');

        if ($user->role === UserRole::Employee) {
            $query->whereHas('assignments', function ($query) use ($user): void {
                $query->where('user_id', $user->id);
            });
        }

        return $query->get();
    }

    /**
     * @param  array{
     *     title: string,
     *     description?: string|null,
     *     task_date: string,
     *     start_at: string,
     *     end_at?: string|null,
     *     employee_ids: list<int|string>,
     *     assignment_notes?: array<int|string, string|null>
     * }  $data
     *
     * @throws AssignmentConflictException
     */
    public function create(User $admin, array $data): Task
    {
        return DB::transaction(function () use ($admin, $data): Task {
            $employeeIds = collect($data['employee_ids'])
                ->map(fn (int|string $employeeId): int => (int) $employeeId)
                ->unique()
                ->values();

            $this->ensureEmployeesAreAvailable($employeeIds->all());

            $task = Task::create([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'task_date' => $data['task_date'],
                'start_at' => $data['start_at'],
                'end_at' => $data['end_at'] ?? null,
                'status' => TaskStatus::Pending,
                'created_by' => $admin->id,
            ]);

            foreach ($employeeIds as $employeeId) {
                $task->assignments()->create([
                    'user_id' => $employeeId,
                    'status' => AssignmentStatus::Pending,
                    'notes' => $data['assignment_notes'][$employeeId] ?? null,
                ]);
            }

            $task->load(['assignments.user', 'creator']);

            foreach ($task->assignments as $assignment) {
                $this->fcmService->sendTaskAssignedNotification($assignment);
            }

            return $task;
        }, attempts: 3);
    }

    /**
     * @param  array{
     *     title?: string,
     *     description?: string|null,
     *     task_date?: string,
     *     start_at?: string,
     *     end_at?: string|null,
     *     assignment_notes?: array<int|string, string|null>
     * }  $data
     */
    public function update(Task $task, array $data): Task
    {
        return DB::transaction(function () use ($task, $data): Task {
            $task->refresh();

            $this->ensureTaskCanBeChanged($task);

            $assignmentNotes = $data['assignment_notes'] ?? null;
            unset($data['assignment_notes']);

            if ($data !== []) {
                $task->update($data);
            }

            if (is_array($assignmentNotes)) {
                foreach ($assignmentNotes as $employeeId => $notes) {
                    $task->assignments()
                        ->where('user_id', (int) $employeeId)
                        ->update(['notes' => $notes]);
                }
            }

            $task->refresh()->load(['assignments.user', 'creator']);

            foreach ($task->assignments as $assignment) {
                $this->fcmService->sendTaskUpdatedNotification($assignment);
            }

            return $task;
        }, attempts: 3);
    }

    public function cancel(Task $task, ?string $cancelReason = null): Task
    {
        return DB::transaction(function () use ($task, $cancelReason): Task {
            $task->refresh();

            $this->ensureTaskCanBeChanged($task);

            $now = now();

            $task->update([
                'status' => TaskStatus::Cancelled,
                'cancelled_at' => $now,
                'cancel_reason' => $cancelReason,
            ]);

            $task->assignments()
                ->whereIn('status', [
                    AssignmentStatus::Pending->value,
                    AssignmentStatus::Active->value,
                ])
                ->update([
                    'status' => AssignmentStatus::Cancelled,
                    'cancelled_at' => $now,
                ]);

            $task->refresh()->load(['assignments.user', 'creator']);

            foreach ($task->assignments as $assignment) {
                $this->fcmService->sendTaskCancelledNotification($assignment, $cancelReason);
            }

            return $task;
        }, attempts: 3);
    }

    public function activate(Task $task): Task
    {
        return DB::transaction(function () use ($task): Task {
            $task->refresh();

            if ($task->status !== TaskStatus::Pending) {
                throw new DomainException('Only pending tasks can be activated.');
            }

            $now = now();

            $task->update([
                'status' => TaskStatus::Active,
            ]);

            $task->assignments()
                ->where('status', AssignmentStatus::Pending->value)
                ->update([
                    'status' => AssignmentStatus::Active,
                    'next_reply_due_at' => $now->copy()->addHour(),
                ]);

            $task->refresh()->load(['assignments.user', 'creator']);

            foreach ($task->assignments as $assignment) {
                $this->fcmService->sendTaskActivatedNotification($assignment);
            }

            return $task;
        }, attempts: 3);
    }

    public function myCurrentAssignment(User $employee): ?TaskAssignment
    {
        return TaskAssignment::query()
            ->with(['task', 'user'])
            ->where('user_id', $employee->id)
            ->whereIn('status', [
                AssignmentStatus::Pending->value,
                AssignmentStatus::Active->value,
            ])
            ->latest('id')
            ->first();
    }

    public function completeAssignment(TaskAssignment $assignment): TaskAssignment
    {
        return DB::transaction(function () use ($assignment): TaskAssignment {
            $assignment->refresh();

            if ($assignment->status !== AssignmentStatus::Active) {
                throw new DomainException('Only active assignments can be completed.');
            }

            $assignment->update([
                'status' => AssignmentStatus::Completed,
                'completed_at' => now(),
            ]);

            return $assignment->refresh()->load(['task', 'user']);
        }, attempts: 3);
    }

    /**
     * @param  list<int>  $employeeIds
     *
     * @throws AssignmentConflictException
     */
    private function ensureEmployeesAreAvailable(array $employeeIds): void
    {
        User::query()
            ->whereIn('id', $employeeIds)
            ->lockForUpdate()
            ->get();

        $busyAssignments = TaskAssignment::query()
            ->with(['task', 'user'])
            ->whereIn('user_id', $employeeIds)
            ->whereIn('status', [
                AssignmentStatus::Pending->value,
                AssignmentStatus::Active->value,
            ])
            ->lockForUpdate()
            ->get();

        if ($busyAssignments->isEmpty()) {
            return;
        }

        $messages = $busyAssignments
            ->map(function (TaskAssignment $assignment): string {
                return sprintf(
                    'Employee %s already has a %s assignment for task #%d.',
                    $assignment->user->name,
                    $assignment->status->value,
                    $assignment->task_id,
                );
            })
            ->values()
            ->all();

        throw new AssignmentConflictException(errors: [
            'employee_ids' => $messages,
        ]);
    }

    private function ensureTaskCanBeChanged(Task $task): void
    {
        if (in_array($task->status, [TaskStatus::Completed, TaskStatus::Cancelled], true)) {
            throw new DomainException('Completed or cancelled tasks cannot be changed.');
        }
    }
}

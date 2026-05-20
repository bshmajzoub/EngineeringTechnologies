<?php

namespace App\Services;

use App\Enums\AssignmentStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Exceptions\AssignmentConflictException;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\User;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
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
            ->with(['assignments.user', 'creator', 'assignedBy'])
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
     *     q?: string|null,
     *     status?: string|null,
     *     priority?: string|null,
     *     employee_id?: int|string|null,
     *     task_date?: string|null,
     *     date_from?: string|null,
     *     date_to?: string|null
     * }  $filters
     * @return LengthAwarePaginator<int, Task>
     */
    public function listAdmin(array $filters, int $perPage): LengthAwarePaginator
    {
        return Task::query()
            ->with(['assignments.user', 'creator', 'assignedBy'])
            ->when($filters['q'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('assignments.user', function ($query) use ($search): void {
                            $query->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            })
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['priority'] ?? null, fn ($query, string $priority) => $query->where('priority', $priority))
            ->when($filters['employee_id'] ?? null, function ($query, int|string $employeeId): void {
                $query->whereHas('assignments', fn ($query) => $query->where('user_id', (int) $employeeId));
            })
            ->when($filters['task_date'] ?? null, fn ($query, string $taskDate) => $query->whereDate('task_date', $taskDate))
            ->when($filters['date_from'] ?? null, fn ($query, string $dateFrom) => $query->whereDate('task_date', '>=', $dateFrom))
            ->when($filters['date_to'] ?? null, fn ($query, string $dateTo) => $query->whereDate('task_date', '<=', $dateTo))
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array{
     *     title: string,
     *     description?: string|null,
     *     task_date: string,
     *     start_at: string,
     *     end_at?: string|null,
     *     priority?: string|null,
     *     reminder_interval_minutes?: int|null,
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
                'priority' => $data['priority'] ?? TaskPriority::MEDIUM->value,
                'reminder_interval_minutes' => $data['reminder_interval_minutes'] ?? null,
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

            $task->load(['assignments.user', 'creator', 'assignedBy']);

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
     *     priority?: string|null,
     *     reminder_interval_minutes?: int|null,
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

            $task->refresh()->load(['assignments.user', 'creator', 'assignedBy']);

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

            $task->refresh()->load(['assignments.user', 'creator', 'assignedBy']);

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
                ->whereNotNull('accepted_at')
                ->update([
                    'status' => AssignmentStatus::Active,
                    'next_reply_due_at' => $now->copy()->addHour(),
                ]);

            $task->refresh()->load(['assignments.user', 'creator', 'assignedBy']);

            foreach ($task->assignments->where('status', AssignmentStatus::Active) as $assignment) {
                $this->fcmService->sendTaskActivatedNotification($assignment);
            }

            return $task;
        }, attempts: 3);
    }

    public function myCurrentAssignment(User $employee): ?TaskAssignment
    {
        return TaskAssignment::query()
            ->with(['task.assignedBy', 'user'])
            ->where('user_id', $employee->id)
            ->whereIn('status', [
                AssignmentStatus::Pending->value,
                AssignmentStatus::Active->value,
            ])
            ->latest('id')
            ->first();
    }

    public function acceptAssignment(User $employee, TaskAssignment $assignment): TaskAssignment
    {
        return DB::transaction(function () use ($employee, $assignment): TaskAssignment {
            $assignment = TaskAssignment::query()
                ->with('task.assignedBy')
                ->whereKey($assignment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($assignment->user_id !== $employee->id) {
                abort(404, 'Assignment not found.');
            }

            if (in_array($assignment->status, [
                AssignmentStatus::Completed,
                AssignmentStatus::Cancelled,
                AssignmentStatus::Rejected,
            ], true)) {
                throw new DomainException('This assignment cannot be accepted.');
            }

            $now = now();
            $updates = [
                'accepted_at' => $assignment->accepted_at ?? $now,
                'rejected_at' => null,
                'rejection_reason' => null,
            ];

            if ($assignment->task->status === TaskStatus::Active) {
                $updates['status'] = AssignmentStatus::Active;
                $updates['next_reply_due_at'] = $assignment->next_reply_due_at ?? $now->copy()->addHour();
            }

            $assignment->update($updates);

            $assignment->refresh()->load(['task.assignedBy', 'user']);
            $this->fcmService->sendTaskAssignmentAcceptedNotification($assignment);

            return $assignment;
        }, attempts: 3);
    }

    public function rejectAssignment(User $employee, TaskAssignment $assignment, string $reason): TaskAssignment
    {
        return DB::transaction(function () use ($employee, $assignment, $reason): TaskAssignment {
            $assignment = TaskAssignment::query()
                ->with('task.assignedBy')
                ->whereKey($assignment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($assignment->user_id !== $employee->id) {
                abort(404, 'Assignment not found.');
            }

            if (in_array($assignment->status, [
                AssignmentStatus::Completed,
                AssignmentStatus::Cancelled,
                AssignmentStatus::Rejected,
            ], true)) {
                throw new DomainException('This assignment cannot be rejected.');
            }

            $assignment->update([
                'status' => AssignmentStatus::Rejected,
                'rejected_at' => now(),
                'rejection_reason' => $reason,
                'next_reply_due_at' => null,
            ]);

            $this->cancelTaskWhenAllAssignmentsRejectedOrCancelled($assignment->task);

            $assignment->refresh()->load(['task.assignedBy', 'user']);
            $this->fcmService->sendTaskAssignmentRejectedNotification($assignment);

            return $assignment;
        }, attempts: 3);
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

            return $assignment->refresh()->load(['task.assignedBy', 'user']);
        }, attempts: 3);
    }

    public function delete(Task $task): int
    {
        return $this->deleteMany([$task->id]);
    }

    /**
     * @param  list<int>  $taskIds
     */
    public function deleteMany(array $taskIds): int
    {
        return DB::transaction(function () use ($taskIds): int {
            $tasks = Task::query()
                ->whereIn('id', $taskIds)
                ->lockForUpdate()
                ->get();

            $now = now();
            $deletedCount = 0;

            foreach ($tasks as $task) {
                if (in_array($task->status, [TaskStatus::Pending, TaskStatus::Active], true)) {
                    $task->update([
                        'status' => TaskStatus::Cancelled,
                        'cancelled_at' => $now,
                        'cancel_reason' => $task->cancel_reason ?? 'Task deleted by admin.',
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
                }

                if ($task->delete()) {
                    $deletedCount++;
                }
            }

            return $deletedCount;
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
            ->where(function ($query): void {
                $query->where('status', AssignmentStatus::Active->value)
                    ->orWhere(function ($query): void {
                        $query->where('status', AssignmentStatus::Pending->value)
                            ->whereNotNull('accepted_at');
                    });
            })
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

    private function cancelTaskWhenAllAssignmentsRejectedOrCancelled(Task $task): void
    {
        $task->refresh();

        if (! in_array($task->status, [TaskStatus::Pending, TaskStatus::Active], true)) {
            return;
        }

        $hasOpenAssignments = $task->assignments()
            ->whereNotIn('status', [
                AssignmentStatus::Rejected->value,
                AssignmentStatus::Cancelled->value,
            ])
            ->exists();

        if ($hasOpenAssignments) {
            return;
        }

        $task->update([
            'status' => TaskStatus::Cancelled,
            'cancelled_at' => now(),
            'cancel_reason' => 'All assignments were rejected or cancelled.',
        ]);
    }
}

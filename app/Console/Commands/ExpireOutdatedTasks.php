<?php

namespace App\Console\Commands;

use App\Enums\AssignmentStatus;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Services\FcmService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireOutdatedTasks extends Command
{
    protected $signature = 'tasks:expire-outdated';

    protected $description = 'Auto-cancel tasks that are past their task_date';

    public function __construct(private readonly FcmService $fcmService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        // Eager-load only the assignments that will actually be cancelled
        // (pending or active) together with user.deviceTokens for FCM.
        $outdatedTasks = Task::query()
            ->whereIn('status', [TaskStatus::Pending, TaskStatus::Active])
            ->where('task_date', '<', now()->toDateString())
            ->with([
                'assignments' => fn ($q) => $q->whereIn('status', [
                    AssignmentStatus::Pending,
                    AssignmentStatus::Active,
                ]),
                'assignments.user.deviceTokens',
            ])
            ->get();

        if ($outdatedTasks->isEmpty()) {
            return self::SUCCESS;
        }

        $expiredCount = 0;

        DB::transaction(function () use ($outdatedTasks, &$expiredCount): void {
            $now = now();
            $cancelReason = 'Auto-expired: task date passed';

            foreach ($outdatedTasks as $task) {
                $task->update([
                    'status' => TaskStatus::Cancelled,
                    'cancelled_at' => $now,
                    'cancel_reason' => $cancelReason,
                ]);

                // Bulk-cancel only pending/active assignments on this task.
                $task->assignments()
                    ->whereIn('status', [
                        AssignmentStatus::Pending->value,
                        AssignmentStatus::Active->value,
                    ])
                    ->update([
                        'status' => AssignmentStatus::Cancelled,
                        'cancelled_at' => $now,
                    ]);

                $expiredCount++;
            }
        });

        // Send FCM notifications OUTSIDE the transaction using the
        // already-eager-loaded (pending/active) assignments.
        foreach ($outdatedTasks as $task) {
            foreach ($task->assignments as $assignment) {
                $this->fcmService->sendTaskCancelledNotification(
                    $assignment,
                    'Auto-expired: task date passed'
                );
            }
        }

        $this->info("Expired {$expiredCount} outdated task(s).");

        return self::SUCCESS;
    }
}

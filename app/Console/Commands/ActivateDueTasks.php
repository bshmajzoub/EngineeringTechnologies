<?php

namespace App\Console\Commands;

use App\Enums\AssignmentStatus;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Services\FcmService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ActivateDueTasks extends Command
{
    protected $signature = 'tasks:activate-due';

    protected $description = 'Activate pending tasks whose scheduled time has arrived';

    public function __construct(private readonly FcmService $fcmService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        // Eager-load assignments (pending only) with user + deviceTokens
        // to avoid N+1 queries inside the loop.
        $tasks = Task::query()
            ->where('status', TaskStatus::Pending)
            ->where('task_date', '<=', now()->toDateString())
            ->where('start_at', '<=', now())
            ->whereHas('assignments', function ($query): void {
                $query->where('status', AssignmentStatus::Pending)
                    ->whereNotNull('accepted_at');
            })
            ->with([
                'assignments' => fn ($q) => $q
                    ->where('status', AssignmentStatus::Pending)
                    ->whereNotNull('accepted_at'),
                'assignments.user.deviceTokens',
            ])
            ->get();

        if ($tasks->isEmpty()) {
            return self::SUCCESS;
        }

        $activatedCount = 0;

        DB::transaction(function () use ($tasks, &$activatedCount): void {
            $now = now();

            foreach ($tasks as $task) {
                $task->update(['status' => TaskStatus::Active]);

                // Bulk-update only the pending assignments on this task.
                $task->assignments()
                    ->where('status', AssignmentStatus::Pending)
                    ->whereNotNull('accepted_at')
                    ->update([
                        'status' => AssignmentStatus::Active,
                        'next_reply_due_at' => $now->copy()->addHour(),
                    ]);

                $activatedCount++;
            }
        });

        // Fire notifications OUTSIDE the transaction — FCM jobs are queued.
        // Re-use the already-eager-loaded assignments; no extra queries needed.
        foreach ($tasks as $task) {
            foreach ($task->assignments as $assignment) {
                // Reflect the status that was just written in the transaction.
                $assignment->status = AssignmentStatus::Active;
                $this->fcmService->sendTaskActivatedNotification($assignment);
            }
        }

        $this->info("Activated {$activatedCount} task(s).");

        return self::SUCCESS;
    }
}

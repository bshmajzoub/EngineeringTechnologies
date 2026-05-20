<?php

namespace App\Console\Commands;

use App\Enums\AssignmentStatus;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Services\FcmService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('tasks:send-reminders')]
#[Description('Sends custom reminders for active tasks based on their reminder_interval_minutes')]
class SendTaskReminders extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(FcmService $fcmService): int
    {
        $now = now();

        $tasks = Task::query()
            ->with('assignments.user')
            ->where('status', TaskStatus::Active)
            ->whereNotNull('reminder_interval_minutes')
            ->get();

        $count = 0;

        foreach ($tasks as $task) {
            $baseTime = $task->last_reminder_sent_at ?? $task->start_at;

            $dueTime = Carbon::parse($baseTime)->addMinutes($task->reminder_interval_minutes);

            if ($now->greaterThanOrEqualTo($dueTime)) {
                $activeAssignments = $task->assignments->where('status', AssignmentStatus::Active);

                foreach ($activeAssignments as $assignment) {
                    $fcmService->sendTaskCustomReminder($assignment);
                }

                $task->update([
                    'last_reminder_sent_at' => $now,
                ]);

                $count++;
            }
        }

        $this->info("Sent reminders for {$count} tasks.");

        return self::SUCCESS;
    }
}

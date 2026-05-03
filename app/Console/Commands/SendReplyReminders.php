<?php

namespace App\Console\Commands;

use App\Enums\AssignmentStatus;
use App\Models\TaskAssignment;
use App\Services\FcmService;
use Illuminate\Console\Command;

class SendReplyReminders extends Command
{
    protected $signature = 'tasks:send-reply-reminders';

    protected $description = 'Send FCM notifications for overdue hourly replies';

    public function __construct(private readonly FcmService $fcmService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $cooldownMinutes = (int) config('tasks.reply_reminder_repeat_minutes', 15);
        $cooldownCutoff = now()->subMinutes($cooldownMinutes);

        $overdueAssignments = TaskAssignment::query()
            ->where('status', AssignmentStatus::Active)
            // Hourly reply window has passed
            ->where('next_reply_due_at', '<=', now())
            // Cooldown filter: never reminded before, OR last reminder was sent
            // longer than $cooldownMinutes ago. Prevents repeated spam.
            ->where(function ($query) use ($cooldownCutoff): void {
                $query->whereNull('last_reminder_sent_at')
                    ->orWhere('last_reminder_sent_at', '<=', $cooldownCutoff);
            })
            // Eager-load relations so FcmService never lazy-loads inside dispatch.
            ->with(['user.deviceTokens', 'task'])
            ->get();

        if ($overdueAssignments->isEmpty()) {
            return self::SUCCESS;
        }

        $remindersSent = 0;

        foreach ($overdueAssignments as $assignment) {
            // Queue the FCM notification (no HTTP call in this process).
            $this->fcmService->sendHourlyReplyReminder($assignment);

            // Record the send time and increment the missed-reply counter.
            $assignment->update([
                'last_reminder_sent_at' => now(),
                'missed_reply_count' => $assignment->missed_reply_count + 1,
            ]);

            $remindersSent++;
        }

        $this->info("Sent {$remindersSent} reply reminder(s).");

        return self::SUCCESS;
    }
}

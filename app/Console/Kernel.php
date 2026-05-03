<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        Commands\ActivateDueTasks::class,
        Commands\SendReplyReminders::class,
        Commands\ExpireOutdatedTasks::class,
        Commands\ExpireLocationRequests::class,
    ];

    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('tasks:activate-due')->everyMinute()->withoutOverlapping();
        $schedule->command('tasks:send-reply-reminders')->everyFiveMinutes()->withoutOverlapping();
        $schedule->command('tasks:expire-outdated')->dailyAt('00:00')->withoutOverlapping();
        $schedule->command('location:expire-requests')->everyMinute()->withoutOverlapping();
    }
}

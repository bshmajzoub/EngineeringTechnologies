<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Reply Reminder Cooldown
    |--------------------------------------------------------------------------
    | Minimum number of minutes between successive reply reminders for the
    | same assignment. Prevents spamming employees every scheduler run.
    | Override via REPLY_REMINDER_REPEAT_MINUTES in .env.
    */
    'reply_reminder_repeat_minutes' => (int) env('REPLY_REMINDER_REPEAT_MINUTES', 15),
];

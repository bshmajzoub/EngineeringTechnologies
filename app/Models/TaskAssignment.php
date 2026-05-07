<?php

namespace App\Models;

use App\Enums\AssignmentStatus;
use Database\Factories\TaskAssignmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaskAssignment extends Model
{
    /** @use HasFactory<TaskAssignmentFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'task_id',
        'user_id',
        'status',
        'notes',
        'accepted_at',
        'rejected_at',
        'rejection_reason',
        'last_reply_at',
        'next_reply_due_at',
        'last_reminder_sent_at',
        'missed_reply_count',
        'completed_at',
        'cancelled_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AssignmentStatus::class,
            'accepted_at' => 'datetime',
            'rejected_at' => 'datetime',
            'last_reply_at' => 'datetime',
            'next_reply_due_at' => 'datetime',
            'last_reminder_sent_at' => 'datetime',
            'missed_reply_count' => 'integer',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Task, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<TaskReply, $this> */
    public function replies(): HasMany
    {
        return $this->hasMany(TaskReply::class);
    }
}

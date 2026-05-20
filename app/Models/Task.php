<?php

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'description',
        'task_date',
        'start_at',
        'end_at',
        'status',
        'priority',
        'created_by',
        'completed_at',
        'cancelled_at',
        'cancel_reason',
        'reminder_interval_minutes',
        'last_reminder_sent_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'task_date' => 'date',
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'status' => TaskStatus::class,
            'priority' => TaskPriority::class,
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'last_reminder_sent_at' => 'datetime',
            'reminder_interval_minutes' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<TaskAssignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(TaskAssignment::class);
    }
}

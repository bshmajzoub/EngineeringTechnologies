<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property int $task_id
 * @property int $user_id
 * @property \App\Enums\AssignmentStatus $status
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $last_reply_at
 * @property \Illuminate\Support\Carbon|null $next_reply_due_at
 * @property \Illuminate\Support\Carbon|null $last_reminder_sent_at
 * @property int $missed_reply_count
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property \Illuminate\Support\Carbon|null $cancelled_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class TaskAssignmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'task_id' => $this->task_id,
            'user_id' => $this->user_id,
            'status' => $this->status->value,
            'notes' => $this->notes,
            'last_reply_at' => $this->last_reply_at?->toIso8601String(),
            'next_reply_due_at' => $this->next_reply_due_at?->toIso8601String(),
            'last_reminder_sent_at' => $this->last_reminder_sent_at?->toIso8601String(),
            'missed_reply_count' => $this->missed_reply_count,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'employee' => new UserResource($this->whenLoaded('user')),
            'task' => new TaskResource($this->whenLoaded('task')),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}

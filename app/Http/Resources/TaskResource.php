<?php

namespace App\Http\Resources;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $title
 * @property string|null $description
 * @property Carbon $task_date
 * @property Carbon $start_at
 * @property Carbon|null $end_at
 * @property TaskStatus $status
 * @property TaskPriority|null $priority
 * @property int|null $reminder_interval_minutes
 * @property Carbon|null $last_reminder_sent_at
 * @property int $created_by
 * @property Carbon|null $completed_at
 * @property Carbon|null $cancelled_at
 * @property string|null $cancel_reason
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class TaskResource extends JsonResource
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
            'title' => $this->title,
            'description' => $this->description,
            'task_date' => $this->task_date->toDateString(),
            'start_at' => $this->start_at->toIso8601String(),
            'end_at' => $this->end_at?->toIso8601String(),
            'status' => $this->status->value,
            'priority' => $this->priority?->value,
            'reminder_interval_minutes' => $this->reminder_interval_minutes,
            'last_reminder_sent_at' => $this->last_reminder_sent_at?->toIso8601String(),
            'created_by' => $this->created_by,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancel_reason' => $this->cancel_reason,
            'creator' => new UserResource($this->whenLoaded('creator')),
            'assigned_by' => new UserResource($this->whenLoaded('assignedBy')),
            'assignments' => TaskAssignmentResource::collection($this->whenLoaded('assignments')),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}

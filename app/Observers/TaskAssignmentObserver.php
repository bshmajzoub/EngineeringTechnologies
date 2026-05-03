<?php

namespace App\Observers;

use App\Enums\AssignmentStatus;
use App\Enums\TaskStatus;
use App\Models\TaskAssignment;

class TaskAssignmentObserver
{
    /**
     * Handle the TaskAssignment "updated" event.
     */
    public function updated(TaskAssignment $taskAssignment): void
    {
        if (! $taskAssignment->wasChanged('status')) {
            return;
        }

        if ($taskAssignment->status !== AssignmentStatus::Completed) {
            return;
        }

        $task = $taskAssignment->task()->first();

        if (! $task || $task->status !== TaskStatus::Active) {
            return;
        }

        $hasIncompleteAssignments = $task->assignments()
            ->whereIn('status', [
                AssignmentStatus::Pending->value,
                AssignmentStatus::Active->value,
            ])
            ->exists();

        if ($hasIncompleteAssignments) {
            return;
        }

        $task->update([
            'status' => TaskStatus::Completed,
            'completed_at' => now(),
        ]);
    }
}

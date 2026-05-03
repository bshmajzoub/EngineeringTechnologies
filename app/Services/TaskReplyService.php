<?php

namespace App\Services;

use App\Enums\AssignmentStatus;
use App\Exceptions\UnauthorizedException;
use App\Models\TaskAssignment;
use App\Models\TaskReply;
use App\Models\User;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class TaskReplyService
{
    /**
     * @return Collection<int, TaskReply>
     */
    public function listForAssignment(TaskAssignment $assignment): Collection
    {
        return $assignment->replies()
            ->with('user')
            ->oldest('created_at')
            ->get();
    }

    /**
     * @param  array{content: string}  $data
     */
    public function create(TaskAssignment $assignment, User $employee, array $data): TaskReply
    {
        return DB::transaction(function () use ($assignment, $employee, $data): TaskReply {
            $assignment = TaskAssignment::query()
                ->whereKey($assignment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($assignment->status !== AssignmentStatus::Active) {
                throw new DomainException('Replies can only be submitted for active assignments.');
            }

            if ($assignment->user_id !== $employee->id) {
                throw new UnauthorizedException('You can only submit replies for your own assignment.');
            }

            $now = now();

            $reply = $assignment->replies()->create([
                'user_id' => $employee->id,
                'content' => $data['content'],
            ]);

            $assignment->update([
                'last_reply_at' => $now,
                'next_reply_due_at' => $now->copy()->addHour(),
            ]);

            return $reply->load('user');
        }, attempts: 3);
    }
}

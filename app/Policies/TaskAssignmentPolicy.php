<?php

namespace App\Policies;

use App\Enums\AssignmentStatus;
use App\Enums\UserRole;
use App\Models\TaskAssignment;
use App\Models\User;

class TaskAssignmentPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, TaskAssignment $taskAssignment): bool
    {
        return $user->role === UserRole::Admin
            || $taskAssignment->user_id === $user->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, TaskAssignment $taskAssignment): bool
    {
        return $user->role === UserRole::Admin;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, TaskAssignment $taskAssignment): bool
    {
        return false;
    }

    public function complete(User $user, TaskAssignment $taskAssignment): bool
    {
        return $user->role === UserRole::Employee
            && $taskAssignment->user_id === $user->id
            && $taskAssignment->status === AssignmentStatus::Active;
    }

    public function viewReplies(User $user, TaskAssignment $taskAssignment): bool
    {
        return $user->role === UserRole::Admin
            || $taskAssignment->user_id === $user->id;
    }

    public function createReply(User $user, TaskAssignment $taskAssignment): bool
    {
        return $user->role === UserRole::Employee
            && $taskAssignment->user_id === $user->id
            && $taskAssignment->status === AssignmentStatus::Active;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, TaskAssignment $taskAssignment): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, TaskAssignment $taskAssignment): bool
    {
        return false;
    }
}

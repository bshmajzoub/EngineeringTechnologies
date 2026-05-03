<?php

namespace Database\Factories;

use App\Models\TaskAssignment;
use App\Models\TaskReply;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskReply>
 */
class TaskReplyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'task_assignment_id' => TaskAssignment::factory(),
            'user_id' => User::factory()->employee(),
            'content' => fake()->paragraph(),
        ];
    }
}

<?php

namespace Database\Factories;

use App\Enums\AssignmentStatus;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskAssignment>
 */
class TaskAssignmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'user_id' => User::factory()->employee(),
            'status' => AssignmentStatus::Pending,
            'notes' => fake()->optional()->sentence(),
            'last_reply_at' => null,
            'next_reply_due_at' => null,
            'last_reminder_sent_at' => null,
            'missed_reply_count' => 0,
            'completed_at' => null,
            'cancelled_at' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AssignmentStatus::Pending,
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AssignmentStatus::Active,
            'next_reply_due_at' => now()->addHour(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AssignmentStatus::Completed,
            'completed_at' => now(),
        ]);
    }
}

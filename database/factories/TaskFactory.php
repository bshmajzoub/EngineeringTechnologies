<?php

namespace Database\Factories;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startAt = fake()->dateTimeBetween('+1 day', '+2 days');

        return [
            'title' => fake()->sentence(3),
            'description' => fake()->optional()->paragraph(),
            'task_date' => $startAt->format('Y-m-d'),
            'start_at' => $startAt,
            'end_at' => fake()->optional()->dateTimeBetween($startAt, '+3 days'),
            'status' => TaskStatus::Pending,
            'created_by' => User::factory()->admin(),
        ];
    }
}

<?php

namespace Tests\Feature;

use App\Enums\AssignmentStatus;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchedulerTest extends TestCase
{
    use RefreshDatabase;

    public function test_activate_due_tasks_command_activates_pending_tasks(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->employee()->create();

        $task = Task::factory()->for($admin, 'creator')->create([
            'status' => TaskStatus::Pending,
            'task_date' => now()->toDateString(),
            'start_at' => now()->subMinute(),
        ]);

        $assignment = TaskAssignment::factory()->accepted()->for($task)->for($employee, 'user')->create();

        $this->artisan('tasks:activate-due')
            ->assertExitCode(0);

        $task->refresh();
        $assignment->refresh();

        $this->assertEquals(TaskStatus::Active, $task->status);
        $this->assertEquals(AssignmentStatus::Active, $assignment->status);
        $this->assertNotNull($assignment->next_reply_due_at);
    }

    public function test_activate_due_tasks_does_not_activate_future_tasks(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->employee()->create();

        $task = Task::factory()->for($admin, 'creator')->create([
            'status' => TaskStatus::Pending,
            'task_date' => now()->addDay()->toDateString(),
        ]);

        TaskAssignment::factory()->pending()->for($task)->for($employee, 'user')->create();

        $this->artisan('tasks:activate-due')
            ->assertExitCode(0);

        $task->refresh();

        $this->assertEquals(TaskStatus::Pending, $task->status);
    }

    public function test_activate_due_tasks_does_not_activate_unaccepted_assignments(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->employee()->create();

        $task = Task::factory()->for($admin, 'creator')->create([
            'status' => TaskStatus::Pending,
            'task_date' => now()->toDateString(),
            'start_at' => now()->subMinute(),
        ]);

        $assignment = TaskAssignment::factory()->pending()->for($task)->for($employee, 'user')->create();

        $this->artisan('tasks:activate-due')
            ->assertExitCode(0);

        $this->assertEquals(TaskStatus::Pending, $task->refresh()->status);
        $this->assertEquals(AssignmentStatus::Pending, $assignment->refresh()->status);
        $this->assertNull($assignment->next_reply_due_at);
    }

    public function test_expire_outdated_tasks_command_cancels_old_tasks(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->employee()->create();

        $task = Task::factory()->for($admin, 'creator')->create([
            'status' => TaskStatus::Pending,
            'task_date' => now()->subDay()->toDateString(),
        ]);

        $assignment = TaskAssignment::factory()->pending()->for($task)->for($employee, 'user')->create();

        $this->artisan('tasks:expire-outdated')
            ->assertExitCode(0);

        $task->refresh();
        $assignment->refresh();

        $this->assertEquals(TaskStatus::Cancelled, $task->status);
        $this->assertEquals('Auto-expired: task date passed', $task->cancel_reason);
        $this->assertEquals(AssignmentStatus::Cancelled, $assignment->status);
    }

    public function test_expire_outdated_tasks_does_not_cancel_recent_tasks(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->employee()->create();

        $task = Task::factory()->for($admin, 'creator')->create([
            'status' => TaskStatus::Active,
            'task_date' => now()->toDateString(),
        ]);

        TaskAssignment::factory()->active()->for($task)->for($employee, 'user')->create();

        $this->artisan('tasks:expire-outdated')
            ->assertExitCode(0);

        $task->refresh();

        $this->assertEquals(TaskStatus::Active, $task->status);
    }
}

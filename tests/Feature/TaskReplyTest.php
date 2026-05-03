<?php

namespace Tests\Feature;

use App\Enums\AssignmentStatus;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\TaskReply;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaskReplyTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_submit_reply_to_own_active_assignment(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->employee()->create();
        $task = Task::factory()->for($admin, 'creator')->create([
            'status' => TaskStatus::Active,
        ]);
        $assignment = TaskAssignment::factory()->active()->for($task)->for($employee, 'user')->create([
            'last_reply_at' => null,
            'next_reply_due_at' => now()->subMinute(),
        ]);

        Sanctum::actingAs($employee);

        $response = $this->postJson("/api/assignments/{$assignment->id}/replies", [
            'content' => 'Machine inspection is progressing normally.',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.task_assignment_id', $assignment->id)
            ->assertJsonPath('data.content', 'Machine inspection is progressing normally.');

        $assignment->refresh();

        $this->assertNotNull($assignment->last_reply_at);
        $this->assertNotNull($assignment->next_reply_due_at);
        $this->assertTrue($assignment->next_reply_due_at->greaterThan($assignment->last_reply_at));
        $this->assertDatabaseHas('task_replies', [
            'task_assignment_id' => $assignment->id,
            'user_id' => $employee->id,
            'content' => 'Machine inspection is progressing normally.',
        ]);
    }

    public function test_employee_cannot_submit_reply_to_non_active_assignment(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->employee()->create();
        $task = Task::factory()->for($admin, 'creator')->create();
        $assignment = TaskAssignment::factory()->for($task)->for($employee, 'user')->create([
            'status' => AssignmentStatus::Pending,
        ]);

        Sanctum::actingAs($employee);

        $response = $this->postJson("/api/assignments/{$assignment->id}/replies", [
            'content' => 'Trying to reply too early.',
        ]);

        $response
            ->assertForbidden()
            ->assertJsonPath('success', false);

        $this->assertDatabaseMissing('task_replies', [
            'task_assignment_id' => $assignment->id,
        ]);
    }

    public function test_admin_and_assigned_employee_can_list_replies(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->employee()->create();
        $task = Task::factory()->for($admin, 'creator')->create([
            'status' => TaskStatus::Active,
        ]);
        $assignment = TaskAssignment::factory()->active()->for($task)->for($employee, 'user')->create();
        TaskReply::factory()->for($assignment, 'assignment')->for($employee, 'user')->create([
            'content' => 'First hourly update.',
        ]);

        Sanctum::actingAs($admin);

        $this->getJson("/api/assignments/{$assignment->id}/replies")
            ->assertOk()
            ->assertJsonPath('data.replies.0.content', 'First hourly update.');

        Sanctum::actingAs($employee);

        $this->getJson("/api/assignments/{$assignment->id}/replies")
            ->assertOk()
            ->assertJsonPath('data.replies.0.content', 'First hourly update.');
    }

    public function test_employee_cannot_view_or_submit_replies_for_another_employee_assignment(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->employee()->create();
        $otherEmployee = User::factory()->employee()->create();
        $task = Task::factory()->for($admin, 'creator')->create([
            'status' => TaskStatus::Active,
        ]);
        $assignment = TaskAssignment::factory()->active()->for($task)->for($owner, 'user')->create();

        Sanctum::actingAs($otherEmployee);

        $this->getJson("/api/assignments/{$assignment->id}/replies")
            ->assertForbidden();

        $this->postJson("/api/assignments/{$assignment->id}/replies", [
            'content' => 'This should not be accepted.',
        ])
            ->assertForbidden();
    }
}

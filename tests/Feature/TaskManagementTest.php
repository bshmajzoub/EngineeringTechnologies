<?php

namespace Tests\Feature;

use App\Enums\AssignmentStatus;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaskManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_task_with_multiple_assignments(): void
    {
        $admin = User::factory()->admin()->create();
        $employeeOne = User::factory()->employee()->create();
        $employeeTwo = User::factory()->employee()->create();
        $startAt = now()->addDay()->setTime(8, 0);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/tasks', [
            'title' => 'Machine A Maintenance',
            'description' => 'Full inspection and oil change',
            'task_date' => $startAt->toDateString(),
            'start_at' => $startAt->toDateTimeString(),
            'end_at' => $startAt->copy()->addHours(4)->toDateTimeString(),
            'employee_ids' => [$employeeOne->id, $employeeTwo->id],
            'assignment_notes' => [
                (string) $employeeOne->id => 'Inspect motor',
                (string) $employeeTwo->id => 'Change oil',
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'Machine A Maintenance')
            ->assertJsonPath('data.status', TaskStatus::Pending->value)
            ->assertJsonCount(2, 'data.assignments');

        $this->assertDatabaseHas('tasks', [
            'title' => 'Machine A Maintenance',
            'status' => TaskStatus::Pending->value,
            'created_by' => $admin->id,
        ]);

        $this->assertDatabaseHas('task_assignments', [
            'user_id' => $employeeOne->id,
            'status' => AssignmentStatus::Pending->value,
            'notes' => 'Inspect motor',
        ]);
    }

    public function test_admin_cannot_assign_employee_with_pending_or_active_assignment(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->employee()->create();
        $existingTask = Task::factory()->for($admin, 'creator')->create();

        TaskAssignment::factory()->for($existingTask)->for($employee, 'user')->create([
            'status' => AssignmentStatus::Pending,
            'accepted_at' => now(),
        ]);

        Sanctum::actingAs($admin);

        $startAt = now()->addDay();

        $this->postJson('/api/tasks', [
            'title' => 'Second Task',
            'task_date' => $startAt->toDateString(),
            'start_at' => $startAt->toDateTimeString(),
            'employee_ids' => [$employee->id],
        ])
            ->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['employee_ids']);
    }

    public function test_employee_can_only_view_assigned_tasks(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->employee()->create();
        $otherEmployee = User::factory()->employee()->create();
        $ownTask = Task::factory()->for($admin, 'creator')->create();
        $otherTask = Task::factory()->for($admin, 'creator')->create();

        TaskAssignment::factory()->for($ownTask)->for($employee, 'user')->create();
        TaskAssignment::factory()->for($otherTask)->for($otherEmployee, 'user')->create();

        Sanctum::actingAs($employee);

        $this->getJson('/api/tasks')
            ->assertOk()
            ->assertJsonCount(1, 'data.tasks')
            ->assertJsonPath('data.tasks.0.id', $ownTask->id);

        $this->getJson("/api/tasks/{$ownTask->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $ownTask->id);

        $this->getJson("/api/tasks/{$otherTask->id}")
            ->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Task not found.');
    }

    public function test_admin_can_activate_task_and_parent_completes_after_all_assignments_complete(): void
    {
        $admin = User::factory()->admin()->create();
        $employeeOne = User::factory()->employee()->create();
        $employeeTwo = User::factory()->employee()->create();
        $task = Task::factory()->for($admin, 'creator')->create();
        $assignmentOne = TaskAssignment::factory()->accepted()->for($task)->for($employeeOne, 'user')->create();
        $assignmentTwo = TaskAssignment::factory()->accepted()->for($task)->for($employeeTwo, 'user')->create();

        Sanctum::actingAs($admin);

        $this->patchJson("/api/tasks/{$task->id}/activate")
            ->assertOk()
            ->assertJsonPath('data.status', TaskStatus::Active->value)
            ->assertJsonPath('data.assignments.0.status', AssignmentStatus::Active->value);

        Sanctum::actingAs($employeeOne);

        $this->patchJson("/api/assignments/{$assignmentOne->id}/complete")
            ->assertOk()
            ->assertJsonPath('data.status', AssignmentStatus::Completed->value);

        $this->assertSame(TaskStatus::Active, $task->refresh()->status);

        Sanctum::actingAs($employeeTwo);

        $this->patchJson("/api/assignments/{$assignmentTwo->id}/complete")
            ->assertOk()
            ->assertJsonPath('data.status', AssignmentStatus::Completed->value);

        $task->refresh();

        $this->assertSame(TaskStatus::Completed, $task->status);
        $this->assertNotNull($task->completed_at);
    }

    public function test_admin_can_cancel_task_and_assignments(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->employee()->create();
        $task = Task::factory()->for($admin, 'creator')->create();
        $assignment = TaskAssignment::factory()->for($task)->for($employee, 'user')->create();

        Sanctum::actingAs($admin);

        $this->patchJson("/api/tasks/{$task->id}/cancel", [
            'cancel_reason' => 'Parts not available',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', TaskStatus::Cancelled->value)
            ->assertJsonPath('data.cancel_reason', 'Parts not available');

        $this->assertSame(TaskStatus::Cancelled, $task->refresh()->status);
        $this->assertSame(AssignmentStatus::Cancelled, $assignment->refresh()->status);
    }

    public function test_employee_can_get_current_assignment_and_cannot_create_task(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->employee()->create();
        $task = Task::factory()->for($admin, 'creator')->create();
        $assignment = TaskAssignment::factory()->for($task)->for($employee, 'user')->create();

        Sanctum::actingAs($employee);

        $this->getJson('/api/assignments/my-current')
            ->assertOk()
            ->assertJsonPath('data.assignment.id', $assignment->id);

        $startAt = now()->addDay();

        $this->postJson('/api/tasks', [
            'title' => 'Employee Task Attempt',
            'task_date' => $startAt->toDateString(),
            'start_at' => $startAt->toDateTimeString(),
            'employee_ids' => [$employee->id],
        ])->assertForbidden();
    }

    public function test_employee_can_accept_pending_assignment_before_start_time(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->employee()->create();
        $task = Task::factory()->for($admin, 'creator')->create([
            'status' => TaskStatus::Pending,
            'start_at' => now()->addHour(),
        ]);
        $assignment = TaskAssignment::factory()->pending()->for($task)->for($employee, 'user')->create();

        Sanctum::actingAs($employee);

        $this->patchJson("/api/assignments/{$assignment->id}/accept")
            ->assertOk()
            ->assertJsonPath('data.status', AssignmentStatus::Pending->value)
            ->assertJsonPath('data.assigned_by.id', $admin->id);

        $assignment->refresh();

        $this->assertNotNull($assignment->accepted_at);
        $this->assertSame(AssignmentStatus::Pending, $assignment->status);
    }

    public function test_employee_can_accept_assignment_after_parent_task_is_active(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->employee()->create();
        $task = Task::factory()->for($admin, 'creator')->create([
            'status' => TaskStatus::Active,
            'start_at' => now()->subHour(),
        ]);
        $assignment = TaskAssignment::factory()->pending()->for($task)->for($employee, 'user')->create();

        Sanctum::actingAs($employee);

        $this->patchJson("/api/assignments/{$assignment->id}/accept")
            ->assertOk()
            ->assertJsonPath('data.status', AssignmentStatus::Active->value);

        $assignment->refresh();

        $this->assertNotNull($assignment->accepted_at);
        $this->assertNotNull($assignment->next_reply_due_at);
    }

    public function test_employee_can_reject_assignment_with_reason_and_all_rejected_cancels_task(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->employee()->create();
        $task = Task::factory()->for($admin, 'creator')->create([
            'status' => TaskStatus::Pending,
        ]);
        $assignment = TaskAssignment::factory()->pending()->for($task)->for($employee, 'user')->create();

        Sanctum::actingAs($employee);

        $this->patchJson("/api/assignments/{$assignment->id}/reject", [
            'rejection_reason' => 'I am not available at this time.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', AssignmentStatus::Rejected->value)
            ->assertJsonPath('data.rejection_reason', 'I am not available at this time.');

        $assignment->refresh();
        $task->refresh();

        $this->assertNotNull($assignment->rejected_at);
        $this->assertSame(TaskStatus::Cancelled, $task->status);
        $this->assertSame('All assignments were rejected or cancelled.', $task->cancel_reason);
    }

    public function test_reject_assignment_requires_reason_and_wrong_employee_gets_404(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->employee()->create();
        $otherEmployee = User::factory()->employee()->create();
        $task = Task::factory()->for($admin, 'creator')->create();
        $assignment = TaskAssignment::factory()->pending()->for($task)->for($employee, 'user')->create();

        Sanctum::actingAs($employee);

        $this->patchJson("/api/assignments/{$assignment->id}/reject", [
            'rejection_reason' => '',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['rejection_reason']);

        Sanctum::actingAs($otherEmployee);

        $this->patchJson("/api/assignments/{$assignment->id}/accept")
            ->assertNotFound();
    }

    public function test_rejected_assignment_does_not_block_parent_task_completion(): void
    {
        $admin = User::factory()->admin()->create();
        $employeeOne = User::factory()->employee()->create();
        $employeeTwo = User::factory()->employee()->create();
        $task = Task::factory()->for($admin, 'creator')->create([
            'status' => TaskStatus::Active,
        ]);
        $activeAssignment = TaskAssignment::factory()->active()->for($task)->for($employeeOne, 'user')->create();
        TaskAssignment::factory()->rejected()->for($task)->for($employeeTwo, 'user')->create();

        Sanctum::actingAs($employeeOne);

        $this->patchJson("/api/assignments/{$activeAssignment->id}/complete")
            ->assertOk();

        $this->assertSame(TaskStatus::Completed, $task->refresh()->status);
    }

    public function test_admin_can_search_and_delete_tasks(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->employee()->create(['name' => 'Machine Operator']);
        $matchingTask = Task::factory()->for($admin, 'creator')->create([
            'title' => 'Machine Calibration',
            'status' => TaskStatus::Active,
            'task_date' => '2026-05-03',
        ]);
        $otherTask = Task::factory()->for($admin, 'creator')->create([
            'title' => 'Packaging',
            'task_date' => '2026-05-04',
        ]);
        TaskAssignment::factory()->active()->for($matchingTask)->for($employee, 'user')->create();

        Sanctum::actingAs($admin);

        $this->getJson("/api/admin/tasks?q=machine&status=active&employee_id={$employee->id}&date_from=2026-05-01&date_to=2026-05-05")
            ->assertOk()
            ->assertJsonPath('data.tasks.0.id', $matchingTask->id)
            ->assertJsonPath('data.tasks.0.assigned_by.id', $admin->id)
            ->assertJsonPath('data.pagination.per_page', 15);

        $this->deleteJson("/api/admin/tasks/{$matchingTask->id}")
            ->assertOk()
            ->assertJsonPath('data.deleted_count', 1);

        $this->postJson('/api/admin/tasks/bulk-delete', [
            'task_ids' => [$otherTask->id],
        ])
            ->assertOk()
            ->assertJsonPath('data.deleted_count', 1);

        $this->assertSoftDeleted($matchingTask);
        $this->assertSoftDeleted($otherTask);
    }
}

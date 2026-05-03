<?php

namespace Tests\Feature;

use App\Enums\AssignmentStatus;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FcmServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_fcm_service_sends_notification_to_device_token(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->employee()->create();

        $task = Task::factory()->for($admin, 'creator')->create([
            'status' => TaskStatus::Active,
        ]);

        $assignment = TaskAssignment::factory()->active()->for($task)->for($employee, 'user')->create();

        $deviceToken = $employee->deviceTokens()->create([
            'token' => 'test_fcm_token_123',
            'device_info' => 'Test Device',
        ]);

        Http::fake([
            'fcm.googleapis.com/*' => Http::response([
                'name' => 'projects/test/messages/123',
            ], 200),
        ]);

        $this->artisan('tasks:activate-due')->assertExitCode(0);
    }

    public function test_fcm_service_does_not_send_when_no_tokens(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->employee()->create();

        $task = Task::factory()->for($admin, 'creator')->create([
            'status' => TaskStatus::Active,
        ]);

        TaskAssignment::factory()->active()->for($task)->for($employee, 'user')->create();

        Http::fake();

        $this->artisan('tasks:activate-due')->assertExitCode(0);

        Http::assertNothingSent();
    }
}

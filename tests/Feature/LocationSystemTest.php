<?php

namespace Tests\Feature;

use App\Enums\LocationRequestStatus;
use App\Jobs\SendFcmNotification;
use App\Models\LiveEmployeeLocation;
use App\Models\LocationRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LocationSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_request_locations_for_specific_employees(): void
    {
        $admin = User::factory()->admin()->create();
        $employee1 = User::factory()->employee()->create();
        $employee2 = User::factory()->employee()->create();

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/location/request', [
            'employee_ids' => [$employee1->id, $employee2->id],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.interval_seconds', 5)
            ->assertJsonStructure([
                'data' => [
                    'location_request_id',
                    'tracking_session_id',
                    'interval_seconds',
                    'expires_at',
                ],
            ]);

        $this->assertDatabaseHas('location_requests', [
            'requested_by' => $admin->id,
            'status' => LocationRequestStatus::Active,
            'interval_seconds' => 5,
        ]);
    }

    public function test_location_request_dispatches_start_live_tracking_payload(): void
    {
        Queue::fake();
        config(['fcm.firebase_rtdb_url' => 'https://engiflow-2aaea-default-rtdb.firebaseio.com']);

        $admin = User::factory()->admin()->create();
        $employee = User::factory()->employee()->create();

        $employee->deviceTokens()->create([
            'token' => 'live_tracking_token_123',
            'device_info' => 'Test Device',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/location/request', [
            'employee_ids' => [$employee->id],
            'interval_seconds' => 7,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.interval_seconds', 7);

        $trackingSessionId = (string) $response->json('data.tracking_session_id');

        Queue::assertPushed(SendFcmNotification::class, function (SendFcmNotification $job) use ($employee, $trackingSessionId): bool {
            $tokens = (fn (): array => $this->tokens)->call($job);
            $data = (fn (): array => $this->data)->call($job);

            $this->assertSame(['live_tracking_token_123'], $tokens);
            $this->assertSame('start_live_tracking', $data['type']);
            $this->assertSame($trackingSessionId, $data['tracking_session_id']);
            $this->assertSame("/live_locations/{$employee->id}", $data['firebase_path']);
            $this->assertSame('https://engiflow-2aaea-default-rtdb.firebaseio.com', $data['firebase_rtdb_url']);
            $this->assertSame('7', $data['interval_seconds']);

            return true;
        });
    }

    public function test_admin_can_request_locations_for_all_employees(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->employee()->count(3)->create();

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/location/request');

        $response
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('location_requests', [
            'requested_by' => $admin->id,
            'status' => LocationRequestStatus::Active,
        ]);
    }

    public function test_employee_cannot_request_locations(): void
    {
        $employee = User::factory()->employee()->create();

        Sanctum::actingAs($employee);

        $response = $this->postJson('/api/location/request');

        $response->assertForbidden();
    }

    public function test_employee_can_submit_location(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->employee()->create();

        $locationRequest = LocationRequest::create([
            'requested_by' => $admin->id,
            'status' => LocationRequestStatus::Active,
            'expires_at' => now()->addMinutes(10),
        ]);

        Sanctum::actingAs($employee);

        $response = $this->postJson('/api/location/submit', [
            'latitude' => 33.5138,
            'longitude' => 36.2765,
            'accuracy' => 12.5,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('employee_locations', [
            'user_id' => $employee->id,
            'location_request_id' => $locationRequest->id,
            'latitude' => 33.5138,
            'longitude' => 36.2765,
        ]);

        $this->assertDatabaseHas('live_employee_locations', [
            'user_id' => $employee->id,
            'latitude' => 33.5138,
            'longitude' => 36.2765,
        ]);
    }

    public function test_employee_can_sync_location_batch(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->employee()->create();

        $locationRequest = LocationRequest::create([
            'requested_by' => $admin->id,
            'status' => LocationRequestStatus::Active,
            'expires_at' => now()->addMinutes(10),
        ]);

        $firstRecordedAt = now()->subMinutes(2)->setMicrosecond(0);
        $secondRecordedAt = now()->subMinute()->setMicrosecond(0);

        Sanctum::actingAs($employee);

        $response = $this->postJson('/api/location/sync', [
            'tracking_session_id' => $locationRequest->id,
            'points' => [
                [
                    'lat' => 33.5138,
                    'lng' => 36.2765,
                    'accuracy' => 12.5,
                    'speed' => 3.4,
                    'heading' => 120.0,
                    'recorded_at' => $firstRecordedAt->toDateTimeString(),
                ],
                [
                    'lat' => 33.5200,
                    'lng' => 36.2800,
                    'accuracy' => 8.0,
                    'speed' => 4.2,
                    'heading' => 125.5,
                    'recorded_at' => $secondRecordedAt->toDateTimeString(),
                ],
            ],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseCount('employee_locations', 2);
        $this->assertDatabaseHas('employee_locations', [
            'user_id' => $employee->id,
            'location_request_id' => $locationRequest->id,
            'latitude' => 33.5138,
            'longitude' => 36.2765,
            'speed' => 3.4,
            'heading' => 120.0,
            'recorded_at' => $firstRecordedAt->toDateTimeString(),
        ]);

        $this->assertDatabaseHas('live_employee_locations', [
            'user_id' => $employee->id,
            'tracking_session_id' => $locationRequest->id,
            'latitude' => 33.5200,
            'longitude' => 36.2800,
            'speed' => 4.2,
            'heading' => 125.5,
            'recorded_at' => $secondRecordedAt->toDateTimeString(),
        ]);
    }

    public function test_location_batch_sync_validation(): void
    {
        $employee = User::factory()->employee()->create();

        Sanctum::actingAs($employee);

        $response = $this->postJson('/api/location/sync', [
            'tracking_session_id' => 999,
            'points' => [
                [
                    'lat' => 33.5138,
                    'lng' => 36.2765,
                    'recorded_at' => now()->addMinute()->toDateTimeString(),
                ],
            ],
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['tracking_session_id', 'points.0.recorded_at']);
    }

    public function test_location_submission_updates_live_location(): void
    {
        $employee = User::factory()->employee()->create();

        LiveEmployeeLocation::create([
            'user_id' => $employee->id,
            'latitude' => 33.5000,
            'longitude' => 36.2000,
            'accuracy' => 10.0,
            'updated_at' => now()->subHour(),
        ]);

        Sanctum::actingAs($employee);

        $this->postJson('/api/location/submit', [
            'latitude' => 33.5138,
            'longitude' => 36.2765,
            'accuracy' => 12.5,
        ]);

        $this->assertDatabaseHas('live_employee_locations', [
            'user_id' => $employee->id,
            'latitude' => 33.5138,
            'longitude' => 36.2765,
            'accuracy' => 12.5,
        ]);

        $this->assertDatabaseMissing('live_employee_locations', [
            'user_id' => $employee->id,
            'latitude' => 33.5000,
        ]);
    }

    public function test_admin_cannot_submit_location(): void
    {
        $admin = User::factory()->admin()->create();

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/location/submit', [
            'latitude' => 33.5138,
            'longitude' => 36.2765,
        ]);

        $response->assertForbidden();
    }

    public function test_location_submission_validation(): void
    {
        $employee = User::factory()->employee()->create();

        Sanctum::actingAs($employee);

        $response = $this->postJson('/api/location/submit', [
            'latitude' => 'invalid',
            'longitude' => 36.2765,
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['latitude']);
    }

    public function test_admin_can_view_all_live_locations(): void
    {
        $admin = User::factory()->admin()->create();
        $employee1 = User::factory()->employee()->create();
        $employee2 = User::factory()->employee()->create();

        LiveEmployeeLocation::create([
            'user_id' => $employee1->id,
            'latitude' => 33.5138,
            'longitude' => 36.2765,
            'accuracy' => 12.5,
            'updated_at' => now(),
        ]);

        LiveEmployeeLocation::create([
            'user_id' => $employee2->id,
            'latitude' => 33.5200,
            'longitude' => 36.2800,
            'accuracy' => 8.0,
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/location/live');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'locations',
                ],
            ])
            ->assertJsonCount(2, 'data.locations');
    }

    public function test_employee_cannot_view_live_locations(): void
    {
        $employee = User::factory()->employee()->create();

        Sanctum::actingAs($employee);

        $response = $this->getJson('/api/location/live');

        $response->assertForbidden();
    }

    public function test_admin_can_view_single_employee_location(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->employee()->create();

        LiveEmployeeLocation::create([
            'user_id' => $employee->id,
            'latitude' => 33.5138,
            'longitude' => 36.2765,
            'accuracy' => 12.5,
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/location/live/{$employee->id}");

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user_id', $employee->id)
            ->assertJsonPath('data.latitude', 33.5138)
            ->assertJsonPath('data.longitude', 36.2765);
    }

    public function test_admin_gets_404_for_nonexistent_employee_location(): void
    {
        $admin = User::factory()->admin()->create();

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/location/live/999');

        $response->assertNotFound();
    }

    public function test_location_request_expires_after_timeout(): void
    {
        $admin = User::factory()->admin()->create();

        LocationRequest::create([
            'requested_by' => $admin->id,
            'status' => LocationRequestStatus::Active,
            'expires_at' => now()->subMinutes(1),
        ]);

        $this->artisan('location:expire-requests')->assertExitCode(0);

        $this->assertDatabaseHas('location_requests', [
            'requested_by' => $admin->id,
            'status' => LocationRequestStatus::Expired,
        ]);
    }

    public function test_active_location_requests_not_expired(): void
    {
        $admin = User::factory()->admin()->create();

        LocationRequest::create([
            'requested_by' => $admin->id,
            'status' => LocationRequestStatus::Active,
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->artisan('location:expire-requests')->assertExitCode(0);

        $this->assertDatabaseHas('location_requests', [
            'requested_by' => $admin->id,
            'status' => LocationRequestStatus::Active,
        ]);
    }

    public function test_location_history_is_preserved(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->employee()->create();

        $locationRequest1 = LocationRequest::create([
            'requested_by' => $admin->id,
            'status' => LocationRequestStatus::Active,
            'expires_at' => now()->addMinutes(10),
        ]);

        Sanctum::actingAs($employee);

        $this->postJson('/api/location/submit', [
            'latitude' => 33.5138,
            'longitude' => 36.2765,
        ]);

        $locationRequest2 = LocationRequest::create([
            'requested_by' => $admin->id,
            'status' => LocationRequestStatus::Active,
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->postJson('/api/location/submit', [
            'latitude' => 33.5200,
            'longitude' => 36.2800,
        ]);

        $this->assertDatabaseCount('employee_locations', 2);
        $this->assertDatabaseCount('live_employee_locations', 1);
    }
}

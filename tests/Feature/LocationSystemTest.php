<?php

namespace Tests\Feature;

use App\Enums\LocationRequestStatus;
use App\Enums\UserRole;
use App\Models\EmployeeLocation;
use App\Models\LiveEmployeeLocation;
use App\Models\LocationRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->assertJsonStructure([
                'data' => [
                    'location_request_id',
                    'expires_at',
                ],
            ]);

        $this->assertDatabaseHas('location_requests', [
            'requested_by' => $admin->id,
            'status' => LocationRequestStatus::Active,
        ]);
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

<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmployeeManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_employee_and_receive_plain_password_once(): void
    {
        $admin = User::factory()->admin()->create();

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin/employees', [
            'name' => 'Ahmad Ali',
            'email' => 'ahmad@example.com',
            'password' => 'emp12345',
            'phone' => '+963912345678',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.employee.email', 'ahmad@example.com')
            ->assertJsonPath('data.employee.role', UserRole::Employee->value)
            ->assertJsonPath('data.employee.is_active', true)
            ->assertJsonPath('data.plain_password', 'emp12345');

        $employee = User::where('email', 'ahmad@example.com')->firstOrFail();

        $this->assertTrue(Hash::check('emp12345', $employee->password));
        $this->assertSame(UserRole::Employee, $employee->role);
    }

    public function test_admin_can_list_view_update_and_toggle_employee(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->employee()->create([
            'name' => 'Old Name',
            'email' => 'old@example.com',
            'phone' => '+963900000001',
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/employees')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.employees.0.email', 'old@example.com');

        $this->getJson("/api/admin/employees/{$employee->id}")
            ->assertOk()
            ->assertJsonPath('data.email', 'old@example.com');

        $this->putJson("/api/admin/employees/{$employee->id}", [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'phone' => null,
            'password' => 'newpass123',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.email', 'updated@example.com')
            ->assertJsonPath('data.phone', null);

        $employee->refresh();

        $this->assertTrue(Hash::check('newpass123', $employee->password));

        $this->patchJson("/api/admin/employees/{$employee->id}/toggle-active")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->patchJson("/api/admin/employees/{$employee->id}/toggle-active")
            ->assertOk()
            ->assertJsonPath('data.is_active', true);
    }

    public function test_duplicate_employee_email_returns_validation_error(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->employee()->create(['email' => 'taken@example.com']);

        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/employees', [
            'name' => 'Duplicate Employee',
            'email' => 'taken@example.com',
            'password' => 'emp12345',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_employee_cannot_access_admin_employee_routes(): void
    {
        $employee = User::factory()->employee()->create();

        Sanctum::actingAs($employee);

        $this->getJson('/api/admin/employees')
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    public function test_deactivated_employee_cannot_log_in(): void
    {
        $employee = User::factory()->employee()->inactive()->create([
            'email' => 'inactive@example.com',
            'password' => 'secret123',
        ]);

        $this->postJson('/api/auth/login', [
            'email' => $employee->email,
            'password' => 'secret123',
        ])
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }
}

<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class EmployeeService
{
    /**
     * @param  array{name: string, email: string, password: string, phone?: string|null}  $data
     */
    public function create(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            return User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'phone' => $data['phone'] ?? null,
                'role' => UserRole::Employee,
                'is_active' => true,
            ]);
        });
    }

    /**
     * @param  array{name?: string, email?: string, password?: string|null, phone?: string|null}  $data
     */
    public function update(User $employee, array $data): User
    {
        return DB::transaction(function () use ($employee, $data): User {
            if (array_key_exists('password', $data)) {
                if ($data['password'] === null) {
                    unset($data['password']);
                } else {
                    $data['password'] = Hash::make($data['password']);
                }
            }

            $employee->update($data);

            return $employee->refresh();
        });
    }

    public function toggleActive(User $employee): User
    {
        return DB::transaction(function () use ($employee): User {
            $employee->forceFill([
                'is_active' => ! $employee->is_active,
            ])->save();

            if (! $employee->is_active) {
                $employee->tokens()->delete();
            }

            return $employee->refresh();
        });
    }
}

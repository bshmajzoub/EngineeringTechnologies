<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class EmployeeService
{
    /**
     * @param  array{q?: string|null, is_active?: bool|int|string|null}  $filters
     * @return LengthAwarePaginator<int, User>
     */
    public function list(array $filters, int $perPage): LengthAwarePaginator
    {
        return User::query()
            ->where('role', UserRole::Employee->value)
            ->when($filters['q'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when(
                array_key_exists('is_active', $filters),
                fn ($query) => $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN)),
            )
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array{name: string, email: string, password: string, phone?: string|null, shift_start_time?: string|null, shift_end_time?: string|null}  $data
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
                'shift_start_time' => $data['shift_start_time'] ?? null,
                'shift_end_time' => $data['shift_end_time'] ?? null,
            ]);
        });
    }

    /**
     * @param  array{name?: string, email?: string, password?: string|null, phone?: string|null, is_active?: bool, shift_start_time?: string|null, shift_end_time?: string|null}  $data
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

            if (array_key_exists('is_active', $data) && ! $employee->is_active) {
                $employee->tokens()->delete();
            }

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

    public function delete(User $employee): int
    {
        return $this->deleteMany([$employee->id]);
    }

    /**
     * @param  list<int>  $employeeIds
     */
    public function deleteMany(array $employeeIds): int
    {
        return DB::transaction(function () use ($employeeIds): int {
            $employees = User::query()
                ->whereIn('id', $employeeIds)
                ->where('role', UserRole::Employee->value)
                ->lockForUpdate()
                ->get();

            $deletedCount = 0;

            foreach ($employees as $employee) {
                $employee->tokens()->delete();

                if ($employee->delete()) {
                    $deletedCount++;
                }
            }

            return $deletedCount;
        });
    }

    public function deleteAllEmployees(): int
    {
        $employeeIds = User::query()
            ->where('role', UserRole::Employee->value)
            ->pluck('id')
            ->map(fn (int|string $employeeId): int => (int) $employeeId)
            ->all();

        return $this->deleteMany($employeeIds);
    }
}

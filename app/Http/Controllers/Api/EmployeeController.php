<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\StoreEmployeeRequest;
use App\Http\Requests\Employee\UpdateEmployeeRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\EmployeeService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class EmployeeController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly EmployeeService $employeeService) {}

    public function index(): JsonResponse
    {
        $employees = User::query()
            ->where('role', UserRole::Employee->value)
            ->latest('id')
            ->get();

        return $this->success([
            'employees' => UserResource::collection($employees),
        ], 'Employees retrieved successfully.');
    }

    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        $plainPassword = $request->validated('password');
        $employee = $this->employeeService->create($request->validated());

        return $this->success([
            'employee' => new UserResource($employee),
            'plain_password' => $plainPassword,
        ], 'Employee created successfully.', 201);
    }

    public function show(User $employee): JsonResponse
    {
        if (! $this->isEmployee($employee)) {
            return $this->employeeNotFoundResponse();
        }

        return $this->success(new UserResource($employee), 'Employee retrieved successfully.');
    }

    public function update(UpdateEmployeeRequest $request, User $employee): JsonResponse
    {
        if (! $this->isEmployee($employee)) {
            return $this->employeeNotFoundResponse();
        }

        $employee = $this->employeeService->update($employee, $request->validated());

        return $this->success(new UserResource($employee), 'Employee updated successfully.');
    }

    public function toggleActive(User $employee): JsonResponse
    {
        if (! $this->isEmployee($employee)) {
            return $this->employeeNotFoundResponse();
        }

        $employee = $this->employeeService->toggleActive($employee);

        $message = $employee->is_active
            ? 'Employee activated successfully.'
            : 'Employee deactivated successfully.';

        return $this->success(new UserResource($employee), $message);
    }

    private function isEmployee(User $user): bool
    {
        return $user->role === UserRole::Employee;
    }

    private function employeeNotFoundResponse(): JsonResponse
    {
        return $this->error('Employee not found.', 404);
    }
}

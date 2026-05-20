<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\BulkDeleteEmployeesRequest;
use App\Http\Requests\Employee\IndexEmployeeRequest;
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

    public function index(IndexEmployeeRequest $request): JsonResponse
    {
        $employees = $this->employeeService->list($request->validated(), $request->perPage());

        return $this->success([
            'employees' => UserResource::collection($employees->getCollection()),
            'pagination' => [
                'current_page' => $employees->currentPage(),
                'per_page' => $employees->perPage(),
                'total' => $employees->total(),
                'last_page' => $employees->lastPage(),
            ],
        ], 'Employees retrieved successfully.');
    }

    /**
     * @OA\Post(
     *     path="/api/employees",
     *     summary="Create a new employee",
     *     tags={"Employees"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="first_name", type="string"),
     *             @OA\Property(property="last_name", type="string"),
     *             @OA\Property(property="email", type="string", format="email"),
     *             @OA\Property(property="password", type="string", minLength=6),
     *             @OA\Property(property="phone", type="string", nullable=true),
     *             @OA\Property(property="shift_start_time", type="string", format="time", example="09:00", nullable=true),
     *             @OA\Property(property="shift_end_time", type="string", format="time", example="17:00", nullable=true)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Employee created successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Employee created successfully."),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="employee", ref="#/components/schemas/UserResource"),
     *                 @OA\Property(property="plain_password", type="string")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
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

    /**
     * @OA\Put(
     *     path="/api/admin/employees/{employee}",
     *     summary="Update an employee",
     *     tags={"Employees"},
     *
     *     @OA\Parameter(
     *         name="employee",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="email", type="string", format="email"),
     *             @OA\Property(property="password", type="string", minLength=6, nullable=true),
     *             @OA\Property(property="phone", type="string", nullable=true),
     *             @OA\Property(property="is_active", type="boolean"),
     *             @OA\Property(property="shift_start_time", type="string", format="time", example="09:00", nullable=true),
     *             @OA\Property(property="shift_end_time", type="string", format="time", example="17:00", nullable=true)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Employee updated successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Employee updated successfully."),
     *             @OA\Property(property="data", ref="#/components/schemas/UserResource")
     *         )
     *     ),
     *
     *     @OA\Response(response=404, description="Employee not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     *
     * @OA\Patch(
     *     path="/api/admin/employees/{employee}",
     *     summary="Update an employee (Patch)",
     *     tags={"Employees"},
     *
     *     @OA\Parameter(
     *         name="employee",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="email", type="string", format="email"),
     *             @OA\Property(property="password", type="string", minLength=6, nullable=true),
     *             @OA\Property(property="phone", type="string", nullable=true),
     *             @OA\Property(property="is_active", type="boolean"),
     *             @OA\Property(property="shift_start_time", type="string", format="time", example="09:00", nullable=true),
     *             @OA\Property(property="shift_end_time", type="string", format="time", example="17:00", nullable=true)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Employee updated successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Employee updated successfully."),
     *             @OA\Property(property="data", ref="#/components/schemas/UserResource")
     *         )
     *     ),
     *
     *     @OA\Response(response=404, description="Employee not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
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

    public function destroy(User $employee): JsonResponse
    {
        if (! $this->isEmployee($employee)) {
            return $this->employeeNotFoundResponse();
        }

        $deletedCount = $this->employeeService->delete($employee);

        return $this->success([
            'deleted_count' => $deletedCount,
        ], 'Employees deleted successfully.');
    }

    public function bulkDelete(BulkDeleteEmployeesRequest $request): JsonResponse
    {
        $employeeIds = collect($request->validated('employee_ids'))
            ->map(fn (int|string $employeeId): int => (int) $employeeId)
            ->unique()
            ->values()
            ->all();

        $deletedCount = $this->employeeService->deleteMany($employeeIds);

        return $this->success([
            'deleted_count' => $deletedCount,
        ], 'Employees deleted successfully.');
    }

    public function deleteAllEmployees(): JsonResponse
    {
        $deletedCount = $this->employeeService->deleteAllEmployees();

        return $this->success([
            'deleted_count' => $deletedCount,
        ], 'Employees deleted successfully.');
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

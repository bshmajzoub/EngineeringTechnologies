<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Exceptions\AssignmentConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Task\BulkDeleteTasksRequest;
use App\Http\Requests\Task\CancelTaskRequest;
use App\Http\Requests\Task\IndexTaskRequest;
use App\Http\Requests\Task\StoreTaskRequest;
use App\Http\Requests\Task\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Services\TaskService;
use App\Traits\ApiResponse;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TaskController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly TaskService $taskService) {}

    public function index(Request $request): JsonResponse
    {
        $tasks = $this->taskService->listFor($request->user());

        return $this->success([
            'tasks' => TaskResource::collection($tasks),
        ], 'Tasks retrieved successfully.');
    }

    public function adminIndex(IndexTaskRequest $request): JsonResponse
    {
        $tasks = $this->taskService->listAdmin($request->validated(), $request->perPage());

        return $this->success([
            'tasks' => TaskResource::collection($tasks->getCollection()),
            'pagination' => [
                'current_page' => $tasks->currentPage(),
                'per_page' => $tasks->perPage(),
                'total' => $tasks->total(),
                'last_page' => $tasks->lastPage(),
            ],
        ], 'Tasks retrieved successfully.');
    }

    public function store(StoreTaskRequest $request): JsonResponse
    {
        Gate::authorize('create', Task::class);

        try {
            $task = $this->taskService->create($request->user(), $request->validated());
        } catch (AssignmentConflictException $exception) {
            return $this->error($exception->getMessage(), 409, $exception->errors());
        }

        return $this->success(new TaskResource($task), 'Task created successfully.', 201);
    }

    public function show(Request $request, Task $task): JsonResponse
    {
        $user = $request->user();

        // Admin can view any task. Employees may only view tasks they are
        // assigned to — return 404 (not 403) to prevent ID enumeration.
        if ($user->role === UserRole::Employee) {
            $isAssigned = $task->assignments()
                ->where('user_id', $user->id)
                ->exists();

            if (! $isAssigned) {
                return $this->error('Task not found.', 404);
            }
        }

        return $this->success(
            new TaskResource($task->load(['assignments.user', 'creator', 'assignedBy'])),
            'Task retrieved successfully.',
        );
    }

    public function update(UpdateTaskRequest $request, Task $task): JsonResponse
    {
        Gate::authorize('update', $task);

        try {
            $task = $this->taskService->update($task, $request->validated());
        } catch (DomainException $exception) {
            return $this->error($exception->getMessage(), 409);
        }

        return $this->success(new TaskResource($task), 'Task updated successfully.');
    }

    public function cancel(CancelTaskRequest $request, Task $task): JsonResponse
    {
        Gate::authorize('cancel', $task);

        try {
            $task = $this->taskService->cancel($task, $request->validated('cancel_reason'));
        } catch (DomainException $exception) {
            return $this->error($exception->getMessage(), 409);
        }

        return $this->success(new TaskResource($task), 'Task cancelled successfully.');
    }

    public function activate(Task $task): JsonResponse
    {
        Gate::authorize('activate', $task);

        try {
            $task = $this->taskService->activate($task);
        } catch (DomainException $exception) {
            return $this->error($exception->getMessage(), 409);
        }

        return $this->success(new TaskResource($task), 'Task activated successfully.');
    }

    public function destroy(Task $task): JsonResponse
    {
        Gate::authorize('delete', $task);

        $deletedCount = $this->taskService->delete($task);

        return $this->success([
            'deleted_count' => $deletedCount,
        ], 'Tasks deleted successfully.');
    }

    public function bulkDelete(BulkDeleteTasksRequest $request): JsonResponse
    {
        $taskIds = collect($request->validated('task_ids'))
            ->map(fn (int|string $taskId): int => (int) $taskId)
            ->unique()
            ->values()
            ->all();

        $deletedCount = $this->taskService->deleteMany($taskIds);

        return $this->success([
            'deleted_count' => $deletedCount,
        ], 'Tasks deleted successfully.');
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TaskAssignmentResource;
use App\Models\TaskAssignment;
use App\Services\TaskService;
use App\Traits\ApiResponse;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TaskAssignmentController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly TaskService $taskService) {}

    public function myCurrent(Request $request): JsonResponse
    {
        $assignment = $this->taskService->myCurrentAssignment($request->user());

        return $this->success([
            'assignment' => $assignment ? new TaskAssignmentResource($assignment) : null,
        ], 'Current assignment retrieved successfully.');
    }

    public function complete(TaskAssignment $assignment): JsonResponse
    {
        Gate::authorize('complete', $assignment);

        try {
            $assignment = $this->taskService->completeAssignment($assignment);
        } catch (DomainException $exception) {
            return $this->error($exception->getMessage(), 409);
        }

        return $this->success(new TaskAssignmentResource($assignment), 'Assignment completed successfully.');
    }
}

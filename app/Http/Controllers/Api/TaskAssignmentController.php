<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Assignment\RejectAssignmentRequest;
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

    public function accept(Request $request, TaskAssignment $assignment): JsonResponse
    {
        if ($assignment->user_id !== $request->user()->id) {
            return $this->error('Assignment not found.', 404);
        }

        try {
            $assignment = $this->taskService->acceptAssignment($request->user(), $assignment);
        } catch (DomainException $exception) {
            return $this->error($exception->getMessage(), 409);
        }

        return $this->success(new TaskAssignmentResource($assignment), 'Assignment accepted successfully.');
    }

    public function reject(RejectAssignmentRequest $request, TaskAssignment $assignment): JsonResponse
    {
        if ($assignment->user_id !== $request->user()->id) {
            return $this->error('Assignment not found.', 404);
        }

        try {
            $assignment = $this->taskService->rejectAssignment(
                $request->user(),
                $assignment,
                $request->validated('rejection_reason'),
            );
        } catch (DomainException $exception) {
            return $this->error($exception->getMessage(), 409);
        }

        return $this->success(new TaskAssignmentResource($assignment), 'Assignment rejected successfully.');
    }
}

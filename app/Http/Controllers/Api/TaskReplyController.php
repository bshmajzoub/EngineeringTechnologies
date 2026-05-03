<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\UnauthorizedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reply\StoreReplyRequest;
use App\Http\Resources\TaskReplyResource;
use App\Models\TaskAssignment;
use App\Services\TaskReplyService;
use App\Traits\ApiResponse;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class TaskReplyController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly TaskReplyService $taskReplyService) {}

    public function index(TaskAssignment $assignment): JsonResponse
    {
        Gate::authorize('viewReplies', $assignment);

        return $this->success([
            'replies' => TaskReplyResource::collection(
                $this->taskReplyService->listForAssignment($assignment),
            ),
        ], 'Replies retrieved successfully.');
    }

    public function store(StoreReplyRequest $request, TaskAssignment $assignment): JsonResponse
    {
        Gate::authorize('createReply', $assignment);

        try {
            $reply = $this->taskReplyService->create($assignment, $request->user(), $request->validated());
        } catch (UnauthorizedException $exception) {
            return $this->error($exception->getMessage(), 403);
        } catch (DomainException $exception) {
            return $this->error($exception->getMessage(), 409);
        }

        return $this->success(new TaskReplyResource($reply), 'Reply submitted successfully.', 201);
    }
}

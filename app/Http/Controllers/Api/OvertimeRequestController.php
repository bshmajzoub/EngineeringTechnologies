<?php

namespace App\Http\Controllers\Api;

use App\Enums\OvertimeRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Overtime\StoreOvertimeRequest;
use App\Http\Requests\Overtime\UpdateOvertimeStatusRequest;
use App\Models\OvertimeRequest;
use App\Services\FcmService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(name="Overtime", description="Endpoints for managing overtime requests")
 */
class OvertimeRequestController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly FcmService $fcmService) {}

    /**
     * @OA\Get(
     *     path="/api/admin/overtime-requests",
     *     summary="List all overtime requests",
     *     tags={"Overtime"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Overtime requests retrieved successfully")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $requests = OvertimeRequest::query()
            ->with('user:id,name,email')
            ->latest('id')
            ->paginate((int) $request->input('per_page', 15));

        return $this->success([
            'overtime_requests' => $requests->items(),
            'pagination' => [
                'current_page' => $requests->currentPage(),
                'per_page' => $requests->perPage(),
                'total' => $requests->total(),
                'last_page' => $requests->lastPage(),
            ],
        ], 'Overtime requests retrieved successfully.');
    }

    /**
     * @OA\Post(
     *     path="/api/overtime-requests",
     *     summary="Submit an overtime request",
     *     tags={"Overtime"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="date", type="string", format="date", example="2024-05-20"),
     *             @OA\Property(property="requested_hours", type="number", format="float", example=2.5)
     *         )
     *     ),
     *
     *     @OA\Response(response=201, description="Overtime request created successfully")
     * )
     */
    public function store(StoreOvertimeRequest $request): JsonResponse
    {
        $overtimeRequest = OvertimeRequest::create([
            'user_id' => $request->user()->id,
            'date' => $request->validated('date'),
            'requested_hours' => $request->validated('requested_hours'),
            'status' => OvertimeRequestStatus::Pending,
        ]);

        $this->fcmService->sendOvertimeRequestedNotification($overtimeRequest);

        return $this->success($overtimeRequest, 'Overtime request created successfully.', 201);
    }

    /**
     * @OA\Patch(
     *     path="/api/admin/overtime-requests/{overtimeRequest}/status",
     *     summary="Update overtime request status",
     *     tags={"Overtime"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="overtimeRequest", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="status", type="string", enum={"approved", "rejected"})
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Overtime request status updated successfully")
     * )
     */
    public function updateStatus(UpdateOvertimeStatusRequest $request, OvertimeRequest $overtimeRequest): JsonResponse
    {
        $overtimeRequest->update([
            'status' => $request->validated('status'),
        ]);

        $this->fcmService->sendOvertimeStatusUpdatedNotification($overtimeRequest);

        return $this->success($overtimeRequest, 'Overtime request status updated successfully.');
    }
}

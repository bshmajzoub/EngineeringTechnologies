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


class OvertimeRequestController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly FcmService $fcmService) {}


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


    public function updateStatus(UpdateOvertimeStatusRequest $request, OvertimeRequest $overtimeRequest): JsonResponse
    {
        $overtimeRequest->update([
            'status' => $request->validated('status'),
        ]);

        $this->fcmService->sendOvertimeStatusUpdatedNotification($overtimeRequest);

        return $this->success($overtimeRequest, 'Overtime request status updated successfully.');
    }
}

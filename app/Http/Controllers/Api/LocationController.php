<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Location\RequestLocationRequest;
use App\Http\Requests\Location\SubmitLocationRequest;
use App\Http\Requests\Location\SyncLocationBatchRequest;
use App\Services\LocationService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class LocationController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly LocationService $locationService) {}

    public function request(RequestLocationRequest $request): JsonResponse
    {
        $locationRequest = $this->locationService->requestLocations(
            $request->user(),
            $request->validated('employee_ids'),
            $request->validated('interval_seconds') ?? 5
        );

        return $this->success([
            'location_request_id' => $locationRequest->id,
            'tracking_session_id' => $locationRequest->id,
            'interval_seconds' => $locationRequest->interval_seconds,
            'expires_at' => $locationRequest->expires_at->toIso8601String(),
        ], 'Location request sent.');
    }

    public function syncBatch(SyncLocationBatchRequest $request): JsonResponse
    {
        $this->locationService->syncBatchLocations($request->user(), $request->validated());

        return $this->success(null, 'Location batch synced successfully.');
    }

    /**
     * @deprecated Use syncBatch() via POST /api/location/sync for batched tracking updates.
     */
    public function submit(SubmitLocationRequest $request): JsonResponse
    {
        $this->locationService->submitLocation($request->user(), $request->validated());

        return $this->success(null, 'Location submitted successfully.');
    }

    public function live(): JsonResponse
    {
        $locations = $this->locationService->getLiveLocations();

        return $this->success([
            'locations' => $locations,
        ], 'Live locations retrieved.');
    }

    public function liveForUser(int $userId): JsonResponse
    {
        $location = $this->locationService->getLiveLocationForUser($userId);

        if (! $location) {
            return $this->error('Location not found for this employee.', 404);
        }

        return $this->success($location, 'Employee location retrieved.');
    }
}

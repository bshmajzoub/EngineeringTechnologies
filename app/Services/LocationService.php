<?php

namespace App\Services;

use App\Enums\LocationRequestStatus;
use App\Enums\UserRole;
use App\Models\EmployeeLocation;
use App\Models\LiveEmployeeLocation;
use App\Models\LocationRequest;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LocationService
{
    public function __construct(private readonly FcmService $fcmService) {}

    public function requestLocations(User $admin, ?array $employeeIds = null, int $intervalSeconds = 5): LocationRequest
    {
        return DB::transaction(function () use ($admin, $employeeIds, $intervalSeconds): LocationRequest {
            $locationRequest = LocationRequest::create([
                'requested_by' => $admin->id,
                'status' => LocationRequestStatus::Active,
                'expires_at' => now()->addMinutes(10),
                'interval_seconds' => $intervalSeconds,
            ]);

            $targetEmployeeIds = $employeeIds ?? User::where('role', UserRole::Employee)
                ->where('is_active', true)
                ->pluck('id')
                ->toArray();

            $this->fcmService->sendStartLiveTrackingNotification(
                $targetEmployeeIds,
                $locationRequest->id,
                $locationRequest->interval_seconds
            );

            return $locationRequest;
        });
    }

    /**
     * @param  array{
     *     tracking_session_id: int|string,
     *     points: list<array{
     *         lat: float|int|string,
     *         lng: float|int|string,
     *         accuracy?: float|int|string|null,
     *         speed?: float|int|string|null,
     *         heading?: float|int|string|null,
     *         recorded_at: string
     *     }>
     * }  $data
     *
     * @throws ValidationException
     */
    public function syncBatchLocations(User $employee, array $data): void
    {
        DB::transaction(function () use ($employee, $data): void {
            $trackingSession = LocationRequest::query()
                ->whereKey((int) $data['tracking_session_id'])
                ->where('status', LocationRequestStatus::Active)
                ->where(function ($query): void {
                    $query->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                })
                ->first();

            if (! $trackingSession) {
                throw ValidationException::withMessages([
                    'tracking_session_id' => 'The selected tracking session is not active.',
                ]);
            }

            $now = now();
            $batch = collect($data['points'])
                ->map(function (array $point) use ($employee, $trackingSession, $now): array {
                    return [
                        'user_id' => $employee->id,
                        'location_request_id' => $trackingSession->id,
                        'latitude' => $point['lat'],
                        'longitude' => $point['lng'],
                        'accuracy' => $point['accuracy'] ?? null,
                        'speed' => $point['speed'] ?? null,
                        'heading' => $point['heading'] ?? null,
                        'recorded_at' => Carbon::parse($point['recorded_at']),
                        'created_at' => $now,
                    ];
                })
                ->all();

            EmployeeLocation::insert($batch);

            $lastPoint = $data['points'][array_key_last($data['points'])];

            LiveEmployeeLocation::updateOrCreate(
                ['user_id' => $employee->id],
                [
                    'tracking_session_id' => $trackingSession->id,
                    'latitude' => $lastPoint['lat'],
                    'longitude' => $lastPoint['lng'],
                    'accuracy' => $lastPoint['accuracy'] ?? null,
                    'speed' => $lastPoint['speed'] ?? null,
                    'heading' => $lastPoint['heading'] ?? null,
                    'recorded_at' => Carbon::parse($lastPoint['recorded_at']),
                    'updated_at' => $now,
                ]
            );
        }, attempts: 3);
    }

    public function submitLocation(User $employee, array $data): void
    {
        DB::transaction(function () use ($employee, $data): void {
            $activeRequest = LocationRequest::query()
                ->where('status', LocationRequestStatus::Active)
                ->where('expires_at', '>', now())
                ->latest()
                ->first();

            $now = now();

            EmployeeLocation::create([
                'user_id' => $employee->id,
                'location_request_id' => $activeRequest?->id,
                'latitude' => $data['latitude'],
                'longitude' => $data['longitude'],
                'accuracy' => $data['accuracy'] ?? null,
                'recorded_at' => $now,
                'created_at' => $now,
            ]);

            LiveEmployeeLocation::updateOrCreate(
                ['user_id' => $employee->id],
                [
                    'tracking_session_id' => $activeRequest?->id,
                    'latitude' => $data['latitude'],
                    'longitude' => $data['longitude'],
                    'accuracy' => $data['accuracy'] ?? null,
                    'speed' => null,
                    'heading' => null,
                    'recorded_at' => $now,
                    'updated_at' => $now,
                ]
            );
        });
    }

    public function getLiveLocations(): Collection
    {
        return LiveEmployeeLocation::query()
            ->with('user')
            ->get()
            ->map(function (LiveEmployeeLocation $location): array {
                return [
                    'user_id' => $location->user_id,
                    'name' => $location->user->name,
                    'latitude' => (float) $location->latitude,
                    'longitude' => (float) $location->longitude,
                    'accuracy' => $location->accuracy,
                    'updated_at' => $location->updated_at->toIso8601String(),
                ];
            });
    }

    public function getLiveLocationForUser(int $userId): ?array
    {
        $location = LiveEmployeeLocation::query()
            ->where('user_id', $userId)
            ->with('user')
            ->first();

        if (! $location) {
            return null;
        }

        return [
            'user_id' => $location->user_id,
            'name' => $location->user->name,
            'latitude' => (float) $location->latitude,
            'longitude' => (float) $location->longitude,
            'accuracy' => $location->accuracy,
            'updated_at' => $location->updated_at->toIso8601String(),
        ];
    }
}

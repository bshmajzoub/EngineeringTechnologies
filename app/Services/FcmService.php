<?php

namespace App\Services;

use App\Jobs\SendFcmNotification;
use App\Models\DeviceToken;
use App\Models\TaskAssignment;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FcmService
{
    private string $projectId;

    private string $fcmUrl;

    public function __construct()
    {
        $this->projectId = config('fcm.project_id');
        $this->fcmUrl = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";
    }

    // ─── Notification Triggers ──────────────────────────────────────────────────

    public function sendTaskAssignedNotification(TaskAssignment $assignment): void
    {
        $user = $assignment->user;
        $task = $assignment->task;

        $this->dispatchToUser($user, [
            'type' => 'task_assigned',
            'task_id' => (string) $task->id,
            'assignment_id' => (string) $assignment->id,
            'assigned_by_id' => (string) $task->created_by,
            'assigned_by_name' => (string) ($task->assignedBy?->name ?? $task->creator?->name ?? ''),
            'task_title' => $task->title,
            'task_date' => $task->task_date->toDateString(),
            'notes' => (string) ($assignment->notes ?? ''),
            'title' => 'New Task Assigned',
            'body' => "You have been assigned to: {$task->title}",
        ]);
    }

    public function sendTaskActivatedNotification(TaskAssignment $assignment): void
    {
        $user = $assignment->user;
        $task = $assignment->task;

        $this->dispatchToUser($user, [
            'type' => 'task_activated',
            'task_id' => (string) $task->id,
            'task_title' => $task->title,
            'assignment_id' => (string) $assignment->id,
            'title' => 'Task Started',
            'body' => "{$task->title} is now active. First update due in 1 hour.",
        ]);
    }

    public function sendHourlyReplyReminder(TaskAssignment $assignment): void
    {
        $user = $assignment->user;
        $task = $assignment->task;

        $this->dispatchToUser($user, [
            'type' => 'hourly_reply_required',
            'task_id' => (string) $task->id,
            'task_title' => $task->title,
            'assignment_id' => (string) $assignment->id,
            'title' => 'Progress Update Required',
            'body' => "Please submit your hourly progress update for: {$task->title}",
        ]);
    }

    public function sendTaskUpdatedNotification(TaskAssignment $assignment): void
    {
        $user = $assignment->user;
        $task = $assignment->task;

        $this->dispatchToUser($user, [
            'type' => 'task_updated',
            'task_id' => (string) $task->id,
            'task_title' => $task->title,
            'assignment_id' => (string) $assignment->id,
            'title' => 'Task Updated',
            'body' => "{$task->title} has been updated by the manager.",
        ]);
    }

    public function sendTaskCancelledNotification(TaskAssignment $assignment, ?string $reason = null): void
    {
        $user = $assignment->user;
        $task = $assignment->task;

        $this->dispatchToUser($user, [
            'type' => 'task_cancelled',
            'task_id' => (string) $task->id,
            'task_title' => $task->title,
            'assignment_id' => (string) $assignment->id,
            'cancel_reason' => $reason ?? '',
            'title' => 'Task Cancelled',
            'body' => "{$task->title} has been cancelled.",
        ]);
    }

    public function sendTaskAssignmentAcceptedNotification(TaskAssignment $assignment): void
    {
        $task = $assignment->task;
        $admin = $task->assignedBy;

        if (! $admin instanceof User) {
            return;
        }

        $this->dispatchToUser($admin, [
            'type' => 'task_assignment_accepted',
            'task_id' => (string) $task->id,
            'assignment_id' => (string) $assignment->id,
            'employee_id' => (string) $assignment->user_id,
            'employee_name' => (string) ($assignment->user?->name ?? ''),
            'task_title' => $task->title,
            'title' => 'Task Assignment Accepted',
            'body' => "{$assignment->user?->name} accepted: {$task->title}",
        ]);
    }

    public function sendTaskAssignmentRejectedNotification(TaskAssignment $assignment): void
    {
        $task = $assignment->task;
        $admin = $task->assignedBy;

        if (! $admin instanceof User) {
            return;
        }

        $this->dispatchToUser($admin, [
            'type' => 'task_assignment_rejected',
            'task_id' => (string) $task->id,
            'assignment_id' => (string) $assignment->id,
            'employee_id' => (string) $assignment->user_id,
            'employee_name' => (string) ($assignment->user?->name ?? ''),
            'task_title' => $task->title,
            'rejection_reason' => (string) ($assignment->rejection_reason ?? ''),
            'title' => 'Task Assignment Rejected',
            'body' => "{$assignment->user?->name} rejected: {$task->title}",
        ]);
    }

    public function sendStartLiveTrackingNotification(array $employeeIds, int $trackingSessionId, int $intervalSeconds = 5): void
    {
        $users = User::whereIn('id', $employeeIds)
            ->with('deviceTokens')
            ->get();
        $firebaseRtdbUrl = (string) config('fcm.firebase_rtdb_url', '');

        foreach ($users as $user) {
            $tokens = $user->deviceTokens->pluck('token')->toArray();

            if (empty($tokens)) {
                continue;
            }

            $this->dispatchToTokens($tokens, [
                'type' => 'start_live_tracking',
                'tracking_session_id' => (string) $trackingSessionId,
                'location_request_id' => (string) $trackingSessionId,
                'firebase_path' => "/live_locations/{$user->id}",
                'firebase_rtdb_url' => $firebaseRtdbUrl,
                'interval_seconds' => (string) $intervalSeconds,
                'title' => 'Live Tracking Started',
                'body' => 'Your manager is requesting live location tracking.',
            ]);
        }
    }

    /**
     * @deprecated Use sendStartLiveTrackingNotification() for Firebase hybrid tracking.
     */
    public function sendLocationRequestedNotification(array $employeeIds, int $locationRequestId): void
    {
        $this->sendStartLiveTrackingNotification($employeeIds, $locationRequestId);
    }

    // ─── Delivery Helpers ───────────────────────────────────────────────────────

    /**
     * Queue FCM notifications for a user's device tokens.
     * Tokens are looked up from the already-loaded relationship when available.
     */
    private function dispatchToUser(User $user, array $data): void
    {
        // Use already-loaded relation to avoid extra query inside transactions.
        $tokens = $user->relationLoaded('deviceTokens')
            ? $user->deviceTokens->pluck('token')->toArray()
            : $user->deviceTokens()->pluck('token')->toArray();

        if (empty($tokens)) {
            return;
        }

        $this->dispatchToTokens($tokens, $data);
    }

    /**
     * Dispatch a queued job to send FCM messages to the given tokens.
     * Keeps HTTP calls out of DB transactions.
     */
    private function dispatchToTokens(array $tokens, array $data): void
    {
        if (empty($tokens)) {
            return;
        }

        SendFcmNotification::dispatch($tokens, $data);
    }

    /**
     * Send FCM data-only messages to each token using the V1 HTTP API.
     * Called by SendFcmNotification job — must remain public.
     *
     * @param  array<string>  $tokens
     * @param  array<string, string>  $data  All values must be strings.
     */
    public function sendToTokens(array $tokens, array $data): void
    {
        if (empty($tokens)) {
            return;
        }

        $accessToken = $this->getAccessToken();

        if ($accessToken === null) {
            Log::error('FCM: Could not obtain access token. Skipping notification.', ['data' => $data]);

            return;
        }

        foreach ($tokens as $token) {
            $this->sendV1Message($token, $data, $accessToken);
        }
    }

    // ─── FCM V1 API ─────────────────────────────────────────────────────────────

    /**
     * Send a single FCM V1 data-only message to a device token.
     */
    private function sendV1Message(string $token, array $data, string $accessToken): void
    {
        // FCM V1 requires all data values to be strings.
        $stringData = array_map(fn (mixed $value): string => (string) $value, $data);

        $payload = [
            'message' => [
                'token' => $token,
                'data' => $stringData,
                'android' => [
                    'priority' => 'high',
                ],
            ],
        ];

        if (isset($data['title']) && isset($data['body'])) {
            $payload['message']['notification'] = [
                'title' => (string) $data['title'],
                'body' => (string) $data['body'],
            ];
        }

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$accessToken}",
            'Content-Type' => 'application/json',
        ])->post($this->fcmUrl, $payload);

        if ($response->successful()) {
            Log::info('FCM notification sent successfully', [
                'token' => $token,
                'data' => $data,
                'response' => $response->json(),
            ]);

            return;
        }

        $errorCode = $response->json('error.details.0.errorCode')
            ?? $response->json('error.status')
            ?? 'UNKNOWN';

        Log::error('FCM V1 notification failed', [
            'token' => $token,
            'data' => $data,
            'status' => $response->status(),
            'error_code' => $errorCode,
            'response' => $response->body(),
        ]);

        if (in_array($errorCode, ['UNREGISTERED', 'INVALID_ARGUMENT'], true)) {
            $this->removeInvalidToken($token);
        }
    }

    /**
     * Obtain a short-lived OAuth2 Bearer token using the service account JSON.
     * Cached for 55 minutes (tokens expire after 60 minutes).
     */
    private function getAccessToken(): ?string
    {
        return Cache::remember('fcm_access_token', 55 * 60, function (): ?string {
            $credentialsPath = base_path(config('fcm.credentials_path'));

            if (! file_exists($credentialsPath)) {
                Log::error('FCM: Service account credentials file not found.', [
                    'path' => $credentialsPath,
                ]);

                return null;
            }

            $credentials = json_decode(file_get_contents($credentialsPath), true);

            if (! is_array($credentials)) {
                Log::error('FCM: Failed to parse service account JSON.');

                return null;
            }

            return $this->fetchOAuth2Token($credentials);
        });
    }

    /**
     * Perform the JWT-based OAuth2 token exchange with Google's token endpoint.
     *
     * @param  array<string, mixed>  $credentials
     */
    private function fetchOAuth2Token(array $credentials): ?string
    {
        $now = time();
        $expiry = $now + 3600;

        $header = base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $claim = base64_encode(json_encode([
            'iss' => $credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => $credentials['token_uri'],
            'iat' => $now,
            'exp' => $expiry,
        ]));

        // URL-safe base64 (JWT spec)
        $header = str_replace(['+', '/', '='], ['-', '_', ''], $header);
        $claim = str_replace(['+', '/', '='], ['-', '_', ''], $claim);

        $unsignedJwt = "{$header}.{$claim}";
        $privateKey = $credentials['private_key'];

        $signature = '';
        if (! openssl_sign($unsignedJwt, $signature, $privateKey, 'SHA256')) {
            Log::error('FCM: Failed to sign JWT with private key.');

            return null;
        }

        $encodedSignature = str_replace(
            ['+', '/', '='],
            ['-', '_', ''],
            base64_encode($signature)
        );

        $jwt = "{$unsignedJwt}.{$encodedSignature}";

        $response = Http::asForm()->post($credentials['token_uri'], [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]);

        if (! $response->successful()) {
            Log::error('FCM: OAuth2 token exchange failed.', [
                'status' => $response->status(),
                'response' => $response->body(),
            ]);

            return null;
        }

        return $response->json('access_token');
    }

    private function removeInvalidToken(string $token): void
    {
        DeviceToken::where('token', $token)->delete();
    }
}

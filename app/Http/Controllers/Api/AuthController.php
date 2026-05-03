<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\DeviceToken;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    use ApiResponse;

    /**
     * Authenticate the user and issue a Sanctum bearer token.
     *
     * POST /api/auth/login
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return $this->error('Invalid credentials. Please check your email and password.', 401);
        }

        if (! $user->is_active) {
            return $this->error('Your account has been deactivated. Please contact the administrator.', 403);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return $this->success(
            [
                'user'  => new UserResource($user),
                'token' => $token,
            ],
            'Login successful',
        );
    }

    /**
     * Revoke the current token and log out.
     *
     * POST /api/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->success(message: 'Logged out successfully.');
    }

    /**
     * Return the authenticated user's profile.
     *
     * GET /api/auth/me
     */
    public function me(Request $request): JsonResponse
    {
        return $this->success(new UserResource($request->user()));
    }

    /**
     * Register or update an FCM device token for this user.
     *
     * POST /api/auth/device-token
     */
    public function registerDeviceToken(Request $request): JsonResponse
    {
        $request->validate([
            'token'       => ['required', 'string', 'max:500'],
            'device_info' => ['nullable', 'string', 'max:255'],
        ]);

        // updateOrCreate prevents duplicate tokens for the same user+device
        DeviceToken::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'token'   => $request->token,
            ],
            [
                'device_info' => $request->device_info,
            ],
        );

        return $this->success(message: 'Device token registered successfully.');
    }

    /**
     * Remove an FCM device token for this user.
     *
     * DELETE /api/auth/device-token
     */
    public function removeDeviceToken(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string', 'max:500'],
        ]);

        $request->user()
            ->deviceTokens()
            ->where('token', $request->token)
            ->delete();

        return $this->success(message: 'Device token removed successfully.');
    }
}

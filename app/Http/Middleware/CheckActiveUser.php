<?php

namespace App\Http\Middleware;

use App\Traits\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckActiveUser
{
    use ApiResponse;

    /**
     * Reject requests from deactivated users (is_active = false).
     * Runs after Sanctum authentication so $request->user() is always available.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->is_active) {
            // Revoke all tokens so the device cannot try again with the same token
            $user->tokens()->delete();

            return $this->error('Your account has been deactivated. Please contact the administrator.', 403);
        }

        return $next($request);
    }
}

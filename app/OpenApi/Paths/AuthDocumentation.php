<?php

namespace App\OpenApi\Paths;

use OpenApi\Attributes as OA;

final class AuthDocumentation
{
    #[OA\Post(
        path: '/api/auth/login',
        operationId: 'authLogin',
        summary: 'Login',
        description: 'Authenticate an active user and issue a Laravel Sanctum bearer token. Rate limited to 10 attempts per minute per IP.',
        tags: ['Auth'],
        security: [],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/LoginRequest'),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Login successful.', content: new OA\JsonContent(ref: '#/components/schemas/LoginResponse')),
            new OA\Response(response: 401, description: 'Invalid credentials.', content: new OA\JsonContent(ref: '#/components/schemas/ApiErrorResponse')),
            new OA\Response(response: 403, description: 'Account is deactivated.', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
            new OA\Response(response: 422, description: 'Validation error.', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
            new OA\Response(response: 429, description: 'Too many login attempts.', content: new OA\JsonContent(ref: '#/components/schemas/ApiErrorResponse')),
        ],
    )]
    public function login(): void {}

    #[OA\Post(
        path: '/api/auth/logout',
        operationId: 'authLogout',
        summary: 'Logout',
        description: 'Revoke the current Sanctum token.',
        tags: ['Auth'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Logged out.', content: new OA\JsonContent(ref: '#/components/schemas/SimpleMessageResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated.', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Inactive user.', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
        ],
    )]
    public function logout(): void {}
}

<?php

namespace App\OpenApi\Paths;

use OpenApi\Attributes as OA;

final class ProfileDocumentation
{
    #[OA\Get(
        path: '/api/auth/me',
        operationId: 'authMe',
        summary: 'Get authenticated profile',
        description: 'Return the authenticated user profile using UserResource.',
        tags: ['Profile'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Profile retrieved.',
                content: new OA\JsonContent(
                    required: ['success', 'message', 'data'],
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Success'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/User'),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 401, description: 'Unauthenticated.', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Inactive user.', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
        ],
    )]
    public function me(): void {}
}

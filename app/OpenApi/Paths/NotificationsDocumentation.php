<?php

namespace App\OpenApi\Paths;

use OpenApi\Attributes as OA;

final class NotificationsDocumentation
{
    #[OA\Post(
        path: '/api/auth/device-token',
        operationId: 'registerDeviceToken',
        summary: 'Register FCM device token',
        description: 'Register or update the authenticated user FCM device token.',
        tags: ['Notifications'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/DeviceTokenRequest')),
        responses: [
            new OA\Response(response: 200, description: 'Device token registered.', content: new OA\JsonContent(ref: '#/components/schemas/SimpleMessageResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated.', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Inactive user.', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
            new OA\Response(response: 422, description: 'Validation error.', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ],
    )]
    public function registerDeviceToken(): void {}

    #[OA\Delete(
        path: '/api/auth/device-token',
        operationId: 'removeDeviceToken',
        summary: 'Remove FCM device token',
        description: 'Remove an FCM device token from the authenticated user.',
        tags: ['Notifications'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['token'],
                properties: [new OA\Property(property: 'token', type: 'string', maxLength: 500, example: 'fcm_example_device_token')],
                type: 'object',
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Device token removed.', content: new OA\JsonContent(ref: '#/components/schemas/SimpleMessageResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated.', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Inactive user.', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
            new OA\Response(response: 422, description: 'Validation error.', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ],
    )]
    public function removeDeviceToken(): void {}
}

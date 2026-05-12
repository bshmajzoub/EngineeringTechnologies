<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\OpenApi(
    info: new OA\Info(
        version: '1.0.0',
        title: 'Notification Employee API',
        description: 'Production API contract for the employee task management, live location tracking, assignment reply, notification device token, and authentication endpoints.',
        contact: new OA\Contact(name: 'API Support'),
    ),
    servers: [
        new OA\Server(url: 'http://127.0.0.1:8000', description: 'Local development server'),
        new OA\Server(url: 'https://notifcation.teknovex.io/public/', description: 'Production server'),
    ],
    security: [
        ['bearerAuth' => []],
    ],
    tags: [
        new OA\Tag(name: 'Auth', description: 'Login, logout, and token lifecycle.'),
        new OA\Tag(name: 'Profile', description: 'Authenticated user profile.'),
        new OA\Tag(name: 'Users', description: 'Admin employee user management.'),
        new OA\Tag(name: 'Roles & Permissions', description: 'Role and permission concepts used by middleware; no dedicated API routes currently exist.'),
        new OA\Tag(name: 'Tasks', description: 'Task listing, creation, updates, activation, cancellation, and deletion.'),
        new OA\Tag(name: 'Assignments', description: 'Employee assignment workflow.'),
        new OA\Tag(name: 'Task Replies', description: 'Assignment progress replies.'),
        new OA\Tag(name: 'Products', description: 'No product API routes currently exist.'),
        new OA\Tag(name: 'Categories', description: 'No category API routes currently exist.'),
        new OA\Tag(name: 'Orders', description: 'No order API routes currently exist.'),
        new OA\Tag(name: 'Payments', description: 'No payment API routes currently exist.'),
        new OA\Tag(name: 'Notifications', description: 'FCM device token registration and removal.'),
        new OA\Tag(name: 'Reports', description: 'Live location reporting endpoints.'),
        new OA\Tag(name: 'Settings', description: 'No settings API routes currently exist.'),
    ],
)]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    description: 'Sanctum bearer token. Use a token returned by POST /api/auth/login, for example: 1|example_token_value.',
    bearerFormat: 'Sanctum',
    scheme: 'bearer',
)]
final class OpenApiSpec {}

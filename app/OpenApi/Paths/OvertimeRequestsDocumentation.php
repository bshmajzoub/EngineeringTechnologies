<?php

namespace App\OpenApi\Paths;

use OpenApi\Attributes as OA;

final class OvertimeRequestsDocumentation
{
    #[OA\Get(
        path: '/api/admin/overtime-requests',
        operationId: 'overtimeRequestsIndex',
        summary: 'List all overtime requests',
        tags: ['Overtime'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15), example: 15),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Overtime requests retrieved successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Overtime requests retrieved successfully.'),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(
                                    property: 'overtime_requests',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'id', type: 'integer', example: 1),
                                            new OA\Property(property: 'user_id', type: 'integer', example: 12),
                                            new OA\Property(property: 'date', type: 'string', format: 'date', example: '2024-05-20'),
                                            new OA\Property(property: 'requested_hours', type: 'number', format: 'float', example: 2.5),
                                            new OA\Property(property: 'status', type: 'string', enum: ['pending', 'approved', 'rejected'], example: 'pending'),
                                            new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
                                            new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
                                            new OA\Property(
                                                property: 'user',
                                                properties: [
                                                    new OA\Property(property: 'id', type: 'integer', example: 12),
                                                    new OA\Property(property: 'name', type: 'string', example: 'Sara Employee'),
                                                    new OA\Property(property: 'email', type: 'string', example: 'sara@example.com'),
                                                ],
                                                type: 'object'
                                            ),
                                        ],
                                        type: 'object'
                                    )
                                ),
                                new OA\Property(property: 'pagination', ref: '#/components/schemas/PaginationMeta'),
                            ],
                            type: 'object'
                        ),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated.', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Admin role required.', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
        ]
    )]
    public function index(): void {}

    #[OA\Post(
        path: '/api/overtime-requests',
        operationId: 'overtimeRequestsStore',
        summary: 'Submit an overtime request',
        tags: ['Overtime'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['date', 'requested_hours'],
                properties: [
                    new OA\Property(property: 'date', type: 'string', format: 'date', example: '2024-05-20'),
                    new OA\Property(property: 'requested_hours', type: 'number', format: 'float', example: 2.5),
                ],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Overtime request created successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Overtime request created successfully.'),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'user_id', type: 'integer', example: 12),
                                new OA\Property(property: 'date', type: 'string', format: 'date', example: '2024-05-20'),
                                new OA\Property(property: 'requested_hours', type: 'number', format: 'float', example: 2.5),
                                new OA\Property(property: 'status', type: 'string', enum: ['pending', 'approved', 'rejected'], example: 'pending'),
                                new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
                                new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
                            ],
                            type: 'object'
                        ),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated.', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 422, description: 'Validation error.', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ]
    )]
    public function store(): void {}

    #[OA\Patch(
        path: '/api/admin/overtime-requests/{overtimeRequest}/status',
        operationId: 'overtimeRequestsUpdateStatus',
        summary: 'Update overtime request status',
        tags: ['Overtime'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'overtimeRequest', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 1),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['status'],
                properties: [
                    new OA\Property(property: 'status', type: 'string', enum: ['approved', 'rejected'], example: 'approved'),
                ],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Overtime request status updated successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Overtime request status updated successfully.'),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'user_id', type: 'integer', example: 12),
                                new OA\Property(property: 'date', type: 'string', format: 'date', example: '2024-05-20'),
                                new OA\Property(property: 'requested_hours', type: 'number', format: 'float', example: 2.5),
                                new OA\Property(property: 'status', type: 'string', enum: ['pending', 'approved', 'rejected'], example: 'approved'),
                                new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
                                new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
                            ],
                            type: 'object'
                        ),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated.', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Admin role required.', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
            new OA\Response(response: 422, description: 'Validation error.', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ]
    )]
    public function updateStatus(): void {}
}

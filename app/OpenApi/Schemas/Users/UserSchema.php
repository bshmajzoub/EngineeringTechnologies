<?php

namespace App\OpenApi\Schemas\Users;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'User',
    required: ['id', 'name', 'email', 'role', 'is_active', 'created_at'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 12),
        new OA\Property(property: 'name', type: 'string', example: 'Sara Employee'),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'sara.employee@example.com'),
        new OA\Property(property: 'phone', type: 'string', nullable: true, example: '+963944000000'),
        new OA\Property(property: 'role', type: 'string', enum: ['admin', 'employee'], example: 'employee'),
        new OA\Property(property: 'is_active', type: 'boolean', example: true),
        new OA\Property(property: 'shift_start_time', type: 'string', format: 'time', nullable: true, example: '09:00:00'),
        new OA\Property(property: 'shift_end_time', type: 'string', format: 'time', nullable: true, example: '17:00:00'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2026-05-10T12:30:00+00:00'),
    ],
    type: 'object',
)]
final class UserSchema {}

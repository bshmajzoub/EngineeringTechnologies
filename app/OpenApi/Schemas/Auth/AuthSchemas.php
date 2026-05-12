<?php

namespace App\OpenApi\Schemas\Auth;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'LoginRequest',
    required: ['email', 'password'],
    properties: [
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'admin@example.com'),
        new OA\Property(property: 'password', type: 'string', minLength: 6, example: 'password'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'LoginResponse',
    required: ['success', 'message', 'data'],
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'message', type: 'string', example: 'Login successful'),
        new OA\Property(
            property: 'data',
            required: ['user', 'token'],
            properties: [
                new OA\Property(property: 'user', ref: '#/components/schemas/User'),
                new OA\Property(property: 'token', type: 'string', example: '1|example_token_value'),
            ],
            type: 'object',
        ),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'DeviceTokenRequest',
    required: ['token'],
    properties: [
        new OA\Property(property: 'token', type: 'string', maxLength: 500, example: 'fcm_example_device_token'),
        new OA\Property(property: 'device_info', type: 'string', maxLength: 255, nullable: true, example: 'Pixel 8 Pro / Android 15'),
    ],
    type: 'object',
)]
final class AuthSchemas {}

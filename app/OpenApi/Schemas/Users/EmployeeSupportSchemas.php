<?php

namespace App\OpenApi\Schemas\Users;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'UpdateEmployeeRequest',
    properties: [
        new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'Sara Employee'),
        new OA\Property(property: 'email', type: 'string', format: 'email', maxLength: 255, example: 'sara.employee@example.com'),
        new OA\Property(property: 'password', type: 'string', minLength: 6, maxLength: 50, nullable: true, example: 'newsecret123'),
        new OA\Property(property: 'phone', type: 'string', maxLength: 20, nullable: true, example: '+963944000000'),
        new OA\Property(property: 'is_active', type: 'boolean', example: true),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'EmployeeResponse',
    required: ['success', 'message', 'data'],
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'message', type: 'string', example: 'Employee updated successfully.'),
        new OA\Property(property: 'data', ref: '#/components/schemas/User'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'DeletedCountResponse',
    required: ['success', 'message', 'data'],
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'message', type: 'string', example: 'Employees deleted successfully.'),
        new OA\Property(
            property: 'data',
            required: ['deleted_count'],
            properties: [new OA\Property(property: 'deleted_count', type: 'integer', example: 1)],
            type: 'object',
        ),
    ],
    type: 'object',
)]
final class EmployeeSupportSchemas {}

<?php

namespace App\OpenApi\Schemas\Locations;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'LiveLocation',
    required: ['user_id', 'name', 'latitude', 'longitude', 'updated_at'],
    properties: [
        new OA\Property(property: 'user_id', type: 'integer', example: 12),
        new OA\Property(property: 'name', type: 'string', example: 'Sara Employee'),
        new OA\Property(property: 'latitude', type: 'number', format: 'float', example: 33.5138),
        new OA\Property(property: 'longitude', type: 'number', format: 'float', example: 36.2765),
        new OA\Property(property: 'accuracy', type: 'number', format: 'float', nullable: true, example: 8.5),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', example: '2026-05-10T12:30:00+00:00'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'LocationPoint',
    required: ['lat', 'lng', 'recorded_at'],
    properties: [
        new OA\Property(property: 'lat', type: 'number', format: 'float', minimum: -90, maximum: 90, example: 33.5138),
        new OA\Property(property: 'lng', type: 'number', format: 'float', minimum: -180, maximum: 180, example: 36.2765),
        new OA\Property(property: 'accuracy', type: 'number', format: 'float', nullable: true, minimum: 0, example: 8.5),
        new OA\Property(property: 'speed', type: 'number', format: 'float', nullable: true, minimum: 0, example: 1.2),
        new OA\Property(property: 'heading', type: 'number', format: 'float', nullable: true, minimum: 0, maximum: 360, example: 185),
        new OA\Property(property: 'recorded_at', type: 'string', format: 'date-time', example: '2026-05-10T12:30:00+00:00'),
    ],
    type: 'object',
)]
final class LocationSchemas {}

<?php

namespace App\OpenApi\Schemas\Locations;

use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'LocationRequestResponse', required: ['success', 'message', 'data'], properties: [new OA\Property(property: 'success', type: 'boolean', example: true), new OA\Property(property: 'message', type: 'string', example: 'Location request sent.'), new OA\Property(property: 'data', required: ['location_request_id', 'tracking_session_id', 'interval_seconds', 'expires_at'], properties: [new OA\Property(property: 'location_request_id', type: 'integer', example: 55), new OA\Property(property: 'tracking_session_id', type: 'integer', example: 55), new OA\Property(property: 'interval_seconds', type: 'integer', example: 5), new OA\Property(property: 'expires_at', type: 'string', format: 'date-time', example: '2026-05-10T12:40:00+00:00')], type: 'object')], type: 'object')]
#[OA\Schema(schema: 'LiveLocationsResponse', required: ['success', 'message', 'data'], properties: [new OA\Property(property: 'success', type: 'boolean', example: true), new OA\Property(property: 'message', type: 'string', example: 'Live locations retrieved.'), new OA\Property(property: 'data', properties: [new OA\Property(property: 'locations', type: 'array', items: new OA\Items(ref: '#/components/schemas/LiveLocation'))], type: 'object')], type: 'object')]
#[OA\Schema(schema: 'LiveLocationResponse', required: ['success', 'message', 'data'], properties: [new OA\Property(property: 'success', type: 'boolean', example: true), new OA\Property(property: 'message', type: 'string', example: 'Employee location retrieved.'), new OA\Property(property: 'data', ref: '#/components/schemas/LiveLocation')], type: 'object')]
final class LocationSupportSchemas {}

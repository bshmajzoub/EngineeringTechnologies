<?php

namespace App\OpenApi\Schemas\Tasks;

use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'AssignmentResponse', required: ['success', 'message', 'data'], properties: [new OA\Property(property: 'success', type: 'boolean', example: true), new OA\Property(property: 'message', type: 'string', example: 'Assignment accepted successfully.'), new OA\Property(property: 'data', ref: '#/components/schemas/TaskAssignment')], type: 'object')]
#[OA\Schema(schema: 'MyCurrentAssignmentResponse', required: ['success', 'message', 'data'], properties: [new OA\Property(property: 'success', type: 'boolean', example: true), new OA\Property(property: 'message', type: 'string', example: 'Current assignment retrieved successfully.'), new OA\Property(property: 'data', properties: [new OA\Property(property: 'assignment', ref: '#/components/schemas/TaskAssignment', nullable: true)], type: 'object')], type: 'object')]
#[OA\Schema(schema: 'ReplyResponse', required: ['success', 'message', 'data'], properties: [new OA\Property(property: 'success', type: 'boolean', example: true), new OA\Property(property: 'message', type: 'string', example: 'Reply submitted successfully.'), new OA\Property(property: 'data', ref: '#/components/schemas/TaskReply')], type: 'object')]
#[OA\Schema(schema: 'RepliesCollectionResponse', required: ['success', 'message', 'data'], properties: [new OA\Property(property: 'success', type: 'boolean', example: true), new OA\Property(property: 'message', type: 'string', example: 'Replies retrieved successfully.'), new OA\Property(property: 'data', properties: [new OA\Property(property: 'replies', type: 'array', items: new OA\Items(ref: '#/components/schemas/TaskReply'))], type: 'object')], type: 'object')]
final class AssignmentReplySupportSchemas {}

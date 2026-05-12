<?php

namespace App\OpenApi\Schemas\Tasks;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Task',
    required: ['id', 'title', 'task_date', 'start_at', 'status', 'created_by', 'created_at', 'updated_at'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 101),
        new OA\Property(property: 'title', type: 'string', example: 'Inspect warehouse safety equipment'),
        new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Check extinguishers and emergency exits.'),
        new OA\Property(property: 'task_date', type: 'string', format: 'date', example: '2026-05-12'),
        new OA\Property(property: 'start_at', type: 'string', format: 'date-time', example: '2026-05-12T09:00:00+00:00'),
        new OA\Property(property: 'end_at', type: 'string', format: 'date-time', nullable: true, example: '2026-05-12T11:00:00+00:00'),
        new OA\Property(property: 'status', type: 'string', enum: ['pending', 'active', 'completed', 'cancelled'], example: 'pending'),
        new OA\Property(property: 'created_by', type: 'integer', example: 1),
        new OA\Property(property: 'completed_at', type: 'string', format: 'date-time', nullable: true, example: null),
        new OA\Property(property: 'cancelled_at', type: 'string', format: 'date-time', nullable: true, example: null),
        new OA\Property(property: 'cancel_reason', type: 'string', nullable: true, example: null),
        new OA\Property(property: 'creator', ref: '#/components/schemas/User', nullable: true),
        new OA\Property(property: 'assigned_by', ref: '#/components/schemas/User', nullable: true),
        new OA\Property(property: 'assignments', type: 'array', items: new OA\Items(ref: '#/components/schemas/TaskAssignment')),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2026-05-10T12:30:00+00:00'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', example: '2026-05-10T12:30:00+00:00'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'TaskAssignment',
    required: ['id', 'task_id', 'user_id', 'status', 'missed_reply_count', 'created_at', 'updated_at'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 301),
        new OA\Property(property: 'task_id', type: 'integer', example: 101),
        new OA\Property(property: 'user_id', type: 'integer', example: 12),
        new OA\Property(property: 'status', type: 'string', enum: ['pending', 'active', 'completed', 'cancelled', 'rejected'], example: 'pending'),
        new OA\Property(property: 'notes', type: 'string', nullable: true, example: 'Use entrance B.'),
        new OA\Property(property: 'accepted_at', type: 'string', format: 'date-time', nullable: true, example: null),
        new OA\Property(property: 'rejected_at', type: 'string', format: 'date-time', nullable: true, example: null),
        new OA\Property(property: 'rejection_reason', type: 'string', nullable: true, example: null),
        new OA\Property(property: 'last_reply_at', type: 'string', format: 'date-time', nullable: true, example: null),
        new OA\Property(property: 'next_reply_due_at', type: 'string', format: 'date-time', nullable: true, example: null),
        new OA\Property(property: 'last_reminder_sent_at', type: 'string', format: 'date-time', nullable: true, example: null),
        new OA\Property(property: 'missed_reply_count', type: 'integer', example: 0),
        new OA\Property(property: 'completed_at', type: 'string', format: 'date-time', nullable: true, example: null),
        new OA\Property(property: 'cancelled_at', type: 'string', format: 'date-time', nullable: true, example: null),
        new OA\Property(property: 'employee', ref: '#/components/schemas/User', nullable: true),
        new OA\Property(property: 'assigned_by', ref: '#/components/schemas/User', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2026-05-10T12:30:00+00:00'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', example: '2026-05-10T12:30:00+00:00'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'TaskReply',
    required: ['id', 'task_assignment_id', 'user_id', 'content', 'created_at', 'updated_at'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 701),
        new OA\Property(property: 'task_assignment_id', type: 'integer', example: 301),
        new OA\Property(property: 'user_id', type: 'integer', example: 12),
        new OA\Property(property: 'content', type: 'string', example: 'Inspection is 50% complete.'),
        new OA\Property(property: 'employee', ref: '#/components/schemas/User', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2026-05-10T12:30:00+00:00'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', example: '2026-05-10T12:30:00+00:00'),
    ],
    type: 'object',
)]
final class TaskSchemas {}

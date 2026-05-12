<?php

namespace App\OpenApi\Schemas\Common;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'PaginationLinks',
    properties: [
        new OA\Property(property: 'first', type: 'string', format: 'uri', nullable: true, example: 'http://localhost:8000/api/admin/tasks?page=1'),
        new OA\Property(property: 'last', type: 'string', format: 'uri', nullable: true, example: 'http://localhost:8000/api/admin/tasks?page=3'),
        new OA\Property(property: 'prev', type: 'string', format: 'uri', nullable: true, example: null),
        new OA\Property(property: 'next', type: 'string', format: 'uri', nullable: true, example: 'http://localhost:8000/api/admin/tasks?page=2'),
    ],
    type: 'object',
)]
final class PaginationLinksSchema {}

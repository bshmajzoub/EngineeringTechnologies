<?php

namespace App\OpenApi\Paths;

use OpenApi\Attributes as OA;

final class UsersDocumentation
{
    #[OA\Get(
        path: '/api/admin/employees',
        operationId: 'employeesIndex',
        summary: 'List employees',
        description: 'Admin-only paginated employee list. Supports search by name, email, or phone, and active status filtering.',
        tags: ['Users'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'q', in: 'query', required: false, schema: new OA\Schema(type: 'string', maxLength: 255), example: 'sara'),
            new OA\Parameter(name: 'is_active', in: 'query', required: false, schema: new OA\Schema(type: 'boolean'), example: true),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 15), example: 15),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Employees retrieved.',
                content: new OA\JsonContent(
                    required: ['success', 'message', 'data'],
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Employees retrieved successfully.'),
                        new OA\Property(
                            property: 'data',
                            required: ['employees', 'pagination'],
                            properties: [
                                new OA\Property(property: 'employees', type: 'array', items: new OA\Items(ref: '#/components/schemas/User')),
                                new OA\Property(property: 'pagination', ref: '#/components/schemas/PaginationMeta'),
                            ],
                            type: 'object',
                        ),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 401, description: 'Unauthenticated.', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Admin role required or user inactive.', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
            new OA\Response(response: 422, description: 'Validation error.', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ],
    )]
    public function index(): void {}

    #[OA\Post(
        path: '/api/admin/employees',
        operationId: 'employeesStore',
        summary: 'Create employee',
        description: 'Admin-only employee creation. The response intentionally returns the plain generated password value supplied in the request.',
        tags: ['Users'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'password'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'Sara Employee'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', maxLength: 255, example: 'sara.employee@example.com'),
                    new OA\Property(property: 'password', type: 'string', minLength: 6, maxLength: 50, example: 'secret123'),
                    new OA\Property(property: 'phone', type: 'string', maxLength: 20, nullable: true, example: '+963944000000'),
                    new OA\Property(property: 'shift_start_time', type: 'string', format: 'time', nullable: true, example: '09:00'),
                    new OA\Property(property: 'shift_end_time', type: 'string', format: 'time', nullable: true, example: '17:00'),
                ],
                type: 'object',
            ),
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Employee created.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Employee created successfully.'),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'employee', ref: '#/components/schemas/User'),
                                new OA\Property(property: 'plain_password', type: 'string', example: 'secret123'),
                            ],
                            type: 'object',
                        ),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 401, description: 'Unauthenticated.', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Admin role required or user inactive.', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
            new OA\Response(response: 422, description: 'Validation error.', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ],
    )]
    public function store(): void {}

    #[OA\Get(
        path: '/api/admin/employees/{employee}',
        operationId: 'employeesShow',
        summary: 'Show employee',
        description: 'Admin-only employee detail. Non-employee users bound to this route return 404.',
        tags: ['Users'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'employee', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 12)],
        responses: [
            new OA\Response(response: 200, description: 'Employee retrieved.', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Employee retrieved successfully.'),
                new OA\Property(property: 'data', ref: '#/components/schemas/User'),
            ], type: 'object')),
            new OA\Response(response: 401, description: 'Unauthenticated.', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')),
            new OA\Response(response: 403, description: 'Admin role required or user inactive.', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')),
            new OA\Response(response: 404, description: 'Employee not found.', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundResponse')),
        ],
    )]
    public function show(): void {}

    #[OA\Put(path: '/api/admin/employees/{employee}', operationId: 'employeesUpdatePut', summary: 'Update employee', tags: ['Users'], security: [['bearerAuth' => []]], parameters: [new OA\Parameter(name: 'employee', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 12)], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/UpdateEmployeeRequest')), responses: [new OA\Response(response: 200, description: 'Employee updated.', content: new OA\JsonContent(ref: '#/components/schemas/EmployeeResponse')), new OA\Response(response: 401, description: 'Unauthenticated.', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')), new OA\Response(response: 403, description: 'Admin role required or user inactive.', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')), new OA\Response(response: 404, description: 'Employee not found.', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundResponse')), new OA\Response(response: 422, description: 'Validation error.', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse'))])]
    #[OA\Patch(path: '/api/admin/employees/{employee}', operationId: 'employeesUpdatePatch', summary: 'Partially update employee', tags: ['Users'], security: [['bearerAuth' => []]], parameters: [new OA\Parameter(name: 'employee', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 12)], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/UpdateEmployeeRequest')), responses: [new OA\Response(response: 200, description: 'Employee updated.', content: new OA\JsonContent(ref: '#/components/schemas/EmployeeResponse')), new OA\Response(response: 401, description: 'Unauthenticated.', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')), new OA\Response(response: 403, description: 'Admin role required or user inactive.', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')), new OA\Response(response: 404, description: 'Employee not found.', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundResponse')), new OA\Response(response: 422, description: 'Validation error.', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse'))])]
    public function update(): void {}

    #[OA\Patch(path: '/api/admin/employees/{employee}/toggle-active', operationId: 'employeesToggleActive', summary: 'Toggle employee active status', tags: ['Users'], security: [['bearerAuth' => []]], parameters: [new OA\Parameter(name: 'employee', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 12)], responses: [new OA\Response(response: 200, description: 'Employee activation toggled.', content: new OA\JsonContent(ref: '#/components/schemas/EmployeeResponse')), new OA\Response(response: 401, description: 'Unauthenticated.', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')), new OA\Response(response: 403, description: 'Admin role required or user inactive.', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')), new OA\Response(response: 404, description: 'Employee not found.', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundResponse'))])]
    public function toggleActive(): void {}

    #[OA\Delete(path: '/api/admin/employees/{employee}', operationId: 'employeesDestroy', summary: 'Delete employee', tags: ['Users'], security: [['bearerAuth' => []]], parameters: [new OA\Parameter(name: 'employee', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 12)], responses: [new OA\Response(response: 200, description: 'Employee deleted.', content: new OA\JsonContent(ref: '#/components/schemas/DeletedCountResponse')), new OA\Response(response: 401, description: 'Unauthenticated.', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')), new OA\Response(response: 403, description: 'Admin role required or user inactive.', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')), new OA\Response(response: 404, description: 'Employee not found.', content: new OA\JsonContent(ref: '#/components/schemas/NotFoundResponse'))])]
    public function destroy(): void {}

    #[OA\Post(path: '/api/admin/employees/bulk-delete', operationId: 'employeesBulkDelete', summary: 'Bulk delete employees', description: 'Deletes selected employee users. All IDs must belong to employees.', tags: ['Users'], security: [['bearerAuth' => []]], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['employee_ids'], properties: [new OA\Property(property: 'employee_ids', type: 'array', minItems: 1, items: new OA\Items(type: 'integer'), example: [12, 13])], type: 'object')), responses: [new OA\Response(response: 200, description: 'Employees deleted.', content: new OA\JsonContent(ref: '#/components/schemas/DeletedCountResponse')), new OA\Response(response: 401, description: 'Unauthenticated.', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')), new OA\Response(response: 403, description: 'Admin role required or user inactive.', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse')), new OA\Response(response: 422, description: 'Validation error.', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse'))])]
    public function bulkDelete(): void {}

    #[OA\Delete(path: '/api/admin/employees', operationId: 'employeesDeleteAll', summary: 'Delete all employees', tags: ['Users'], security: [['bearerAuth' => []]], responses: [new OA\Response(response: 200, description: 'Employees deleted.', content: new OA\JsonContent(ref: '#/components/schemas/DeletedCountResponse')), new OA\Response(response: 401, description: 'Unauthenticated.', content: new OA\JsonContent(ref: '#/components/schemas/UnauthenticatedResponse')), new OA\Response(response: 403, description: 'Admin role required or user inactive.', content: new OA\JsonContent(ref: '#/components/schemas/ForbiddenResponse'))])]
    public function deleteAll(): void {}
}

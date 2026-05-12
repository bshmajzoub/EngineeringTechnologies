<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Auth', description: 'Login, logout, and token lifecycle.')]
#[OA\Tag(name: 'Profile', description: 'Authenticated user profile.')]
#[OA\Tag(name: 'Users', description: 'Admin employee user management.')]
#[OA\Tag(name: 'Roles & Permissions', description: 'Role and permission concepts used by middleware; no dedicated API routes currently exist.')]
#[OA\Tag(name: 'Products', description: 'No product API routes currently exist.')]
#[OA\Tag(name: 'Categories', description: 'No category API routes currently exist.')]
#[OA\Tag(name: 'Orders', description: 'No order API routes currently exist.')]
#[OA\Tag(name: 'Payments', description: 'No payment API routes currently exist.')]
#[OA\Tag(name: 'Notifications', description: 'FCM device token registration and removal.')]
#[OA\Tag(name: 'Reports', description: 'Live location reporting endpoints.')]
#[OA\Tag(name: 'Settings', description: 'No settings API routes currently exist.')]
#[OA\Tag(name: 'Tasks', description: 'Task listing, creation, updates, activation, cancellation, and deletion.')]
#[OA\Tag(name: 'Assignments', description: 'Employee assignment workflow.')]
#[OA\Tag(name: 'Task Replies', description: 'Assignment progress replies.')]
final class TagsDocumentation {}

<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

/**
 * Documents the `/api/user` route closure in `routes/api.php`.
 */
#[OA\Get(
    path: '/api/user',
    operationId: 'apiUser',
    summary: 'Current user',
    description: 'Returns the authenticated user model (Sanctum token with `api` ability).',
    tags: ['Auth'],
    responses: [
        new OA\Response(
            response: 200,
            description: 'OK',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'id', type: 'integer'),
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'email_verified_at', type: 'string', format: 'date-time', nullable: true),
                    new OA\Property(property: 'locale', type: 'string', nullable: true),
                    new OA\Property(property: 'created_at', type: 'string', format: 'date-time', nullable: true),
                    new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', nullable: true),
                ],
                type: 'object'
            )
        ),
        new OA\Response(response: 401, description: 'Unauthenticated'),
    ]
)]
class ApiUserEndpoint {}

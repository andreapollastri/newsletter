<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\OpenApi(
    openapi: '3.0.0',
    info: new OA\Info(
        title: 'Newsletter API',
        description: 'REST API for subscribers, tags, templates, campaigns, messages, and newsletter statistics. Authenticate with a Bearer token created in the admin panel (API tokens with the `api` ability).',
        version: '1.0.0'
    ),
    servers: [
        new OA\Server(url: '/', description: 'Application root'),
    ],
    security: [['sanctum' => []]],
    tags: [
        new OA\Tag(name: 'Auth', description: 'Authenticated user'),
        new OA\Tag(name: 'Reports', description: 'Newsletter analytics and statistics'),
        new OA\Tag(name: 'Tags', description: 'Subscriber tags'),
        new OA\Tag(name: 'Subscribers', description: 'Newsletter subscribers'),
        new OA\Tag(name: 'Templates', description: 'Email HTML templates'),
        new OA\Tag(name: 'Campaigns', description: 'Newsletter campaigns'),
        new OA\Tag(name: 'Messages', description: 'Messages within a campaign'),
    ]
)]
#[OA\SecurityScheme(
    securityScheme: 'sanctum',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'Sanctum personal access token',
    description: 'Use `Authorization: Bearer {token}` with a token from the admin panel.'
)]
class OpenApiInfo {}

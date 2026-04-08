<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreTemplateRequest;
use App\Http\Requests\Api\UpdateTemplateRequest;
use App\Http\Resources\TemplateResource;
use App\Models\Template;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class TemplateController extends Controller
{
    #[OA\Get(
        path: '/api/templates',
        operationId: 'templatesIndex',
        description: 'Optional `q` filters by template name (contains).',
        summary: 'List templates',
        tags: ['Templates'],
        parameters: [
            new OA\Parameter(name: 'q', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                            new OA\Property(property: 'name', type: 'string'),
                            new OA\Property(property: 'html_content', type: 'string'),
                            new OA\Property(
                                property: 'placeholders',
                                type: 'array',
                                items: new OA\Items(type: 'string'),
                                nullable: true
                            ),
                            new OA\Property(property: 'messages_count', type: 'integer'),
                            new OA\Property(property: 'created_at', type: 'string', format: 'date-time', nullable: true),
                            new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', nullable: true),
                        ],
                        type: 'object'
                    )
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Template::class);

        $templates = Template::query()
            ->withCount('messages')
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->string('q').'%'))
            ->orderBy('name')
            ->get();

        return TemplateResource::collection($templates);
    }

    #[OA\Post(
        path: '/api/templates',
        operationId: 'templatesStore',
        summary: 'Create template',
        tags: ['Templates'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'html_content'],
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'html_content', type: 'string'),
                    new OA\Property(
                        property: 'placeholders',
                        type: 'array',
                        items: new OA\Items(type: 'string'),
                        nullable: true
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Created'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(StoreTemplateRequest $request): JsonResponse
    {
        $this->authorize('create', Template::class);

        $template = Template::query()->create($request->validated());

        return (new TemplateResource($template->loadCount('messages')))
            ->response()
            ->setStatusCode(201);
    }

    #[OA\Get(
        path: '/api/templates/{template}',
        operationId: 'templatesShow',
        summary: 'Get template',
        tags: ['Templates'],
        parameters: [
            new OA\Parameter(name: 'template', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function show(Template $template): TemplateResource
    {
        $this->authorize('view', $template);

        return new TemplateResource($template->loadCount('messages'));
    }

    #[OA\Put(
        path: '/api/templates/{template}',
        operationId: 'templatesUpdate',
        summary: 'Update template',
        tags: ['Templates'],
        parameters: [
            new OA\Parameter(name: 'template', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'html_content', type: 'string'),
                    new OA\Property(
                        property: 'placeholders',
                        type: 'array',
                        items: new OA\Items(type: 'string'),
                        nullable: true
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(UpdateTemplateRequest $request, Template $template): TemplateResource
    {
        $this->authorize('update', $template);

        $template->update($request->validated());

        return new TemplateResource($template->fresh()->loadCount('messages'));
    }

    #[OA\Delete(
        path: '/api/templates/{template}',
        operationId: 'templatesDestroy',
        summary: 'Delete template',
        tags: ['Templates'],
        parameters: [
            new OA\Parameter(name: 'template', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'No content'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function destroy(Template $template): JsonResponse
    {
        $this->authorize('delete', $template);

        $template->delete();

        return response()->json(null, 204);
    }
}

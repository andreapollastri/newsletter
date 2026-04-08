<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreCampaignRequest;
use App\Http\Requests\Api\UpdateCampaignRequest;
use App\Http\Resources\CampaignResource;
use App\Models\Campaign;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class CampaignController extends Controller
{
    #[OA\Get(
        path: '/api/campaigns',
        operationId: 'campaignsIndex',
        description: 'Returns all campaigns owned by the authenticated user.',
        summary: 'List campaigns',
        tags: ['Campaigns'],
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
                            new OA\Property(property: 'slug', type: 'string'),
                            new OA\Property(property: 'description', type: 'string', nullable: true),
                            new OA\Property(property: 'created_at', type: 'string', format: 'date-time', nullable: true),
                            new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', nullable: true),
                            new OA\Property(property: 'deleted_at', type: 'string', format: 'date-time', nullable: true),
                        ],
                        type: 'object'
                    )
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Campaign::class);

        $campaigns = Campaign::query()
            ->where('user_id', auth()->id())
            ->orderBy('name')
            ->get();

        return CampaignResource::collection($campaigns);
    }

    #[OA\Post(
        path: '/api/campaigns',
        operationId: 'campaignsStore',
        summary: 'Create campaign',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'slug', type: 'string', nullable: true),
                    new OA\Property(property: 'description', type: 'string', nullable: true),
                ]
            )
        ),
        tags: ['Campaigns'],
        responses: [
            new OA\Response(response: 201, description: 'Created'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(StoreCampaignRequest $request): JsonResponse
    {
        $this->authorize('create', Campaign::class);

        $data = $request->validated();
        $data['user_id'] = $request->user()->id;

        $campaign = Campaign::query()->create($data);

        return (new CampaignResource($campaign))
            ->response()
            ->setStatusCode(201);
    }

    #[OA\Get(
        path: '/api/campaigns/{campaign}',
        operationId: 'campaignsShow',
        summary: 'Get campaign',
        tags: ['Campaigns'],
        parameters: [
            new OA\Parameter(name: 'campaign', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function show(Campaign $campaign): CampaignResource
    {
        $this->authorize('view', $campaign);

        return new CampaignResource($campaign);
    }

    #[OA\Put(
        path: '/api/campaigns/{campaign}',
        operationId: 'campaignsUpdate',
        summary: 'Update campaign',
        tags: ['Campaigns'],
        parameters: [
            new OA\Parameter(name: 'campaign', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'slug', type: 'string', nullable: true),
                    new OA\Property(property: 'description', type: 'string', nullable: true),
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
    public function update(UpdateCampaignRequest $request, Campaign $campaign): CampaignResource
    {
        $this->authorize('update', $campaign);

        $campaign->update($request->validated());

        return new CampaignResource($campaign->fresh());
    }

    #[OA\Delete(
        path: '/api/campaigns/{campaign}',
        operationId: 'campaignsDestroy',
        summary: 'Delete campaign (soft delete)',
        tags: ['Campaigns'],
        parameters: [
            new OA\Parameter(name: 'campaign', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'No content'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function destroy(Campaign $campaign): JsonResponse
    {
        $this->authorize('delete', $campaign);

        $campaign->delete();

        return response()->json(null, 204);
    }
}

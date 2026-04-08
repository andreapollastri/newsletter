<?php

namespace App\Http\Controllers\Api;

use App\Enums\SubscriberStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreSubscriberRequest;
use App\Http\Requests\Api\UpdateSubscriberRequest;
use App\Http\Resources\SubscriberResource;
use App\Models\Subscriber;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class SubscriberController extends Controller
{
    #[OA\Get(
        path: '/api/subscribers',
        operationId: 'subscribersIndex',
        description: 'Filter with `q` (email or name contains), `status`, or `tag_id`.',
        summary: 'List subscribers',
        tags: ['Subscribers'],
        parameters: [
            new OA\Parameter(name: 'q', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(
                name: 'status',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['pending', 'confirmed', 'unsubscribed', 'bounced'])
            ),
            new OA\Parameter(name: 'tag_id', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid')),
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
                            new OA\Property(property: 'email', type: 'string', format: 'email'),
                            new OA\Property(property: 'name', type: 'string', nullable: true),
                            new OA\Property(property: 'status', type: 'string', enum: ['pending', 'confirmed', 'unsubscribed', 'bounced']),
                            new OA\Property(property: 'confirmed_at', type: 'string', format: 'date-time', nullable: true),
                            new OA\Property(property: 'unsubscribed_at', type: 'string', format: 'date-time', nullable: true),
                            new OA\Property(
                                property: 'tags',
                                type: 'array',
                                items: new OA\Items(
                                    properties: [
                                        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                                        new OA\Property(property: 'name', type: 'string'),
                                        new OA\Property(property: 'is_testing', type: 'boolean'),
                                        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', nullable: true),
                                        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', nullable: true),
                                    ],
                                    type: 'object'
                                )
                            ),
                            new OA\Property(property: 'created_at', type: 'string', format: 'date-time', nullable: true),
                            new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', nullable: true),
                            new OA\Property(property: 'deleted_at', type: 'string', format: 'date-time', nullable: true),
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
        $this->authorize('viewAny', Subscriber::class);

        $subscribers = Subscriber::query()
            ->with('tags')
            ->when($request->filled('status'), function ($q) use ($request): void {
                $status = SubscriberStatus::tryFrom($request->string('status')->toString());
                if ($status !== null) {
                    $q->where('status', $status);
                }
            })
            ->when($request->filled('tag_id'), function ($q) use ($request): void {
                $tagId = $request->string('tag_id')->toString();
                $q->whereHas('tags', fn ($tq) => $tq->where('tags.id', $tagId));
            })
            ->when($request->filled('q'), fn ($q) => $q->where(function ($qq) use ($request): void {
                $term = '%'.$request->string('q').'%';
                $qq->where('email', 'like', $term)
                    ->orWhere('name', 'like', $term);
            }))
            ->orderBy('email')
            ->get();

        return SubscriberResource::collection($subscribers);
    }

    #[OA\Post(
        path: '/api/subscribers',
        operationId: 'subscribersStore',
        summary: 'Create subscriber',
        tags: ['Subscribers'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'status'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'name', type: 'string', nullable: true),
                    new OA\Property(property: 'status', type: 'string', enum: ['pending', 'confirmed', 'unsubscribed', 'bounced']),
                    new OA\Property(
                        property: 'tag_ids',
                        type: 'array',
                        items: new OA\Items(type: 'string', format: 'uuid')
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
    public function store(StoreSubscriberRequest $request): JsonResponse
    {
        $this->authorize('create', Subscriber::class);

        $data = $request->validated();
        $tagIds = $data['tag_ids'] ?? [];
        unset($data['tag_ids']);

        $subscriber = Subscriber::query()->create($data);
        $this->syncTags($subscriber, $tagIds);

        return (new SubscriberResource($subscriber->load('tags')))
            ->response()
            ->setStatusCode(201);
    }

    #[OA\Get(
        path: '/api/subscribers/{subscriber}',
        operationId: 'subscribersShow',
        summary: 'Get subscriber',
        tags: ['Subscribers'],
        parameters: [
            new OA\Parameter(name: 'subscriber', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function show(Subscriber $subscriber): SubscriberResource
    {
        $this->authorize('view', $subscriber);

        return new SubscriberResource($subscriber->load('tags'));
    }

    #[OA\Put(
        path: '/api/subscribers/{subscriber}',
        operationId: 'subscribersUpdate',
        summary: 'Update subscriber',
        tags: ['Subscribers'],
        parameters: [
            new OA\Parameter(name: 'subscriber', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'name', type: 'string', nullable: true),
                    new OA\Property(property: 'status', type: 'string', enum: ['pending', 'confirmed', 'unsubscribed', 'bounced']),
                    new OA\Property(
                        property: 'tag_ids',
                        type: 'array',
                        items: new OA\Items(type: 'string', format: 'uuid')
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
    public function update(UpdateSubscriberRequest $request, Subscriber $subscriber): SubscriberResource
    {
        $this->authorize('update', $subscriber);

        $data = $request->validated();
        $tagIds = null;
        if (array_key_exists('tag_ids', $data)) {
            $tagIds = $data['tag_ids'];
            unset($data['tag_ids']);
        }

        if ($data !== []) {
            $subscriber->update($data);
        }

        if ($tagIds !== null) {
            $this->syncTags($subscriber, $tagIds);
        }

        return new SubscriberResource($subscriber->fresh()->load('tags'));
    }

    #[OA\Delete(
        path: '/api/subscribers/{subscriber}',
        operationId: 'subscribersDestroy',
        summary: 'Delete subscriber',
        tags: ['Subscribers'],
        parameters: [
            new OA\Parameter(name: 'subscriber', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'No content'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function destroy(Subscriber $subscriber): JsonResponse
    {
        $this->authorize('delete', $subscriber);

        $subscriber->delete();

        return response()->json(null, 204);
    }

    /**
     * @param  list<string>  $tagIds
     */
    private function syncTags(Subscriber $subscriber, array $tagIds): void
    {
        if ($tagIds === []) {
            $subscriber->tags()->sync([]);

            return;
        }

        $validIds = Tag::query()->whereIn('id', $tagIds)->pluck('id');
        $subscriber->tags()->sync($validIds);
    }
}

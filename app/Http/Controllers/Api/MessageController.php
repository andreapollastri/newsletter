<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreMessageRequest;
use App\Http\Requests\Api\UpdateMessageRequest;
use App\Http\Resources\MessageResource;
use App\Models\Campaign;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class MessageController extends Controller
{
    #[OA\Get(
        path: '/api/campaigns/{campaign}/messages',
        operationId: 'messagesIndex',
        summary: 'List messages in a campaign',
        tags: ['Messages'],
        parameters: [
            new OA\Parameter(name: 'campaign', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Campaign not found'),
        ]
    )]
    public function index(Campaign $campaign): AnonymousResourceCollection
    {
        $this->authorize('view', $campaign);

        $messages = $campaign->messages()
            ->with('tags')
            ->orderByDesc('created_at')
            ->get();

        return MessageResource::collection($messages);
    }

    #[OA\Post(
        path: '/api/campaigns/{campaign}/messages',
        operationId: 'messagesStore',
        summary: 'Create message',
        tags: ['Messages'],
        parameters: [
            new OA\Parameter(name: 'campaign', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['template_id', 'subject', 'html_content', 'status'],
                properties: [
                    new OA\Property(property: 'template_id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'subject', type: 'string'),
                    new OA\Property(property: 'html_content', type: 'string'),
                    new OA\Property(property: 'status', type: 'string', enum: ['draft', 'ready']),
                    new OA\Property(property: 'scheduled_at', type: 'string', format: 'date-time', nullable: true),
                    new OA\Property(
                        property: 'tag_ids',
                        type: 'array',
                        items: new OA\Items(type: 'string', format: 'uuid'),
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
    public function store(StoreMessageRequest $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('view', $campaign);

        $data = $request->validated();
        $tagIds = $data['tag_ids'] ?? [];
        unset($data['tag_ids']);

        $message = $campaign->messages()->create($data);
        $message->tags()->sync($tagIds);
        $message->load('tags');

        return (new MessageResource($message))
            ->response()
            ->setStatusCode(201);
    }

    #[OA\Get(
        path: '/api/campaigns/{campaign}/messages/{message}',
        operationId: 'messagesShow',
        summary: 'Get message',
        tags: ['Messages'],
        parameters: [
            new OA\Parameter(name: 'campaign', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'message', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function show(Campaign $campaign, Message $message): MessageResource
    {
        $this->authorize('view', $campaign);
        $this->authorize('view', $message);

        $message->load('tags');

        return new MessageResource($message);
    }

    #[OA\Put(
        path: '/api/campaigns/{campaign}/messages/{message}',
        operationId: 'messagesUpdate',
        summary: 'Update message',
        tags: ['Messages'],
        parameters: [
            new OA\Parameter(name: 'campaign', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'message', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'template_id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'subject', type: 'string'),
                    new OA\Property(property: 'html_content', type: 'string'),
                    new OA\Property(property: 'status', type: 'string', enum: ['draft', 'ready']),
                    new OA\Property(property: 'scheduled_at', type: 'string', format: 'date-time', nullable: true),
                    new OA\Property(
                        property: 'tag_ids',
                        type: 'array',
                        items: new OA\Items(type: 'string', format: 'uuid'),
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
    public function update(UpdateMessageRequest $request, Campaign $campaign, Message $message): MessageResource
    {
        $this->authorize('view', $campaign);
        $this->authorize('update', $message);

        $data = $request->validated();
        $tagIds = null;
        if (array_key_exists('tag_ids', $data)) {
            $tagIds = $data['tag_ids'];
            unset($data['tag_ids']);
        }

        if ($data !== []) {
            $message->update($data);
        }

        if ($tagIds !== null) {
            $message->tags()->sync($tagIds);
        }

        return new MessageResource($message->fresh(['tags']));
    }

    #[OA\Delete(
        path: '/api/campaigns/{campaign}/messages/{message}',
        operationId: 'messagesDestroy',
        summary: 'Delete message',
        tags: ['Messages'],
        parameters: [
            new OA\Parameter(name: 'campaign', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'message', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'No content'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function destroy(Campaign $campaign, Message $message): JsonResponse
    {
        $this->authorize('view', $campaign);
        $this->authorize('delete', $message);

        $message->delete();

        return response()->json(null, 204);
    }
}

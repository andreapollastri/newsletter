<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\NewsletterReportRequest;
use App\Models\Campaign;
use App\Services\NewsletterReportingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class NewsletterReportController extends Controller
{
    public function __construct(
        protected NewsletterReportingService $reporting,
    ) {}

    #[OA\Get(
        path: '/api/reports/newsletter',
        operationId: 'newsletterReport',
        description: 'Aggregated send, open, click, and bounce statistics for the authenticated user’s campaigns in the date range. When `campaign_id` is set, the report is scoped to that campaign (must belong to the user).',
        summary: 'Newsletter statistics report',
        tags: ['Reports'],
        parameters: [
            new OA\Parameter(name: 'campaign_id', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'start_date', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'end_date', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'filters',
                            properties: [
                                new OA\Property(property: 'campaign_id', type: 'string', format: 'uuid', nullable: true),
                                new OA\Property(property: 'start_date', type: 'string', format: 'date'),
                                new OA\Property(property: 'end_date', type: 'string', format: 'date'),
                            ],
                            type: 'object'
                        ),
                        new OA\Property(
                            property: 'summary',
                            properties: [
                                new OA\Property(property: 'sends', type: 'integer'),
                                new OA\Property(property: 'failed_sends', type: 'integer'),
                                new OA\Property(property: 'opens', type: 'integer'),
                                new OA\Property(property: 'clicks', type: 'integer'),
                                new OA\Property(property: 'bounces', type: 'integer'),
                                new OA\Property(property: 'unique_messages_with_sends', type: 'integer'),
                            ],
                            type: 'object'
                        ),
                        new OA\Property(
                            property: 'by_message',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'message_id', type: 'string', format: 'uuid'),
                                    new OA\Property(property: 'subject', type: 'string'),
                                    new OA\Property(property: 'campaign_id', type: 'string', format: 'uuid'),
                                    new OA\Property(property: 'campaign_name', type: 'string'),
                                    new OA\Property(property: 'sends', type: 'integer'),
                                    new OA\Property(property: 'failed_sends', type: 'integer'),
                                    new OA\Property(property: 'opens', type: 'integer'),
                                    new OA\Property(property: 'clicks', type: 'integer'),
                                ],
                                type: 'object'
                            )
                        ),
                        new OA\Property(
                            property: 'timeseries',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'date', type: 'string', format: 'date'),
                                    new OA\Property(property: 'sends', type: 'integer'),
                                ],
                                type: 'object'
                            )
                        ),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Campaign not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function __invoke(NewsletterReportRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Campaign::class);

        $campaignId = $request->validated('campaign_id');
        if ($campaignId !== null) {
            $campaign = Campaign::query()->findOrFail($campaignId);
            $this->authorize('view', $campaign);
        }

        $start = Carbon::parse($request->validated('start_date'));
        $end = Carbon::parse($request->validated('end_date'));

        $payload = $this->reporting->build($request->user(), $campaignId, $start, $end);

        return response()->json($payload);
    }
}

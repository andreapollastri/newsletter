<?php

namespace App\Mcp\Tools;

use App\Enums\MessageStatus;
use App\Models\Campaign;
use App\Models\Message;
use App\Models\Tag;
use App\Models\Template;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Creates a draft or ready message inside a campaign owned by the user, optionally attaching audience tags.')]
class CreateNewsletterMessageTool extends Tool
{
    public function handle(Request $request): Response
    {
        $user = $request->user();
        if ($user === null) {
            return Response::text('Authentication required.');
        }

        $validated = $request->validate([
            'campaign_id' => ['required', 'uuid', 'exists:campaigns,id'],
            'template_id' => ['required', 'uuid', 'exists:templates,id'],
            'subject' => ['required', 'string', 'max:255'],
            'html_content' => ['required', 'string'],
            'status' => ['required', 'in:draft,ready'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['uuid', 'exists:tags,id'],
        ]);

        $campaign = Campaign::query()->findOrFail($validated['campaign_id']);
        if (! $user->canAccessCampaign($campaign)) {
            return Response::text('You do not have access to that campaign.');
        }

        Template::query()->findOrFail($validated['template_id']);

        $data = [
            'template_id' => $validated['template_id'],
            'subject' => $validated['subject'],
            'html_content' => $validated['html_content'],
            'status' => MessageStatus::from($validated['status']),
        ];

        /** @var Message $message */
        $message = $campaign->messages()->create($data);

        $tagIds = $validated['tag_ids'] ?? [];
        if ($tagIds !== []) {
            $valid = Tag::query()->whereIn('id', $tagIds)->pluck('id');
            $message->tags()->sync($valid);
        }

        $message->load('tags');

        return Response::json([
            'message' => [
                'id' => $message->id,
                'campaign_id' => $message->campaign_id,
                'template_id' => $message->template_id,
                'subject' => $message->subject,
                'status' => $message->status->value,
                'tag_ids' => $message->tags->pluck('id')->values()->all(),
            ],
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'campaign_id' => $schema->string()->format('uuid')->description('Campaign UUID.')->required(),
            'template_id' => $schema->string()->format('uuid')->description('Template UUID.')->required(),
            'subject' => $schema->string()->description('Email subject line.')->required(),
            'html_content' => $schema->string()->description('HTML body for the message.')->required(),
            'status' => $schema->string()->enum(['draft', 'ready'])->description('Message workflow status.')->required(),
            'tag_ids' => $schema->array()
                ->items($schema->string()->format('uuid'))
                ->description('Optional audience tag UUIDs.'),
        ];
    }
}

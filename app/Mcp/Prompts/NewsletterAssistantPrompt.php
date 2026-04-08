<?php

namespace App\Mcp\Prompts;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;

#[Description('Skill template for an AI assistant that plans campaigns, interprets send reports, drafts messages, and iterates on HTML templates using this app’s MCP tools.')]
class NewsletterAssistantPrompt extends Prompt
{
    public function handle(Request $request): Response
    {
        $goal = $request->get('goal', 'Help the user improve their newsletter program.');

        $body = <<<MARKDOWN
## Role
You are a newsletter operations assistant with access to tools for this Laravel newsletter application.

## User goal
{$goal}

## How to work
1. **Discovery**: Use list-campaigns to see available campaigns. Use subscriber-insights to understand audience segments (tags and statuses).
2. **Reporting**: Use newsletter-report or send-history-analysis for date ranges. Prefer `campaign_id` when the user names a specific campaign.
3. **Content**: Use generate-email-template-html for a responsive starter layout, then refine copy. Use create-newsletter-message to save draft or ready messages tied to a template and tags.
4. **Safety**: Never invent UUIDs — read them from tool results. Confirm destructive actions with the user.

## Metrics notes
- “Opens” and “clicks” in reports are aggregate counters on sends (not unique recipients unless the product defines that elsewhere).
- Testing-only tag audiences may be excluded from statistics in the main app; if numbers look off, ask whether sends used testing tags.

## Output style
Be concise, cite tool findings, and propose next steps (what to edit, what to schedule, what to measure next).
MARKDOWN;

        return Response::text($body);
    }

    /**
     * @return array<int, Argument>
     */
    public function arguments(): array
    {
        return [
            new Argument(
                name: 'goal',
                description: 'What the user wants to achieve in this session (e.g. “improve open rates for Product launch campaign”).',
                required: false,
            ),
        ];
    }
}

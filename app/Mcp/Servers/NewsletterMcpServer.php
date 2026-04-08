<?php

namespace App\Mcp\Servers;

use App\Mcp\Prompts\NewsletterAssistantPrompt;
use App\Mcp\Tools\CreateNewsletterMessageTool;
use App\Mcp\Tools\GenerateEmailTemplateHtmlTool;
use App\Mcp\Tools\ListCampaignsTool;
use App\Mcp\Tools\NewsletterReportTool;
use App\Mcp\Tools\SendHistoryAnalysisTool;
use App\Mcp\Tools\SubscriberInsightsTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Tool;

#[Name('Newsletter')]
#[Version('1.0.0')]
#[Instructions(<<<'MD'
This MCP server connects AI clients to this application's newsletter data and actions.

Use the tools to: list campaigns; pull delivery reports and history highlights; inspect subscribers and tags; generate responsive HTML email templates; and create messages in a campaign.

Authenticate with a Sanctum personal access token that includes the `mcp` ability (Bearer token in the Authorization header).

Prefer the `newsletter-assistant` prompt as a reusable “skill” for end-to-end workflows.
MD)]
class NewsletterMcpServer extends Server
{
    /**
     * @var array<int, class-string<Tool>>
     */
    protected array $tools = [
        ListCampaignsTool::class,
        NewsletterReportTool::class,
        SendHistoryAnalysisTool::class,
        SubscriberInsightsTool::class,
        GenerateEmailTemplateHtmlTool::class,
        CreateNewsletterMessageTool::class,
    ];

    /**
     * @var array<int, class-string<Server\Resource>>
     */
    protected array $resources = [
        //
    ];

    /**
     * @var array<int, class-string<Prompt>>
     */
    protected array $prompts = [
        NewsletterAssistantPrompt::class,
    ];
}

<?php

use App\Mcp\Servers\NewsletterMcpServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp/newsletter', NewsletterMcpServer::class)
    ->middleware(['auth:sanctum', 'abilities:mcp']);

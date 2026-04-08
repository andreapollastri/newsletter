<?php

use App\Http\Controllers\Api\CampaignController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\NewsletterReportController;
use App\Http\Controllers\Api\SubscriberController;
use App\Http\Controllers\Api\TagController;
use App\Http\Controllers\Api\TemplateController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'abilities:api'])->group(function (): void {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::get('/reports/newsletter', NewsletterReportController::class);

    Route::apiResource('tags', TagController::class);
    Route::apiResource('subscribers', SubscriberController::class);
    Route::apiResource('templates', TemplateController::class);

    Route::scopeBindings()->group(function (): void {
        Route::apiResource('campaigns', CampaignController::class);
        Route::apiResource('campaigns.messages', MessageController::class);
    });
});

<?php

use App\Http\Controllers\SubscribeController;
use App\Http\Controllers\TrackingController;
use Illuminate\Support\Facades\Route;

// Subscription routes
Route::get('/subscribe', [SubscribeController::class, 'showForm'])->name('subscribe.form');
Route::post('/subscribe', [SubscribeController::class, 'subscribe'])->name('subscribe.store');
Route::get('/subscribe/confirm/{token}', [SubscribeController::class, 'confirm'])->name('subscribe.confirm');
Route::get('/unsubscribe/{subscriber}', [SubscribeController::class, 'unsubscribe'])->name('unsubscribe');
Route::post('/unsubscribe/{subscriber}/confirm', [SubscribeController::class, 'confirmUnsubscribe'])->name('unsubscribe.confirm');

// Tracking routes
Route::get('/track/open/{messageSend}', [TrackingController::class, 'open'])->name('tracking.open');
Route::get('/track/click/{messageSend}', [TrackingController::class, 'click'])->name('tracking.click');

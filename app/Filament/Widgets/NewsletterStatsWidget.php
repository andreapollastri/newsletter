<?php

namespace App\Filament\Widgets;

use App\Enums\SubscriberStatus;
use App\Models\Bounce;
use App\Models\Message;
use App\Models\MessageSend;
use App\Models\Subscriber;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class NewsletterStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $totalSubscribers = Subscriber::where('status', SubscriberStatus::Confirmed)->count();
        $sentThisMonth = Message::where('status', \App\Enums\MessageStatus::Sent)
            ->whereMonth('sent_at', now()->month)
            ->whereYear('sent_at', now()->year)
            ->count();

        // Conteggio invii totali del mese
        $sendsThisMonth = MessageSend::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->whereNotNull('sent_at')
            ->count();

        // Conteggio univoco aperture (messaggi aperti almeno una volta)
        $uniqueOpensThisMonth = MessageSend::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->whereNotNull('sent_at')
            ->where('opens_count', '>', 0)
            ->count();

        // Conteggio univoco click (messaggi cliccati almeno una volta)
        $uniqueClicksThisMonth = MessageSend::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->whereNotNull('sent_at')
            ->where('clicks_count', '>', 0)
            ->count();

        $openRate = $sendsThisMonth > 0
            ? round(($uniqueOpensThisMonth / $sendsThisMonth) * 100, 1).'%'
            : 'N/A';

        $clickRate = $sendsThisMonth > 0
            ? round(($uniqueClicksThisMonth / $sendsThisMonth) * 100, 1).'%'
            : 'N/A';

        $bouncesThisMonth = Bounce::whereMonth('detected_at', now()->month)
            ->whereYear('detected_at', now()->year)
            ->count();

        return [
            Stat::make(__('Total Subscribers'), $totalSubscribers)
                ->icon(Heroicon::Users)
                ->color('success'),

            Stat::make(__('Messages Sent (month)'), $sentThisMonth)
                ->icon(Heroicon::EnvelopeOpen)
                ->color('info'),

            Stat::make(__('Total Emails Sent (month)'), $sendsThisMonth)
                ->icon(Heroicon::PaperAirplane)
                ->color('success'),

            Stat::make(__('Open Rate (month)'), $openRate)
                ->description(__('Opens').': '.$uniqueOpensThisMonth)
                ->icon(Heroicon::Eye)
                ->color('warning'),

            Stat::make(__('Click Rate (month)'), $clickRate)
                ->description(__('Clicks').': '.$uniqueClicksThisMonth)
                ->icon(Heroicon::CursorArrowRays)
                ->color('info'),

            Stat::make(__('Bounces (month)'), $bouncesThisMonth)
                ->icon(Heroicon::ExclamationTriangle)
                ->color('danger'),
        ];
    }
}

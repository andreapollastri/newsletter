<?php

namespace App\Filament\Widgets;

use App\Enums\MessageStatus;
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
        $sentThisMonth = Message::where('status', MessageStatus::Sent)
            ->whereMonth('sent_at', now()->month)
            ->whereYear('sent_at', now()->year)
            ->count();

        $sendsThisMonth = MessageSend::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->whereNotNull('sent_at')
            ->count();
        $opensThisMonth = MessageSend::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->whereNotNull('sent_at')
            ->sum('opens_count');

        $openRate = $sendsThisMonth > 0
            ? round(($opensThisMonth / $sendsThisMonth) * 100, 1).'%'
            : 'N/A';

        $bouncesThisMonth = Bounce::whereMonth('detected_at', now()->month)
            ->whereYear('detected_at', now()->year)
            ->count();

        return [
            Stat::make('Subscribers Totali', $totalSubscribers)
                ->icon(Heroicon::Users)
                ->color('success'),

            Stat::make('Messaggi Inviati (mese)', $sentThisMonth)
                ->icon(Heroicon::EnvelopeOpen)
                ->color('info'),

            Stat::make('Tasso Apertura (mese)', $openRate)
                ->icon(Heroicon::Eye)
                ->color('warning'),

            Stat::make('Bounces (mese)', $bouncesThisMonth)
                ->icon(Heroicon::ExclamationTriangle)
                ->color('danger'),
        ];
    }
}

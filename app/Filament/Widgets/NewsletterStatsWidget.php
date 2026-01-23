<?php

namespace App\Filament\Widgets;

use App\Enums\SubscriberStatus;
use App\Models\Bounce;
use App\Models\Message;
use App\Models\MessageSend;
use App\Models\Subscriber;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class NewsletterStatsWidget extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected function getStats(): array
    {
        $period = $this->pageFilters['period'] ?? '1m';
        $startDate = $this->getStartDateForPeriod($period);

        $newSubscribers = Subscriber::where('status', SubscriberStatus::Confirmed)
            ->when($startDate, fn (Builder $query) => $query->where('confirmed_at', '>=', $startDate))
            ->count();

        $totalSubscribers = Subscriber::where('status', SubscriberStatus::Confirmed)->count();

        $unsubscribesCount = Subscriber::where('status', SubscriberStatus::Unsubscribed)
            ->when($startDate, fn (Builder $query) => $query->where('unsubscribed_at', '>=', $startDate))
            ->count();

        $sentCount = Message::where('status', \App\Enums\MessageStatus::Sent)
            ->when($startDate, fn (Builder $query) => $query->where('sent_at', '>=', $startDate))
            ->count();

        $sendsCount = MessageSend::when($startDate, fn (Builder $query) => $query->where('sent_at', '>=', $startDate))
            ->whereNotNull('sent_at')
            ->count();

        $uniqueOpensCount = MessageSend::when($startDate, fn (Builder $query) => $query->where('sent_at', '>=', $startDate))
            ->whereNotNull('sent_at')
            ->where('opens_count', '>', 0)
            ->count();

        $uniqueClicksCount = MessageSend::when($startDate, fn (Builder $query) => $query->where('sent_at', '>=', $startDate))
            ->whereNotNull('sent_at')
            ->where('clicks_count', '>', 0)
            ->count();

        $messagesWithLinks = DB::table('message_clicks')
            ->join('message_sends', 'message_clicks.message_send_id', '=', 'message_sends.id')
            ->when($startDate, fn ($query) => $query->where('message_sends.sent_at', '>=', $startDate))
            ->whereNotNull('message_sends.sent_at')
            ->distinct()
            ->pluck('message_sends.message_id');

        $sendsWithLinksCount = MessageSend::when($startDate, fn (Builder $query) => $query->where('sent_at', '>=', $startDate))
            ->whereNotNull('sent_at')
            ->whereIn('message_id', $messagesWithLinks)
            ->count();

        $openRate = $sendsCount > 0
            ? round(($uniqueOpensCount / $sendsCount) * 100, 1).'%'
            : 'N/A';

        $clickRate = $sendsWithLinksCount > 0
            ? round(($uniqueClicksCount / $sendsWithLinksCount) * 100, 1).'%'
            : 'N/A';

        $bouncesCount = Bounce::when($startDate, fn (Builder $query) => $query->where('detected_at', '>=', $startDate))
            ->count();

        $periodLabel = $this->getPeriodLabel($period);

        return [
            Stat::make(__('New Subscribers').' ('.$periodLabel.')', $newSubscribers)
                ->description(__('Total subscribers: :count', ['count' => $totalSubscribers]))
                ->icon(Heroicon::Users)
                ->color('success'),

            Stat::make(__('Total Unsubscribes').' ('.$periodLabel.')', $unsubscribesCount)
                ->icon(Heroicon::UserMinus)
                ->color('danger'),

            Stat::make(__('Total Emails Sent').' ('.$periodLabel.')', $sendsCount)
                ->description(__('Total messages: :count', ['count' => $sentCount]))
                ->icon(Heroicon::PaperAirplane)
                ->color('success'),

            Stat::make(__('Open Rate').' ('.$periodLabel.')', $openRate)
                ->description(__('Opens').': '.$uniqueOpensCount)
                ->icon(Heroicon::Eye)
                ->color('warning'),

            Stat::make(__('Click Rate').' ('.$periodLabel.')', $clickRate)
                ->description(__('Clicks').': '.$uniqueClicksCount.' / '.__('Emails with links').': '.$sendsWithLinksCount)
                ->icon(Heroicon::CursorArrowRays)
                ->color('info'),

            Stat::make(__('Bounces').' ('.$periodLabel.')', $bouncesCount)
                ->icon(Heroicon::ExclamationTriangle)
                ->color('danger'),
        ];
    }

    protected function getStartDateForPeriod(?string $period): ?\Illuminate\Support\Carbon
    {
        return match ($period) {
            '24h' => now()->subHours(24),
            '7d' => now()->subDays(7),
            '1m' => now()->subMonth(),
            '6m' => now()->subMonths(6),
            '1y' => now()->subYear(),
            default => now()->subMonth(),
        };
    }

    protected function getPeriodLabel(string $period): string
    {
        return match ($period) {
            '24h' => __('Last 24 hours'),
            '7d' => __('Last 7 days'),
            '1m' => __('Last month'),
            '6m' => __('Last 6 months'),
            '1y' => __('Last year'),
            default => __('Last month'),
        };
    }
}

<?php

namespace App\Models;

use App\Enums\MessageStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Message extends Model
{
    /** @use HasFactory<\Database\Factories\MessageFactory> */
    use HasFactory;

    use HasUuids;

    protected $fillable = [
        'campaign_id',
        'template_id',
        'subject',
        'html_content',
        'status',
        'scheduled_at',
        'sent_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => MessageStatus::class,
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * @return BelongsTo<Template, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    /**
     * @return HasMany<MessageSend, $this>
     */
    public function sends(): HasMany
    {
        return $this->hasMany(MessageSend::class);
    }

    /**
     * Boot the model.
     * Note: MessageSend records are automatically deleted via cascadeOnDelete in the migration.
     * Any jobs in the queue will check if MessageSend exists before processing.
     */
    protected static function booted(): void
    {
        // MessageSend deletion is handled by database cascade
        // Jobs will gracefully skip if MessageSend no longer exists
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /**
     * Calculate estimated time to complete sending based on rate limits.
     */
    public function getEstimatedSendTime(): ?string
    {
        if ($this->status !== MessageStatus::Sending) {
            return null;
        }

        // Count pending sends
        $pendingSends = $this->sends()
            ->whereNull('sent_at')
            ->whereNull('failed_at')
            ->count();

        if ($pendingSends === 0) {
            return __('Completing...');
        }

        // Get rate limits
        $perMinute = (int) config('newsletter.rate_limits.per_minute', 0);
        $perHour = (int) config('newsletter.rate_limits.per_hour', 0);
        $perDay = (int) config('newsletter.rate_limits.per_day', 0);

        // If no limits, send is immediate
        if ($perMinute === 0 && $perHour === 0 && $perDay === 0) {
            return __('Estimated send time: immediate');
        }

        // Calculate minutes needed based on each limit
        $minutesNeeded = [];

        if ($perMinute > 0) {
            $minutesNeeded[] = ceil($pendingSends / $perMinute);
        }

        if ($perHour > 0) {
            $minutesNeeded[] = ceil($pendingSends / $perHour) * 60;
        }

        if ($perDay > 0) {
            $minutesNeeded[] = ceil($pendingSends / $perDay) * 1440;
        }

        // Take the maximum (most restrictive limit)
        $totalMinutes = max($minutesNeeded);

        return __('Estimated send time: :time', [
            'time' => $this->formatEstimatedTime($totalMinutes),
        ]);
    }

    /**
     * Format estimated time in a human-readable way.
     */
    protected function formatEstimatedTime(float $minutes): string
    {
        if ($minutes < 1) {
            return __('immediate');
        }

        if ($minutes < 60) {
            $mins = (int) ceil($minutes);

            return trans_choice('{1} :count minute|[2,*] :count minutes', $mins, ['count' => $mins]);
        }

        if ($minutes < 1440) {
            $hours = (int) ceil($minutes / 60);

            return trans_choice('{1} :count hour|[2,*] :count hours', $hours, ['count' => $hours]);
        }

        $days = (int) ceil($minutes / 1440);

        return trans_choice('{1} :count day|[2,*] :count days', $days, ['count' => $days]);
    }
}

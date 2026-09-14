<?php

namespace App\Models;

use App\Enums\MessageStatus;
use App\Enums\SubscriberStatus;
use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class Message extends Model
{
    /** @use HasFactory<MessageFactory> */
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
     * Tags whose subscribers must not receive this message.
     *
     * @return BelongsToMany<Tag, $this>
     */
    public function excludedTags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'message_excluded_tag');
    }

    /**
     * Confirmed subscribers who should receive this message.
     *
     * Include tags match any selected tag (OR). Excluded tags remove anyone
     * who has at least one of those tags, including when include tags are empty.
     *
     * @return Builder<Subscriber>
     */
    public function targetSubscribers(): Builder
    {
        $this->loadMissing(['tags', 'excludedTags']);

        $query = Subscriber::query()->where('status', SubscriberStatus::Confirmed);

        if ($this->tags->isNotEmpty()) {
            $tagIds = $this->tags->pluck('id');
            $query->whereHas('tags', fn (Builder $q) => $q->whereIn('tags.id', $tagIds));
        }

        if ($this->excludedTags->isNotEmpty()) {
            $excludedIds = $this->excludedTags->pluck('id');
            $query->whereDoesntHave('tags', fn (Builder $q) => $q->whereIn('tags.id', $excludedIds));
        }

        return $query;
    }

    /**
     * Labels for the audience column (include tags plus "not {tag}" exclusions).
     *
     * @return list<string>
     */
    public function audienceLabels(): array
    {
        $this->loadMissing(['tags', 'excludedTags']);

        $included = $this->tags->pluck('name');
        $excluded = $this->excludedTagLabels();

        if ($included->isEmpty() && $excluded->isEmpty()) {
            return [__('All subscribers')];
        }

        if ($included->isEmpty()) {
            return $excluded->all();
        }

        return $included->concat($excluded)->values()->all();
    }

    /**
     * @return Collection<int, string>
     */
    public function excludedTagLabels(): Collection
    {
        $this->loadMissing('excludedTags');

        return $this->excludedTags
            ->pluck('name')
            ->map(fn (string $name): string => __('not :tag', ['tag' => $name]))
            ->values();
    }

    /**
     * @param  list<string>|array<int, string>  $includedIds
     * @param  list<string>|array<int, string>  $excludedIds
     */
    public static function tagsOverlap(array $includedIds, array $excludedIds): bool
    {
        return array_intersect(
            array_map(strval(...), $includedIds),
            array_map(strval(...), $excludedIds),
        ) !== [];
    }

    /**
     * Exclude select aggregate aliases from replication (they are not database columns).
     *
     * @param  array<string>|null  $except
     */
    public function replicate(?array $except = null): static
    {
        return parent::replicate(array_merge($except ?? [], [
            'emails_sent_count',
            'opens_sum',
        ]));
    }

    /**
     * Messages whose audience is defined only by "testing" tags (at least one tag, all testing).
     * These sends are excluded from dashboard statistics and removed from send history after completion.
     */
    public function hasTestingAudienceOnly(): bool
    {
        if ($this->relationLoaded('tags')) {
            return $this->tags->isNotEmpty()
                && $this->tags->every(fn (Tag $t): bool => $t->is_testing);
        }

        return $this->tags()->exists()
            && ! $this->tags()->where('is_testing', false)->exists();
    }

    /**
     * Limit to messages that count toward newsletter statistics (excludes testing-tag-only audiences).
     *
     * @param  Builder<Message>  $query
     * @return Builder<Message>
     */
    public function scopeForStatistics(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->whereDoesntHave('tags')
                ->orWhereHas('tags', fn (Builder $tq) => $tq->where('is_testing', false));
        });
    }

    /**
     * Hide sent messages whose audience consists entirely of testing tags.
     * Non-sent testing messages remain visible for management.
     *
     * @param  Builder<Message>  $query
     * @return Builder<Message>
     */
    public function scopeExcludeSentTestingOnly(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->where('status', '!=', MessageStatus::Sent)
                ->orWhereDoesntHave('tags')
                ->orWhereHas('tags', fn (Builder $tq) => $tq->where('is_testing', false));
        });
    }

    /**
     * After a testing-audience send completes, remove per-recipient rows so history and subscriber timelines stay clean.
     */
    public function purgeSendsForTestingAudience(): void
    {
        if (! $this->hasTestingAudienceOnly()) {
            return;
        }

        DB::transaction(function (): void {
            $sendIds = $this->sends()->pluck('id');
            if ($sendIds->isEmpty()) {
                return;
            }

            Bounce::whereIn('message_send_id', $sendIds)->delete();
            $this->sends()->delete();
        });
    }

    /**
     * Estimate time to drain pending sends using `config('newsletter.rate_limits')`
     * (same values as `NEWSLETTER_RATE_LIMIT_PER_MINUTE`, `_PER_HOUR`, `_PER_DAY`).
     * Takes the maximum of per-window durations when multiple caps are enabled.
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

        /** @var array{per_minute: int, per_hour: int, per_day: int} $limits */
        $limits = config('newsletter.rate_limits', [
            'per_minute' => 0,
            'per_hour' => 0,
            'per_day' => 0,
        ]);

        $perMinute = (int) ($limits['per_minute'] ?? 0);
        $perHour = (int) ($limits['per_hour'] ?? 0);
        $perDay = (int) ($limits['per_day'] ?? 0);

        // If no limits, send is immediate
        if ($perMinute === 0 && $perHour === 0 && $perDay === 0) {
            return __('Estimated send time: immediate');
        }

        // Minutes needed if each limit applied alone (same knobs as EmailRateLimiter / .env).
        $minutesNeeded = [];

        if ($perMinute > 0) {
            $minutesNeeded[] = (int) ceil($pendingSends / $perMinute);
        }

        if ($perHour > 0) {
            $minutesNeeded[] = (int) ceil(($pendingSends * 60) / $perHour);
        }

        if ($perDay > 0) {
            $minutesNeeded[] = (int) ceil(($pendingSends * 1440) / $perDay);
        }

        // Most restrictive window dominates total duration.
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

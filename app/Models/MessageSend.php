<?php

namespace App\Models;

use Database\Factories\MessageSendFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MessageSend extends Model
{
    /** @use HasFactory<MessageSendFactory> */
    use HasFactory;

    use HasUuids;

    protected $fillable = [
        'message_id',
        'subscriber_id',
        'sent_at',
        'failed_at',
        'error_message',
        'opens_count',
        'clicks_count',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
            'opens_count' => 'integer',
            'clicks_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Message, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    /**
     * @return BelongsTo<Subscriber, $this>
     */
    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(Subscriber::class);
    }

    /**
     * @return HasMany<MessageOpen, $this>
     */
    public function opens(): HasMany
    {
        return $this->hasMany(MessageOpen::class);
    }

    /**
     * @return HasMany<MessageClick, $this>
     */
    public function clicks(): HasMany
    {
        return $this->hasMany(MessageClick::class);
    }

    /**
     * @return HasOne<Bounce, $this>
     */
    public function bounce(): HasOne
    {
        return $this->hasOne(Bounce::class);
    }

    /**
     * Failed deliveries cannot have been opened or clicked by the recipient.
     *
     * Bounce NDRs typically include the original HTML, so mail clients and scanners
     * load the tracking pixel and would otherwise inflate open/click counts.
     */
    public function discardEngagementTracking(): void
    {
        $this->opens()->delete();
        $this->clicks()->delete();
        $this->forceFill([
            'opens_count' => 0,
            'clicks_count' => 0,
        ])->save();
    }

    /**
     * Sends that count toward newsletter statistics (excludes testing-tag-only message audiences).
     *
     * @param  Builder<MessageSend>  $query
     * @return Builder<MessageSend>
     */
    public function scopeForStatistics(Builder $query): Builder
    {
        return $query->whereHas('message', fn (Builder $mq) => $mq->forStatistics());
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SendLog extends Model
{
    /** @use HasFactory<\Database\Factories\SendLogFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'message_id',
        'subscriber_id',
        'status',
        'queued_at',
        'sent_at',
        'error_message',
        'message_id_header',
        'open_count',
        'click_count',
        'first_opened_at',
        'last_opened_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'queued_at' => 'datetime',
            'sent_at' => 'datetime',
            'first_opened_at' => 'datetime',
            'last_opened_at' => 'datetime',
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
     * @return HasMany<LinkClick, $this>
     */
    public function clicks(): HasMany
    {
        return $this->hasMany(LinkClick::class);
    }

    /**
     * Check if the email was sent successfully.
     */
    public function wasSent(): bool
    {
        return $this->status === 'sent';
    }

    /**
     * Check if the email failed.
     */
    public function hasFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if the email bounced.
     */
    public function hasBounced(): bool
    {
        return $this->status === 'bounced';
    }

    /**
     * Mark as sent.
     */
    public function markAsSent(?string $messageIdHeader = null): void
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
            'message_id_header' => $messageIdHeader,
        ]);
    }

    /**
     * Mark as failed.
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Mark as bounced.
     */
    public function markAsBounced(?string $errorMessage = null): void
    {
        $this->update([
            'status' => 'bounced',
            'error_message' => $errorMessage,
        ]);

        // Also mark the subscriber as bounced
        $this->subscriber->markAsBounced();
    }

    /**
     * Record an open event.
     */
    public function recordOpen(?string $ipAddress = null, ?string $userAgent = null): MessageOpen
    {
        $this->increment('open_count');

        if ($this->first_opened_at === null) {
            $this->update(['first_opened_at' => now()]);
        }

        $this->update(['last_opened_at' => now()]);

        // Update message stats
        if ($this->open_count === 1) {
            $this->message->incrementOpenedCount();
        }

        return $this->opens()->create([
            'opened_at' => now(),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }

    /**
     * Record a click event.
     */
    public function recordClick(string $url, ?string $ipAddress = null, ?string $userAgent = null): LinkClick
    {
        $this->increment('click_count');

        // Update message stats (only first click per send log)
        if ($this->click_count === 1) {
            $this->message->incrementClickedCount();
        }

        return $this->clicks()->create([
            'original_url' => $url,
            'url_hash' => hash('sha256', $url),
            'clicked_at' => now(),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }
}

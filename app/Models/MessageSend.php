<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MessageSend extends Model
{
    /** @use HasFactory<\Database\Factories\MessageSendFactory> */
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
}

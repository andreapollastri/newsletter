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
}

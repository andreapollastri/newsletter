<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageClick extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'message_send_id',
        'url',
        'clicked_at',
        'ip_address',
        'user_agent',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'clicked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<MessageSend, $this>
     */
    public function messageSend(): BelongsTo
    {
        return $this->belongsTo(MessageSend::class);
    }
}

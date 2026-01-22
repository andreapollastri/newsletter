<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bounce extends Model
{
    use HasUuids;

    protected $fillable = [
        'message_send_id',
        'email',
        'type',
        'raw_message',
        'detected_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'detected_at' => 'datetime',
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

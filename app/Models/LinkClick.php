<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LinkClick extends Model
{
    /** @use HasFactory<\Database\Factories\LinkClickFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'send_log_id',
        'original_url',
        'url_hash',
        'clicked_at',
        'ip_address',
        'user_agent',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'clicked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<SendLog, $this>
     */
    public function sendLog(): BelongsTo
    {
        return $this->belongsTo(SendLog::class);
    }
}

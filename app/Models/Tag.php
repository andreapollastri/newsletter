<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    /** @use HasFactory<\Database\Factories\TagFactory> */
    use HasFactory;

    use HasUuids;

    protected $fillable = [
        'name',
        'color',
    ];

    /**
     * @return BelongsToMany<Subscriber, $this>
     */
    public function subscribers(): BelongsToMany
    {
        return $this->belongsToMany(Subscriber::class)->withTimestamps();
    }

    /**
     * @return BelongsToMany<Message, $this>
     */
    public function messages(): BelongsToMany
    {
        return $this->belongsToMany(Message::class);
    }
}

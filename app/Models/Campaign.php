<?php

namespace App\Models;

use Database\Factories\CampaignFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Campaign extends Model
{
    /** @use HasFactory<CampaignFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'description',
    ];

    protected static function booted(): void
    {
        static::saving(function (Campaign $campaign): void {
            if (blank($campaign->slug) && filled($campaign->name)) {
                $campaign->slug = Str::slug($campaign->name);
            }

            $campaign->slug = self::ensureUniqueSlug((string) $campaign->slug, $campaign->id);
        });
    }

    /**
     * Ensure the slug is unique among campaigns (suffix with -2, -3, … when needed).
     */
    private static function ensureUniqueSlug(string $slug, ?string $ignoreId = null): string
    {
        $base = $slug !== '' ? $slug : 'campaign';
        $candidate = $base;
        $n = 2;

        while (static::query()
            ->where('slug', $candidate)
            ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists()) {
            $candidate = $base.'-'.$n;
            $n++;
        }

        return $candidate;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }
}

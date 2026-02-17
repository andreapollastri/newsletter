<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class EmailRateLimiter
{
    protected const CACHE_PREFIX = 'email_rate_limit:';

    protected const MINUTE_KEY = self::CACHE_PREFIX.'minute';

    protected const HOUR_KEY = self::CACHE_PREFIX.'hour';

    protected const DAY_KEY = self::CACHE_PREFIX.'day';

    /**
     * Check if we can send an email based on all rate limits.
     * Returns an array with ['allowed' => bool, 'retry_after' => int|null]
     */
    public function attempt(): array
    {
        $perMinute = (int) config('newsletter.rate_limits.per_minute', 0);
        $perHour = (int) config('newsletter.rate_limits.per_hour', 0);
        $perDay = (int) config('newsletter.rate_limits.per_day', 0);

        // Check daily limit first (highest priority)
        if ($perDay > 0) {
            $dailyCount = $this->getCount(self::DAY_KEY);
            if ($dailyCount >= $perDay) {
                $ttl = Cache::get(self::DAY_KEY.':ttl');
                $retryAfter = $ttl ? now()->diffInSeconds($ttl) : 86400;

                return ['allowed' => false, 'retry_after' => $retryAfter, 'limit_type' => 'daily'];
            }
        }

        // Check hourly limit (second priority)
        if ($perHour > 0) {
            $hourlyCount = $this->getCount(self::HOUR_KEY);
            if ($hourlyCount >= $perHour) {
                $ttl = Cache::get(self::HOUR_KEY.':ttl');
                $retryAfter = $ttl ? now()->diffInSeconds($ttl) : 3600;

                return ['allowed' => false, 'retry_after' => $retryAfter, 'limit_type' => 'hourly'];
            }
        }

        // Check per-minute limit (lowest priority)
        if ($perMinute > 0) {
            $minuteCount = $this->getCount(self::MINUTE_KEY);
            if ($minuteCount >= $perMinute) {
                $ttl = Cache::get(self::MINUTE_KEY.':ttl');
                $retryAfter = $ttl ? now()->diffInSeconds($ttl) : 60;

                return ['allowed' => false, 'retry_after' => $retryAfter, 'limit_type' => 'per_minute'];
            }
        }

        // All checks passed, increment all counters
        $this->increment();

        return ['allowed' => true, 'retry_after' => null, 'limit_type' => null];
    }

    /**
     * Increment all rate limit counters.
     */
    protected function increment(): void
    {
        // Increment minute counter (expires after 60 seconds)
        $this->incrementCounter(self::MINUTE_KEY, 60);

        // Increment hour counter (expires after 3600 seconds)
        $this->incrementCounter(self::HOUR_KEY, 3600);

        // Increment day counter (expires after 86400 seconds)
        $this->incrementCounter(self::DAY_KEY, 86400);
    }

    /**
     * Increment a specific counter with TTL.
     */
    protected function incrementCounter(string $key, int $ttl): void
    {
        if (! Cache::has($key)) {
            Cache::put($key, 0, $ttl);
            Cache::put($key.':ttl', now()->addSeconds($ttl), $ttl);
        }

        Cache::increment($key);
    }

    /**
     * Get the current count for a specific key.
     */
    protected function getCount(string $key): int
    {
        return (int) Cache::get($key, 0);
    }

    /**
     * Get current statistics for all rate limits.
     */
    public function getStats(): array
    {
        return [
            'per_minute' => [
                'limit' => (int) config('newsletter.rate_limits.per_minute', 0),
                'current' => $this->getCount(self::MINUTE_KEY),
                'resets_at' => Cache::get(self::MINUTE_KEY.':ttl'),
            ],
            'per_hour' => [
                'limit' => (int) config('newsletter.rate_limits.per_hour', 0),
                'current' => $this->getCount(self::HOUR_KEY),
                'resets_at' => Cache::get(self::HOUR_KEY.':ttl'),
            ],
            'per_day' => [
                'limit' => (int) config('newsletter.rate_limits.per_day', 0),
                'current' => $this->getCount(self::DAY_KEY),
                'resets_at' => Cache::get(self::DAY_KEY.':ttl'),
            ],
        ];
    }

    /**
     * Reset all rate limit counters.
     */
    public function reset(): void
    {
        Cache::forget(self::MINUTE_KEY);
        Cache::forget(self::MINUTE_KEY.':ttl');
        Cache::forget(self::HOUR_KEY);
        Cache::forget(self::HOUR_KEY.':ttl');
        Cache::forget(self::DAY_KEY);
        Cache::forget(self::DAY_KEY.':ttl');
    }
}

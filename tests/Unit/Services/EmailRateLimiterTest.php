<?php

namespace Tests\Unit\Services;

use App\Services\EmailRateLimiter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class EmailRateLimiterTest extends TestCase
{
    use RefreshDatabase;

    protected EmailRateLimiter $rateLimiter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rateLimiter = new EmailRateLimiter;

        // Reset cache before each test
        Cache::flush();
    }

    public function test_allows_email_when_no_limits_configured(): void
    {
        Config::set('newsletter.rate_limits.per_minute', 0);
        Config::set('newsletter.rate_limits.per_hour', 0);
        Config::set('newsletter.rate_limits.per_day', 0);

        $result = $this->rateLimiter->attempt();

        $this->assertTrue($result['allowed']);
        $this->assertNull($result['retry_after']);
    }

    public function test_blocks_email_when_per_minute_limit_reached(): void
    {
        Config::set('newsletter.rate_limits.per_minute', 2);
        Config::set('newsletter.rate_limits.per_hour', 0);
        Config::set('newsletter.rate_limits.per_day', 0);

        // First two should succeed
        $result1 = $this->rateLimiter->attempt();
        $this->assertTrue($result1['allowed']);

        $result2 = $this->rateLimiter->attempt();
        $this->assertTrue($result2['allowed']);

        // Third should be blocked
        $result3 = $this->rateLimiter->attempt();
        $this->assertFalse($result3['allowed']);
        $this->assertNotNull($result3['retry_after']);
        $this->assertEquals('per_minute', $result3['limit_type']);
    }

    public function test_blocks_email_when_hourly_limit_reached(): void
    {
        Config::set('newsletter.rate_limits.per_minute', 0);
        Config::set('newsletter.rate_limits.per_hour', 2);
        Config::set('newsletter.rate_limits.per_day', 0);

        // First two should succeed
        $result1 = $this->rateLimiter->attempt();
        $this->assertTrue($result1['allowed']);

        $result2 = $this->rateLimiter->attempt();
        $this->assertTrue($result2['allowed']);

        // Third should be blocked
        $result3 = $this->rateLimiter->attempt();
        $this->assertFalse($result3['allowed']);
        $this->assertEquals('hourly', $result3['limit_type']);
    }

    public function test_blocks_email_when_daily_limit_reached(): void
    {
        Config::set('newsletter.rate_limits.per_minute', 0);
        Config::set('newsletter.rate_limits.per_hour', 0);
        Config::set('newsletter.rate_limits.per_day', 2);

        // First two should succeed
        $result1 = $this->rateLimiter->attempt();
        $this->assertTrue($result1['allowed']);

        $result2 = $this->rateLimiter->attempt();
        $this->assertTrue($result2['allowed']);

        // Third should be blocked
        $result3 = $this->rateLimiter->attempt();
        $this->assertFalse($result3['allowed']);
        $this->assertEquals('daily', $result3['limit_type']);
    }

    public function test_daily_limit_takes_precedence_over_others(): void
    {
        Config::set('newsletter.rate_limits.per_minute', 100);
        Config::set('newsletter.rate_limits.per_hour', 100);
        Config::set('newsletter.rate_limits.per_day', 1);

        // First should succeed
        $result1 = $this->rateLimiter->attempt();
        $this->assertTrue($result1['allowed']);

        // Second should be blocked by daily limit
        $result2 = $this->rateLimiter->attempt();
        $this->assertFalse($result2['allowed']);
        $this->assertEquals('daily', $result2['limit_type']);
    }

    public function test_hourly_limit_takes_precedence_over_per_minute(): void
    {
        Config::set('newsletter.rate_limits.per_minute', 100);
        Config::set('newsletter.rate_limits.per_hour', 1);
        Config::set('newsletter.rate_limits.per_day', 0);

        // First should succeed
        $result1 = $this->rateLimiter->attempt();
        $this->assertTrue($result1['allowed']);

        // Second should be blocked by hourly limit
        $result2 = $this->rateLimiter->attempt();
        $this->assertFalse($result2['allowed']);
        $this->assertEquals('hourly', $result2['limit_type']);
    }

    public function test_get_stats_returns_correct_information(): void
    {
        Config::set('newsletter.rate_limits.per_minute', 10);
        Config::set('newsletter.rate_limits.per_hour', 100);
        Config::set('newsletter.rate_limits.per_day', 1000);

        // Make some attempts
        $this->rateLimiter->attempt();
        $this->rateLimiter->attempt();

        $stats = $this->rateLimiter->getStats();

        $this->assertEquals(10, $stats['per_minute']['limit']);
        $this->assertEquals(2, $stats['per_minute']['current']);

        $this->assertEquals(100, $stats['per_hour']['limit']);
        $this->assertEquals(2, $stats['per_hour']['current']);

        $this->assertEquals(1000, $stats['per_day']['limit']);
        $this->assertEquals(2, $stats['per_day']['current']);
    }

    public function test_reset_clears_all_counters(): void
    {
        Config::set('newsletter.rate_limits.per_minute', 10);
        Config::set('newsletter.rate_limits.per_hour', 100);
        Config::set('newsletter.rate_limits.per_day', 1000);

        // Make some attempts
        $this->rateLimiter->attempt();
        $this->rateLimiter->attempt();

        // Verify counters are set
        $statsBefore = $this->rateLimiter->getStats();
        $this->assertEquals(2, $statsBefore['per_minute']['current']);

        // Reset
        $this->rateLimiter->reset();

        // Verify counters are cleared
        $statsAfter = $this->rateLimiter->getStats();
        $this->assertEquals(0, $statsAfter['per_minute']['current']);
        $this->assertEquals(0, $statsAfter['per_hour']['current']);
        $this->assertEquals(0, $statsAfter['per_day']['current']);
    }
}

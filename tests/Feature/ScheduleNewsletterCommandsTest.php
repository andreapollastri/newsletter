<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class ScheduleNewsletterCommandsTest extends TestCase
{
    public function test_newsletter_recovery_and_bounce_commands_are_scheduled(): void
    {
        /** @var Schedule $schedule */
        $schedule = $this->app->make(Schedule::class);

        $commands = collect($schedule->events())
            ->map(fn ($event): string => $event->command ?? $event->description ?? '')
            ->implode(' ');

        $this->assertStringContainsString('newsletter:send-scheduled', $commands);
        $this->assertStringContainsString('newsletter:process-pending', $commands);
        $this->assertStringContainsString('newsletter:process-bounces', $commands);
    }
}

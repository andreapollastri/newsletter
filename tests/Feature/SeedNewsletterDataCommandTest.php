<?php

namespace Tests\Feature;

use App\Enums\MessageStatus;
use App\Enums\UserRole;
use App\Models\Message;
use App\Models\MessageOpen;
use App\Models\MessageSend;
use App\Models\Subscriber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeedNewsletterDataCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_a_demo_dataset_with_an_administrator(): void
    {
        $this->artisan('newsletter:seed-data', ['--subscribers' => 60])->assertSuccessful();

        $this->assertSame(UserRole::Administrator, User::where('email', 'admin@newsletter.test')->first()->role);
        $this->assertSame(63, Subscriber::count());
        $this->assertTrue(Message::where('status', MessageStatus::Sent)->exists());
        $this->assertTrue(Message::where('status', MessageStatus::Ready)->whereNotNull('scheduled_at')->exists());
        $this->assertTrue(Message::has('excludedTags')->exists());
        $this->assertGreaterThan(0, MessageSend::count());
        $this->assertGreaterThan(0, MessageOpen::count());
        $this->assertFalse(Subscriber::where('email', 'not like', '%@example.%')->exists());
    }

    public function test_it_is_idempotent(): void
    {
        $this->artisan('newsletter:seed-data', ['--subscribers' => 60])->assertSuccessful();
        $counts = [Subscriber::count(), MessageSend::count(), MessageOpen::count()];

        $this->artisan('newsletter:seed-data', ['--subscribers' => 60])->assertSuccessful();

        $this->assertSame($counts, [Subscriber::count(), MessageSend::count(), MessageOpen::count()]);
    }

    public function test_it_refuses_to_run_in_production_without_force(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('newsletter:seed-data')->assertFailed();

        $this->assertSame(0, Subscriber::count());
    }
}

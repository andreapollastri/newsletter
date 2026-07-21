<?php

namespace Tests\Feature;

use App\Models\MessageSend;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrackingControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_open_tracking_creates_message_open_record(): void
    {
        $messageSend = MessageSend::factory()->sent()->create();

        $this->get(route('tracking.open', $messageSend))
            ->assertSuccessful()
            ->assertHeader('Content-Type', 'image/gif');

        $this->assertDatabaseHas('message_opens', [
            'message_send_id' => $messageSend->id,
        ]);
    }

    public function test_open_tracking_increments_opens_count(): void
    {
        $messageSend = MessageSend::factory()->sent()->create(['opens_count' => 0]);

        $this->get(route('tracking.open', $messageSend));

        $messageSend->refresh();
        $this->assertEquals(1, $messageSend->opens_count);
    }

    public function test_open_tracking_deduplicates_repeat_opens(): void
    {
        $messageSend = MessageSend::factory()->sent()->create(['opens_count' => 0]);

        $this->get(route('tracking.open', $messageSend))->assertSuccessful();
        $this->get(route('tracking.open', $messageSend))->assertSuccessful();

        $messageSend->refresh();
        $this->assertEquals(1, $messageSend->opens_count);
        $this->assertDatabaseCount('message_opens', 1);
    }

    public function test_click_tracking_creates_message_click_record(): void
    {
        $messageSend = MessageSend::factory()->sent()->create();
        $url = 'https://example.com/test-page';

        $this->get(route('tracking.click', [
            'messageSend' => $messageSend,
            'url' => base64_encode($url),
        ]))->assertRedirect($url);

        $this->assertDatabaseHas('message_clicks', [
            'message_send_id' => $messageSend->id,
            'url' => $url,
        ]);
    }

    public function test_click_tracking_increments_clicks_count(): void
    {
        $messageSend = MessageSend::factory()->sent()->create(['clicks_count' => 0]);
        $url = 'https://example.com/test-page';

        $this->get(route('tracking.click', [
            'messageSend' => $messageSend,
            'url' => base64_encode($url),
        ]));

        $messageSend->refresh();
        $this->assertEquals(1, $messageSend->clicks_count);
    }

    public function test_click_tracking_deduplicates_repeat_clicks_for_same_url(): void
    {
        $messageSend = MessageSend::factory()->sent()->create(['clicks_count' => 0]);
        $url = 'https://example.com/test-page';

        $this->get(route('tracking.click', [
            'messageSend' => $messageSend,
            'url' => base64_encode($url),
        ]))->assertRedirect($url);

        $this->get(route('tracking.click', [
            'messageSend' => $messageSend,
            'url' => base64_encode($url),
        ]))->assertRedirect($url);

        $messageSend->refresh();
        $this->assertEquals(1, $messageSend->clicks_count);
        $this->assertDatabaseCount('message_clicks', 1);
    }

    public function test_click_tracking_counts_distinct_urls_separately(): void
    {
        $messageSend = MessageSend::factory()->sent()->create(['clicks_count' => 0]);

        $this->get(route('tracking.click', [
            'messageSend' => $messageSend,
            'url' => base64_encode('https://example.com/a'),
        ]))->assertRedirect('https://example.com/a');

        $this->get(route('tracking.click', [
            'messageSend' => $messageSend,
            'url' => base64_encode('https://example.com/b'),
        ]))->assertRedirect('https://example.com/b');

        $messageSend->refresh();
        $this->assertEquals(2, $messageSend->clicks_count);
        $this->assertDatabaseCount('message_clicks', 2);
    }

    public function test_click_tracking_redirects_to_original_url(): void
    {
        $messageSend = MessageSend::factory()->sent()->create();
        $url = 'https://example.com/some-page';

        $this->get(route('tracking.click', [
            'messageSend' => $messageSend,
            'url' => base64_encode($url),
        ]))->assertRedirect($url);
    }

    public function test_click_tracking_fails_without_url(): void
    {
        $messageSend = MessageSend::factory()->sent()->create();

        $this->get(route('tracking.click', ['messageSend' => $messageSend]))
            ->assertStatus(400);
    }

    public function test_click_tracking_fails_with_invalid_url(): void
    {
        $messageSend = MessageSend::factory()->sent()->create();

        $this->get(route('tracking.click', [
            'messageSend' => $messageSend,
            'url' => base64_encode('not-a-valid-url'),
        ]))->assertStatus(400);
    }

    public function test_click_tracking_redirects_when_message_send_row_was_removed(): void
    {
        $url = 'https://example.com/after-testing-purge';

        $this->get(route('tracking.click', [
            'messageSend' => '019d6d6a-7303-71e5-b451-c303271926a5',
            'url' => base64_encode($url),
        ]))->assertRedirect($url);

        $this->assertDatabaseCount('message_clicks', 0);
    }

    public function test_open_tracking_returns_pixel_when_message_send_row_was_removed(): void
    {
        $this->get(route('tracking.open', ['messageSend' => '019d6d6a-7303-71e5-b451-c303271926a5']))
            ->assertSuccessful()
            ->assertHeader('Content-Type', 'image/gif');

        $this->assertDatabaseCount('message_opens', 0);
    }

    public function test_tracking_returns_404_for_non_uuid_message_send_parameter(): void
    {
        $this->get(route('tracking.open', ['messageSend' => 'not-a-uuid']))
            ->assertNotFound();

        $this->get(route('tracking.click', [
            'messageSend' => 'not-a-uuid',
            'url' => base64_encode('https://example.com'),
        ]))->assertNotFound();
    }
}

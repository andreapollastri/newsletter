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
}

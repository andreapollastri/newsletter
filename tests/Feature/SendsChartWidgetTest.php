<?php

namespace Tests\Feature;

use App\Filament\Widgets\SendsChartWidget;
use App\Models\Message;
use App\Models\MessageOpen;
use App\Models\MessageSend;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SendsChartWidgetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
        $this->travelTo(now()->setTime(15, 30));
    }

    public function test_it_counts_sends_and_opens_per_day(): void
    {
        $message = Message::factory()->sent()->create();
        $today = MessageSend::factory()->count(2)->sent()->create(['message_id' => $message->id, 'sent_at' => now()->subHour()]);
        MessageSend::factory()->sent()->create(['message_id' => $message->id, 'sent_at' => now()->subDay()]);
        MessageOpen::create(['message_send_id' => $today->first()->id, 'opened_at' => now()]);

        $data = $this->chartData(['period' => '7d']);

        $this->assertCount(7, $data['labels']);
        $this->assertSame(now()->format('d/m'), end($data['labels']));
        $this->assertSame([0, 0, 0, 0, 0, 1, 2], $data['datasets'][0]['data']);
        $this->assertSame([0, 0, 0, 0, 0, 0, 1], $data['datasets'][1]['data']);
    }

    public function test_it_counts_per_hour_for_the_last_24_hours(): void
    {
        $message = Message::factory()->sent()->create();
        MessageSend::factory()->sent()->create(['message_id' => $message->id, 'sent_at' => now()]);
        MessageSend::factory()->sent()->create(['message_id' => $message->id, 'sent_at' => now()->subHours(2)]);

        $data = $this->chartData(['period' => '24h']);

        $this->assertCount(24, $data['labels']);
        $this->assertSame(1, $data['datasets'][0]['data'][23]);
        $this->assertSame(1, $data['datasets'][0]['data'][21]);
        $this->assertSame(2, array_sum($data['datasets'][0]['data']));
    }

    public function test_testing_only_messages_are_excluded(): void
    {
        $testing = Message::factory()->sent()->create();
        $testing->tags()->attach(Tag::factory()->create(['is_testing' => true])->id);
        MessageSend::factory()->sent()->create(['message_id' => $testing->id, 'sent_at' => now()]);

        $data = $this->chartData(['period' => '7d']);

        $this->assertSame(0, array_sum($data['datasets'][0]['data']));
    }

    /**
     * @param  array<string, string>  $filters
     * @return array{datasets: list<array<string, mixed>>, labels: list<string>}
     */
    private function chartData(array $filters): array
    {
        $widget = Livewire::test(SendsChartWidget::class, ['pageFilters' => $filters])->instance();

        return (fn (): array => $this->getData())->call($widget);
    }
}

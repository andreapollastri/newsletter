<?php

namespace Tests\Unit\Models;

use App\Models\Message;
use App\Models\MessageSend;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessageTestingAudienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_has_testing_audience_only_is_false_when_no_tags(): void
    {
        $message = Message::factory()->create();

        $this->assertFalse($message->hasTestingAudienceOnly());
    }

    public function test_has_testing_audience_only_is_true_when_all_tags_are_testing(): void
    {
        $message = Message::factory()->create();
        $tag = Tag::factory()->testing()->create();
        $message->tags()->attach($tag->id);

        $this->assertTrue($message->fresh()->hasTestingAudienceOnly());
    }

    public function test_has_testing_audience_only_is_false_when_mixed_tags(): void
    {
        $message = Message::factory()->create();
        $message->tags()->attach([
            Tag::factory()->testing()->create()->id,
            Tag::factory()->create(['is_testing' => false])->id,
        ]);

        $this->assertFalse($message->fresh()->hasTestingAudienceOnly());
    }

    public function test_message_send_for_statistics_excludes_testing_audience_messages(): void
    {
        $normalMessage = Message::factory()->create();
        $normalMessage->tags()->attach(Tag::factory()->create(['is_testing' => false])->id);

        $testingMessage = Message::factory()->create();
        $testingMessage->tags()->attach(Tag::factory()->testing()->create()->id);

        $normalSend = MessageSend::factory()->create(['message_id' => $normalMessage->id]);
        $testingSend = MessageSend::factory()->create(['message_id' => $testingMessage->id]);

        $ids = MessageSend::query()->forStatistics()->pluck('id');

        $this->assertTrue($ids->contains($normalSend->id));
        $this->assertFalse($ids->contains($testingSend->id));
    }
}

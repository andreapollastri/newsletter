<?php

namespace Tests\Unit\Models;

use App\Models\Message;
use App\Models\Subscriber;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessageTargetAudienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_tags_overlap_detects_shared_ids(): void
    {
        $this->assertTrue(Message::tagsOverlap(['a', 'b'], ['b', 'c']));
        $this->assertFalse(Message::tagsOverlap(['a'], ['b']));
        $this->assertFalse(Message::tagsOverlap([], ['b']));
    }

    public function test_target_subscribers_are_all_confirmed_when_no_tags(): void
    {
        $confirmed = Subscriber::factory()->confirmed()->create();
        Subscriber::factory()->pending()->create();
        $message = Message::factory()->create();

        $this->assertEqualsCanonicalizing(
            [$confirmed->id],
            $message->targetSubscribers()->pluck('id')->all()
        );
    }

    public function test_target_subscribers_exclude_tag_only(): void
    {
        $partner = Tag::factory()->create(['name' => 'partner']);
        $partnerSubscriber = Subscriber::factory()->confirmed()->create();
        $partnerSubscriber->tags()->attach($partner->id);
        $regular = Subscriber::factory()->confirmed()->create();

        $message = Message::factory()->create();
        $message->excludedTags()->attach($partner->id);

        $this->assertEqualsCanonicalizing(
            [$regular->id],
            $message->fresh()->targetSubscribers()->pluck('id')->all()
        );
    }

    public function test_target_subscribers_include_and_exclude_tags(): void
    {
        $needA = Tag::factory()->create(['name' => 'need-A']);
        $partner = Tag::factory()->create(['name' => 'partner']);

        $needAOnly = Subscriber::factory()->confirmed()->create();
        $needAOnly->tags()->attach($needA->id);

        $needAAndPartner = Subscriber::factory()->confirmed()->create();
        $needAAndPartner->tags()->attach([$needA->id, $partner->id]);

        $unrelated = Subscriber::factory()->confirmed()->create();

        $message = Message::factory()->create();
        $message->tags()->attach($needA->id);
        $message->excludedTags()->attach($partner->id);

        $this->assertEqualsCanonicalizing(
            [$needAOnly->id],
            $message->fresh()->targetSubscribers()->pluck('id')->all()
        );
        $this->assertNotContains($unrelated->id, $message->fresh()->targetSubscribers()->pluck('id')->all());
    }

    public function test_audience_labels_use_not_prefix_for_excluded_tags(): void
    {
        $partner = Tag::factory()->create(['name' => 'partner']);
        $message = Message::factory()->create();
        $message->excludedTags()->attach($partner->id);

        $this->assertSame(
            [__('not :tag', ['tag' => 'partner'])],
            $message->fresh()->audienceLabels()
        );
    }
}

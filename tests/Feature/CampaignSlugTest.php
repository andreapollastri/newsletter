<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignSlugTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_slugs_are_suffixed_when_saving(): void
    {
        $user = User::factory()->create();
        Campaign::factory()->create(['user_id' => $user->id, 'slug' => 'spring-sale']);
        $second = Campaign::factory()->create(['user_id' => $user->id, 'slug' => 'spring-sale']);
        $second->refresh();

        $this->assertSame('spring-sale-2', $second->slug);
    }
}

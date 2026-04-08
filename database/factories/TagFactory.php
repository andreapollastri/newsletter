<?php

namespace Database\Factories;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tag>
 */
class TagFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'is_testing' => false,
        ];
    }

    /**
     * Mark the tag as a testing tag (excluded from statistics; sends purged after completion).
     */
    public function testing(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_testing' => true,
        ]);
    }
}

<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\Application>
 */
class ApplicationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Use dot-separated slug to align with Application::findByKey normalization
            'app_key' => \Illuminate\Support\Str::slug($this->faker->unique()->slug(2), '.'),
            'name' => $this->faker->unique()->company(),
            'description' => $this->faker->optional()->sentence(),
            'enabled' => true,
            'callback_url' => sprintf('http://127.0.0.1:%d/callback', $this->faker->numberBetween(8000, 9000)),
            'secret' => Str::random(40),
        ];
    }
}

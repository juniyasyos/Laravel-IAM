<?php

namespace Database\Factories;

use App\Models\Setting;
use Illuminate\Database\Eloquent\Factories\Factory;

class SettingFactory extends Factory
{
    protected $model = Setting::class;

    public function definition(): array
    {
        $groups = ['sso', 'iam', 'auth', 'fortify', 'general'];
        $types = ['string', 'integer', 'boolean', 'json'];

        $type = $this->faker->randomElement($types);

        $value = match ($type) {
            'integer' => (string) $this->faker->numberBetween(1, 10000),
            'boolean' => $this->faker->boolean() ? 'true' : 'false',
            'json' => json_encode(['example' => $this->faker->word()]),
            default => $this->faker->word(),
        };

        return [
            'key' => $this->faker->unique()->words(2, true),
            'group' => $this->faker->randomElement($groups),
            'value' => $value,
            'type' => $type,
            'description' => $this->faker->sentence(),
            'input_type' => 'text',
            'select_options' => null,
            'validation_rules' => null,
            'is_readonly' => false,
            'is_sensitive' => false,
            'environment' => null,
            'category' => null,
        ];
    }
}

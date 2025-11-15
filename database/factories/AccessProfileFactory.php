<?php

namespace Database\Factories;

use App\Domain\Iam\Models\AccessProfile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories.Factory<\App\Models\AccessProfile>
 */
class AccessProfileFactory extends Factory
{
    protected $model = AccessProfile::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $name = $this->faker->unique()->randomElement([
            'Kepala Unit',
            'Tim Mutu',
            'Admin Mutu',
            'Direktur RS',
            'Perawat Unit',
        ]);

        return [
            'slug'        => Str::slug($name, '_'), // kepala_unit, tim_mutu, dst
            'name'        => $name,
            'description' => $this->faker->optional()->sentence(),
            'is_system'   => false,
            'is_active'   => true,
        ];
    }

    /**
     * State untuk profile sistem (tidak boleh dihapus seenaknya).
     */
    public function system(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_system' => true,
        ]);
    }

    /**
     * State untuk profile non-aktif.
     */
    public function inactive(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_active' => false,
        ]);
    }
}

<?php

namespace Database\Factories;

use App\Models\Activity;
use App\Models\ActivityVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityVersion>
 */
class ActivityVersionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'activity_id' => Activity::factory(),
            'version_number' => 1,
            'title' => fake()->sentence(3),
            'instructions' => fake()->paragraph(),
            'evaluation_criteria' => null,
            'created_by_user_id' => User::factory(),
            'published_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => ['published_at' => now()]);
    }
}

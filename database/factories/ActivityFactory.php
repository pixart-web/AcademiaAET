<?php

namespace Database\Factories;

use App\Enums\ActivityStatus;
use App\Models\Activity;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Activity>
 */
class ActivityFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->sentence(),
            'category' => fake()->randomElement(['comunicação', 'motricidade', 'emocional']),
            'area' => fake()->randomElement(['linguagem', 'atenção', 'autonomia']),
            'difficulty' => fake()->randomElement(['facil', 'medio', 'dificil']),
            'status' => ActivityStatus::Draft,
            'created_by_user_id' => User::factory(),
            'is_demo' => true,
        ];
    }
}

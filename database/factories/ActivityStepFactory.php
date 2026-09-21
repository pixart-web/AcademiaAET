<?php

namespace Database\Factories;

use App\Enums\ResponseType;
use App\Models\ActivityStep;
use App\Models\ActivityVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityStep>
 */
class ActivityStepFactory extends Factory
{
    public function definition(): array
    {
        return [
            'activity_version_id' => ActivityVersion::factory(),
            'position' => 1,
            'title' => fake()->sentence(3),
            'body' => fake()->sentence(),
            'response_type' => ResponseType::SingleChoice,
            'response_config' => [
                'options' => ['Sim', 'Não'],
                'correct' => 'Sim',
            ],
        ];
    }
}

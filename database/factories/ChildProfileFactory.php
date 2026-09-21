<?php

namespace Database\Factories;

use App\Enums\ChildStatus;
use App\Enums\VisualExperience;
use App\Models\ChildProfile;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChildProfile>
 */
class ChildProfileFactory extends Factory
{
    public function definition(): array
    {
        $birthDate = fake()->dateTimeBetween('-17 years', '-3 years');

        return [
            'organization_id' => Organization::factory(),
            'first_name' => fake()->firstName(),
            'preferred_name' => null,
            'birth_date' => $birthDate,
            'visual_experience' => VisualExperience::suggestedFor(now()->diffInYears($birthDate)),
            'visual_experience_overridden' => false,
            'status' => ChildStatus::Active,
            'care_notes' => null,
            'is_demo' => true,
        ];
    }
}

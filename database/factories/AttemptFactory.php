<?php

namespace Database\Factories;

use App\Enums\AttemptStatus;
use App\Models\Assignment;
use App\Models\Attempt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attempt>
 */
class AttemptFactory extends Factory
{
    public function definition(): array
    {
        return [
            'assignment_id' => Assignment::factory(),
            'attempt_number' => 1,
            'status' => AttemptStatus::InProgress,
            'started_at' => now(),
        ];
    }
}

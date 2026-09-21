<?php

namespace Database\Factories;

use App\Enums\AssignmentStatus;
use App\Models\ActivityVersion;
use App\Models\Assignment;
use App\Models\ChildProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Assignment>
 */
class AssignmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'child_profile_id' => ChildProfile::factory(),
            'activity_version_id' => ActivityVersion::factory(),
            'assigned_by_user_id' => User::factory(),
            'status' => AssignmentStatus::Assigned,
        ];
    }
}

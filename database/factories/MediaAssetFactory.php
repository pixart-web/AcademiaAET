<?php

namespace Database\Factories;

use App\Enums\MediaKind;
use App\Models\MediaAsset;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MediaAsset>
 */
class MediaAssetFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'disk' => 'local',
            'path' => 'media/demo/'.fake()->uuid().'.png',
            'mime_type' => 'image/png',
            'kind' => MediaKind::Image,
            'title' => fake()->words(2, true),
            'alt_text' => fake()->sentence(),
            'size_bytes' => 1024,
            'status' => 'active',
        ];
    }
}

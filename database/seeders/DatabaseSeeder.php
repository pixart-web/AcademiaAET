<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Only ever calls the demo seeder, which itself refuses to run outside
     * local/testing unless APP_ALLOW_DEMO_SEEDING is explicitly set — a fresh
     * production install must boot with zero demo data.
     */
    public function run(): void
    {
        $this->call(DemoDataSeeder::class);
    }
}

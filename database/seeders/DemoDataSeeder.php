<?php

namespace Database\Seeders;

use App\Enums\ResponseType;
use App\Enums\UserRole;
use App\Enums\VisualExperience;
use App\Models\Activity;
use App\Models\Assignment;
use App\Models\ChildProfile;
use App\Models\Organization;
use App\Models\ProfessionalAssignment;
use App\Models\User;
use App\Services\ActivityVersioningService;
use Illuminate\Database\Seeder;

/**
 * Fictional demo content only — never run against production (guarded by
 * APP_ALLOW_DEMO_SEEDING; see docs/demo-content.md).
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing') && ! config('app.allow_demo_seeding')) {
            $this->command?->error('Seeding de demonstração bloqueado fora de ambiente local (ver APP_ALLOW_DEMO_SEEDING).');

            return;
        }

        $org = Organization::query()->firstOrCreate(
            ['slug' => 'academia-aet-demo'],
            ['name' => 'Academia AET (Demonstração)', 'timezone' => 'Europe/Lisbon'],
        );

        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@academia-aet.test'],
            ['organization_id' => $org->id, 'role' => UserRole::Admin, 'name' => 'Admin Demonstração', 'password' => bcrypt('password')],
        );

        $professional = User::query()->firstOrCreate(
            ['email' => 'terapeuta@academia-aet.test'],
            ['organization_id' => $org->id, 'role' => UserRole::Professional, 'name' => 'Terapeuta Demonstração', 'password' => bcrypt('password')],
        );

        $children = collect([
            ['first_name' => 'Matilde', 'preferred_name' => null, 'birth_date' => now()->subYears(5)],
            ['first_name' => 'Rodrigo', 'preferred_name' => null, 'birth_date' => now()->subYears(10)],
            ['first_name' => 'Beatriz', 'preferred_name' => 'Bia', 'birth_date' => now()->subYears(16)],
        ])->map(function ($data) use ($org) {
            return ChildProfile::query()->firstOrCreate(
                ['organization_id' => $org->id, 'first_name' => $data['first_name']],
                [
                    'preferred_name' => $data['preferred_name'],
                    'birth_date' => $data['birth_date'],
                    'visual_experience' => VisualExperience::suggestedFor(now()->diffInYears($data['birth_date'])),
                    'status' => 'active',
                    'is_demo' => true,
                ],
            );
        });

        foreach ($children as $child) {
            ProfessionalAssignment::query()->firstOrCreate([
                'child_profile_id' => $child->id,
                'user_id' => $professional->id,
                'active' => true,
            ], [
                'assigned_by_user_id' => $admin->id,
                'started_at' => now(),
            ]);
        }

        $activity = Activity::query()->firstOrCreate(
            ['organization_id' => $org->id, 'title' => 'Reconhecer emoções — demonstração'],
            [
                'description' => 'Atividade de demonstração para reconhecimento de emoções básicas.',
                'category' => 'emocional',
                'area' => 'comunicação',
                'difficulty' => 'facil',
                'status' => 'draft',
                'created_by_user_id' => $professional->id,
                'is_demo' => true,
            ],
        );

        if ($activity->wasRecentlyCreated) {
            $versioning = app(ActivityVersioningService::class);

            $version = $versioning->createInitialVersion(
                $activity,
                $professional,
                $activity->title,
                'Observe a imagem e responda à pergunta. Pode repetir sempre que precisar.',
                'Correção automática nas escolhas; registo qualitativo nas restantes.',
                [
                    [
                        'title' => 'Como está a criança na imagem?',
                        'body' => 'Escolha a opção que descreve melhor a expressão.',
                        'response_type' => ResponseType::SingleChoice->value,
                        'response_config' => ['options' => ['Feliz', 'Triste', 'Zangado'], 'correct' => 'Feliz'],
                    ],
                    [
                        'title' => 'Diga por palavras suas',
                        'body' => 'Explique como soube a resposta.',
                        'response_type' => ResponseType::ShortText->value,
                    ],
                    [
                        'title' => 'Repita a palavra "feliz"',
                        'body' => 'Ouça o áudio e grave-se a repetir a palavra.',
                        'response_type' => ResponseType::VoiceRecording->value,
                    ],
                ],
            );

            $versioning->publish($version);
        }

        $firstChild = $children->first();
        if ($firstChild && $activity->current_version_id) {
            Assignment::query()->firstOrCreate([
                'child_profile_id' => $firstChild->id,
                'activity_version_id' => $activity->fresh()->current_version_id,
            ], [
                'assigned_by_user_id' => $professional->id,
                'status' => 'assigned',
            ]);
        }

        $this->command?->info('Dados de demonstração criados. Login: admin@academia-aet.test / terapeuta@academia-aet.test (password: password)');
    }
}

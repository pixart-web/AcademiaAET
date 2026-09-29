<?php

namespace Tests\Feature;

use App\Models\ChildProfile;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\TestCase;

class ChildProfileEditTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Regression test: ChildProfile had #[Hidden(['care_notes'])], which
     * stripped the field from every Inertia response including the edit
     * form itself — so the "Editar perfil" page always showed an empty
     * textarea regardless of existing notes, and saving without manually
     * retyping them would silently wipe out real clinical notes.
     */
    public function test_edit_page_is_prefilled_with_existing_care_notes(): void
    {
        $org = Organization::factory()->create();
        $admin = User::factory()->admin()->for($org)->create();
        $child = ChildProfile::factory()->for($org)->create(['care_notes' => 'Nota importante já existente']);

        $this->actingAs($admin)
            ->get(route('children.edit', $child))
            ->assertInertia(fn (AssertableJson $page) => $page
                ->where('child.care_notes', 'Nota importante já existente')
                ->etc()
            );
    }

    public function test_saving_the_edit_form_without_touching_care_notes_does_not_erase_it(): void
    {
        $org = Organization::factory()->create();
        $admin = User::factory()->admin()->for($org)->create();
        $child = ChildProfile::factory()->for($org)->create(['care_notes' => 'Nota importante já existente']);

        // Simulates the real form: it always submits whatever value it was
        // given, which must be the existing note (see the test above) —
        // not an empty string, which is what happened before the fix.
        $this->actingAs($admin)->put(route('children.update', $child), [
            'first_name' => $child->first_name,
            'birth_date' => $child->birth_date->format('Y-m-d'),
            'status' => $child->status->value,
            'visual_experience' => $child->visual_experience->value,
            'care_notes' => 'Nota importante já existente',
        ]);

        $this->assertSame('Nota importante já existente', $child->fresh()->care_notes);
    }
}

<?php

namespace Tests\Feature;

use App\Enums\ScreeningItemKind;
use App\Mail\ScreeningReportMail;
use App\Models\Organization;
use App\Models\ScreeningItem;
use App\Models\ScreeningSession;
use App\Models\User;
use App\Services\Screening\ScreeningAge;
use App\Services\Screening\ScreeningEngine;
use App\Services\Screening\ScreeningScorer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ScreeningTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private User $pro;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-03 10:00:00');
        Storage::fake('local');
        Mail::fake();

        $this->org = Organization::factory()->create(['settings' => ['screening_clinic_email' => 'clinica@example.test']]);
        $this->pro = User::factory()->for($this->org)->create();
    }

    private function items(string $block = '4'): void
    {
        $n = 1;
        // speech: 4 words (b, f, ʃ, R); comprehension: 5; naming: 2; repetition: 2
        foreach ([['Bola', 'b'], ['Fogo', 'f'], ['Chave', 'ʃ'], ['Rato', 'R']] as [$w, $ph]) {
            ScreeningItem::create(['organization_id' => $this->org->id, 'age_block' => $block, 'kind' => 'speech', 'position' => $n++, 'prompt' => 'Diz.', 'target_word' => $w, 'phoneme' => $ph]);
        }
        for ($i = 1; $i <= 5; $i++) {
            ScreeningItem::create(['organization_id' => $this->org->id, 'age_block' => $block, 'kind' => 'comprehension', 'position' => $i, 'prompt' => "Clica {$i}", 'options' => [
                ['label' => 'certa', 'correct' => true], ['label' => 'b', 'correct' => false], ['label' => 'c', 'correct' => false], ['label' => 'd', 'correct' => false],
            ]]);
        }
        foreach (['naming', 'repetition'] as $kind) {
            foreach ([1, 2] as $p) {
                ScreeningItem::create(['organization_id' => $this->org->id, 'age_block' => $block, 'kind' => $kind, 'position' => $p, 'prompt' => 'P', 'expected_answer' => 'R']);
            }
        }
    }

    private function makeSession(string $birth = '2022-07-20', ?User $by = null): ScreeningSession
    {
        $birthDate = Carbon::parse($birth);

        return ScreeningSession::create([
            'organization_id' => $this->org->id, 'conducted_by_user_id' => ($by ?? $this->pro)->id,
            'institution_name' => 'Escola Teste', 'child_name' => 'Criança Fictícia', 'birth_date' => $birthDate, 'gender' => 'feminino',
            'guardian_name' => 'Enc. Fictício', 'guardian_email' => 'enc@example.test', 'guardian_phone' => '912345678',
            'screened_on' => now()->toDateString(), 'age_months' => ScreeningAge::months($birthDate, now()),
            'age_block' => ScreeningAge::block(ScreeningAge::months($birthDate, now())) ?? '4',
            'consent_confirmed_at' => now(),
        ]);
    }

    /** Answers every item; $correctFor decides if an item is answered correctly. */
    private function runAll(ScreeningSession $s, callable $correctFor, array $speechError = ['outcome' => 'omission']): void
    {
        $engine = app(ScreeningEngine::class);

        while ($item = $engine->next($s->fresh())) {
            $ok = $correctFor($item);
            $input = match ($item->kind) {
                ScreeningItemKind::Comprehension => ['option_index' => $this->presentedIndex($engine, $s, $item, $ok)],
                ScreeningItemKind::Speech => $ok ? ['outcome' => 'correct'] : $speechError,
                default => ['outcome' => $ok ? 'correct' : 'incorrect'],
            };
            $engine->record($s->fresh(), $item, $input);
        }
    }

    private function presentedIndex(ScreeningEngine $engine, ScreeningSession $s, ScreeningItem $item, bool $wantCorrect): int
    {
        foreach ($engine->presentedOptions($s, $item) as $i => $o) {
            if (($o['correct'] ?? false) === $wantCorrect) {
                return $i;
            }
        }
        $this->fail('no option');
    }

    public function test_form_routes_to_the_right_age_block_and_stores_the_age(): void
    {
        $this->items('4');

        $this->actingAs($this->pro)->post(route('screenings.store'), $this->payload('20/07/2022'))->assertRedirect();

        $s = ScreeningSession::firstOrFail();
        $this->assertSame('4', $s->age_block);
        $this->assertSame(51, $s->age_months);
        $this->assertSame('4 anos e 3 meses', $s->ageLabel());
    }

    private function payload(string $birth): array
    {
        return [
            'institution_name' => 'Escola Teste', 'child_name' => 'Criança Fictícia', 'birth_date' => $birth, 'gender' => 'feminino',
            'guardian_name' => 'Enc', 'guardian_email' => 'enc@example.test', 'guardian_phone' => '912 345 678', 'consent' => true,
        ];
    }

    public function test_ages_outside_3_to_7_and_bad_input_are_rejected(): void
    {
        $this->items('4');

        $this->actingAs($this->pro)->post(route('screenings.store'), $this->payload('04/11/2023'))->assertSessionHasErrors('birth_date'); // 2a11m
        $this->actingAs($this->pro)->post(route('screenings.store'), $this->payload('01/01/2018'))->assertSessionHasErrors('birth_date'); // 8a
        $this->actingAs($this->pro)->post(route('screenings.store'), $this->payload('2022-07-20'))->assertSessionHasErrors('birth_date'); // wrong format
        $this->actingAs($this->pro)->post(route('screenings.store'), $this->payload('20/07/2030'))->assertSessionHasErrors('birth_date'); // future
        $this->actingAs($this->pro)->post(route('screenings.store'), [...$this->payload('20/07/2022'), 'consent' => false])->assertSessionHasErrors('consent');
        $this->assertSame(0, ScreeningSession::count());
    }

    public function test_a_block_without_items_cannot_be_started(): void
    {
        $this->actingAs($this->pro)->post(route('screenings.store'), $this->payload('20/07/2022'))->assertSessionHasErrors('birth_date');
        $this->assertSame(0, ScreeningSession::count());
    }

    public function test_the_correct_option_is_never_sent_to_the_browser(): void
    {
        $this->items();
        $s = $this->makeSession();
        $engine = app(ScreeningEngine::class);
        // skip speech items to reach comprehension
        foreach (range(1, 4) as $_) {
            $engine->record($s, $engine->next($s), ['outcome' => 'correct']);
        }

        $response = $this->actingAs($this->pro)->get(route('screenings.run', $s));

        $response->assertInertia(fn ($page) => $page->where('item.kind', 'comprehension')->has('item.options', 4)->missing('item.options.0.correct'));
        $this->assertStringNotContainsString('"correct"', $response->getContent());
    }

    public function test_substitution_requires_the_produced_sound_and_kinds_restrict_outcomes(): void
    {
        $this->items();
        $s = $this->makeSession();
        $engine = app(ScreeningEngine::class);
        $item = $engine->next($s);

        $this->actingAs($this->pro)->post(route('screenings.answer', $s), ['item_id' => $item->id, 'outcome' => 'substitution'])->assertSessionHasErrors('produced_sound');
        $this->actingAs($this->pro)->post(route('screenings.answer', $s), ['item_id' => $item->id, 'outcome' => 'incorrect'])->assertSessionHasErrors('outcome');
        $this->actingAs($this->pro)->post(route('screenings.answer', $s), ['item_id' => $item->id, 'outcome' => 'substitution', 'produced_sound' => 'd'])->assertRedirect();

        $r = $s->responses()->firstOrFail();
        $this->assertSame('substitution', $r->outcome->value);
        $this->assertSame('d', $r->produced_sound);
        $this->assertSame(0, $r->points);
    }

    public function test_only_the_current_item_can_be_answered(): void
    {
        $this->items();
        $s = $this->makeSession();
        $items = app(ScreeningEngine::class)->items($s);

        $this->actingAs($this->pro)->post(route('screenings.answer', $s), ['item_id' => $items[3]->id, 'outcome' => 'correct'])->assertStatus(409);
        $this->assertSame(0, $s->responses()->count());
    }

    public function test_three_consecutive_errors_close_the_subsection_and_record_the_rest_as_not_applied(): void
    {
        $this->items();
        $s = $this->makeSession();
        $engine = app(ScreeningEngine::class);
        foreach (range(1, 4) as $_) {
            $engine->record($s, $engine->next($s), ['outcome' => 'correct']);
        }

        // comprehension: 1 right, then 3 wrong in a row -> item 5 not applied
        foreach ([true, false, false, false] as $ok) {
            $item = $engine->next($s);
            $engine->record($s, $item, ['option_index' => $this->presentedIndex($engine, $s, $item, $ok)]);
        }

        $last = $s->responses()->whereHas('item', fn ($q) => $q->where('kind', 'comprehension')->where('position', 5))->firstOrFail();
        $this->assertSame('not_applied', $last->outcome->value);
        $this->assertNull($last->points);
        // flow moved on to the next subsection (expression/naming), not item 5
        $this->assertSame(ScreeningItemKind::Naming, $engine->next($s)->kind);
    }

    public function test_two_errors_then_a_correct_answer_does_not_close_the_subsection(): void
    {
        $this->items();
        $s = $this->makeSession();
        $engine = app(ScreeningEngine::class);
        foreach (range(1, 4) as $_) {
            $engine->record($s, $engine->next($s), ['outcome' => 'correct']);
        }
        foreach ([false, false, true, false] as $ok) {
            $item = $engine->next($s);
            $engine->record($s, $item, ['option_index' => $this->presentedIndex($engine, $s, $item, $ok)]);
        }

        $this->assertSame(0, $s->responses()->where('outcome', 'not_applied')->count());
        $this->assertSame(5, $engine->next($s)->position);
    }

    public function test_not_applied_items_are_left_out_of_the_percentage(): void
    {
        $this->items();
        $s = $this->makeSession();
        // speech all right; comprehension 1 right then 3 wrong (item 5 skipped); expression all right
        $count = 0;
        $this->runAll($s, function (ScreeningItem $i) use (&$count) {
            if ($i->kind === ScreeningItemKind::Comprehension) {
                return $i->position === 1;
            }

            return true;
        });

        $r = $s->fresh()->result;
        $this->assertSame(1, $r['areas']['comprehension']['not_applied']);
        $this->assertSame(4, $r['areas']['comprehension']['applicable']);
        $this->assertEquals(25.0, $r['areas']['comprehension']['percent']);
        $this->assertEquals(100.0, $r['areas']['speech']['percent']);
        // 4 + 1 + 4 = 9 points of 12 applicable
        $this->assertSame(9, $r['total']['points']);
        $this->assertSame(12, $r['total']['applicable']);
        $this->assertEquals(75.0, $r['total']['percent']);
    }

    public function test_below_70_percent_suggests_formal_evaluation(): void
    {
        $this->items();
        $s = $this->makeSession();
        $this->runAll($s, fn (ScreeningItem $i) => $i->kind === ScreeningItemKind::Speech);

        $r = $s->fresh()->result;
        $this->assertTrue($r['attention']);
        $this->assertContains('score_below_threshold', $r['attention_reasons']);
        $this->assertStringContainsString('Sugerida Avaliação Formal em Terapia da Fala', $r['conclusion']);
    }

    public function test_70_percent_or_more_without_expected_sound_errors_is_in_line_with_expected(): void
    {
        $this->items();
        $s = $this->makeSession(); // 4a3m = 51 months
        // only the /ʃ/ and /R/ words fail (acquired by 59 months — not yet expected at 51 months)
        $this->runAll($s, fn (ScreeningItem $i) => ! in_array($i->phoneme, ['ʃ', 'R'], true));

        $r = $s->fresh()->result;
        $this->assertSame(11, $r['total']['points']); // 2 of 4 speech + 5 + 2 + 2 of 13 applicable
        $this->assertGreaterThanOrEqual(70, $r['total']['percent']);
        $this->assertFalse($r['attention']);
        $this->assertCount(2, $r['findings']);
        $this->assertFalse($r['findings'][0]['expected_for_age']);
        $this->assertStringContainsString('de acordo com o esperado', $r['conclusion']);
    }

    public function test_an_error_in_a_sound_already_expected_for_the_age_triggers_attention_even_above_70(): void
    {
        $this->items();
        $s = $this->makeSession(); // 51 months: /b/ (36) and /f/ (47) are already expected
        $this->runAll($s, fn (ScreeningItem $i) => $i->phoneme !== 'f');

        $r = $s->fresh()->result;
        $this->assertGreaterThanOrEqual(70, $r['total']['percent']);
        $this->assertTrue($r['attention']);
        $this->assertSame(['phonetic_error_in_expected_sound'], $r['attention_reasons']);
        $this->assertTrue($r['findings'][0]['expected_for_age']);
    }

    public function test_completing_generates_a_pdf_and_emails_the_guardian_and_the_clinic(): void
    {
        $this->items();
        $s = $this->makeSession();
        $this->runAll($s, fn () => true);

        $s = $s->fresh();
        $this->assertTrue($s->isCompleted());
        Storage::disk('local')->assertExists($s->report_path);
        $this->assertStringStartsWith('%PDF', Storage::disk('local')->get($s->report_path));
        Mail::assertSent(ScreeningReportMail::class, fn ($m) => $m->hasTo('enc@example.test') && ! $m->forClinic);
        Mail::assertSent(ScreeningReportMail::class, fn ($m) => $m->hasTo('clinica@example.test') && $m->forClinic);
        $this->assertNotNull($s->report_guardian_sent_at);
        $this->assertNotNull($s->report_clinic_sent_at);
        $this->assertNull($s->report_error);
    }

    public function test_the_email_body_never_contains_the_result(): void
    {
        $this->items();
        $s = $this->makeSession();
        $this->runAll($s, fn () => false);

        Mail::assertSent(ScreeningReportMail::class, function ($m) {
            $html = $m->render();

            return ! str_contains($html, 'Avaliação Formal') && ! str_contains($html, '%');
        });
    }

    public function test_a_missing_clinic_email_is_recorded_but_does_not_lose_the_screening(): void
    {
        $this->org->update(['settings' => []]);
        $this->items();
        $s = $this->makeSession();
        $this->runAll($s, fn () => true);

        $s = $s->fresh();
        $this->assertTrue($s->isCompleted());
        $this->assertNotNull($s->report_guardian_sent_at);
        $this->assertNull($s->report_clinic_sent_at);
        $this->assertStringContainsString('Sem email da clínica', $s->report_error);
    }

    public function test_access_is_limited_to_the_staff_member_who_ran_it_and_admins_of_the_organization(): void
    {
        $this->items();
        $s = $this->makeSession();
        $other = User::factory()->for($this->org)->create();
        $admin = User::factory()->admin()->for($this->org)->create();
        $outsider = User::factory()->admin()->for(Organization::factory()->create())->create();

        $this->actingAs($other)->get(route('screenings.run', $s))->assertForbidden();
        $this->actingAs($outsider)->get(route('screenings.run', $s))->assertForbidden();
        $this->actingAs($admin)->get(route('screenings.run', $s))->assertOk();
        $this->actingAs($this->pro)->get(route('screenings.run', $s))->assertOk();
    }

    public function test_the_report_can_be_downloaded_only_when_completed(): void
    {
        $this->items();
        $s = $this->makeSession();

        $this->actingAs($this->pro)->get(route('screenings.report', $s))->assertNotFound();

        $this->runAll($s, fn () => true);
        $this->actingAs($this->pro)->get(route('screenings.report', $s))->assertOk()->assertDownload('relatorio-rastreio.pdf');
    }

    public function test_a_completed_screening_cannot_be_changed(): void
    {
        $this->items();
        $s = $this->makeSession();
        $this->runAll($s, fn () => true);

        $item = ScreeningItem::first();
        $this->actingAs($this->pro)->post(route('screenings.answer', $s), ['item_id' => $item->id, 'outcome' => 'incorrect'])->assertStatus(422);
    }

    public function test_scorer_is_deterministic_and_handles_an_empty_denominator(): void
    {
        $this->items();
        $s = $this->makeSession();

        $summary = app(ScreeningScorer::class)->summarize($s);
        $this->assertNull($summary['total']['percent']);
        $this->assertFalse($summary['attention']);
    }
}

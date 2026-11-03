<?php

namespace App\Services\Screening;

use App\Enums\ScreeningItemKind;
use App\Enums\ScreeningOutcome;
use App\Models\ScreeningItem;
use App\Models\ScreeningResponse;
use App\Models\ScreeningSession;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Server-authoritative flow of one screening: which item comes next, how an
 * answer is validated and scored, and when a subsection is closed by the
 * consecutive-errors rule. The browser only ever sends an answer for the item
 * the server itself says is current — never a score, never the order.
 */
class ScreeningEngine
{
    public function __construct(private readonly ScreeningScorer $scorer, private readonly ScreeningReportService $reports) {}

    /** @return Collection<int, ScreeningItem> */
    public function items(ScreeningSession $session): Collection
    {
        return ScreeningItem::query()
            ->where('organization_id', $session->organization_id)
            ->where('age_block', $session->age_block)
            ->get()
            ->sortBy(fn (ScreeningItem $i) => [$i->kind->order(), $i->position])
            ->values();
    }

    public function next(ScreeningSession $session): ?ScreeningItem
    {
        $answered = $session->responses()->pluck('screening_item_id')->all();

        return $this->items($session)->first(fn (ScreeningItem $i) => ! in_array($i->id, $answered, true));
    }

    /** @return array{done: int, total: int} items resolved (answered or not applied) / all items */
    public function progress(ScreeningSession $session): array
    {
        return ['done' => $session->responses()->count(), 'total' => $this->items($session)->count()];
    }

    /**
     * @param  array{outcome?: string, produced_sound?: ?string, option_index?: ?int}  $input
     */
    public function record(ScreeningSession $session, ScreeningItem $item, array $input): ScreeningResponse
    {
        abort_if($session->isCompleted(), 422, 'Este rastreio já foi concluído.');

        return DB::transaction(function () use ($session, $item, $input) {
            ScreeningSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();

            $current = $this->next($session);
            abort_unless($current !== null && $current->is($item), 409, 'Este item já não é o item atual.');

            [$outcome, $produced] = $this->resolveOutcome($session, $item, $input);

            $response = $session->responses()->create([
                'screening_item_id' => $item->id,
                'outcome' => $outcome,
                'points' => $outcome->points(),
                'produced_sound' => $produced,
                'answered_at' => now(),
            ]);

            $this->applyStopRule($session, $item);

            if ($this->next($session) === null) {
                $this->finalize($session);
            }

            return $response;
        });
    }

    /**
     * Options in the order shown to the child: deterministic per session+item
     * (so the answer endpoint can map a tapped position back to the same
     * option) but not the stored order, which would otherwise always put the
     * correct option first.
     *
     * @return list<array{label: string, correct?: bool, media_asset_id?: int}>
     */
    public function presentedOptions(ScreeningSession $session, ScreeningItem $item): array
    {
        return collect($item->options ?? [])
            ->sortBy(fn (array $option, int $i) => crc32("{$session->id}-{$item->id}-{$i}"))
            ->values()
            ->all();
    }

    /** @return array{0: ScreeningOutcome, 1: ?string} */
    private function resolveOutcome(ScreeningSession $session, ScreeningItem $item, array $input): array
    {
        if ($item->kind->isAutoValidated()) {
            $options = $this->presentedOptions($session, $item);
            $index = $input['option_index'] ?? null;

            if (! is_int($index) || ! isset($options[$index])) {
                throw ValidationException::withMessages(['option_index' => 'Escolha inválida.']);
            }

            return [($options[$index]['correct'] ?? false) ? ScreeningOutcome::Correct : ScreeningOutcome::Incorrect, null];
        }

        $allowed = $item->kind === ScreeningItemKind::Speech
            ? [ScreeningOutcome::Correct, ScreeningOutcome::Substitution, ScreeningOutcome::Omission, ScreeningOutcome::Distortion]
            : [ScreeningOutcome::Correct, ScreeningOutcome::Incorrect];

        $outcome = ScreeningOutcome::tryFrom((string) ($input['outcome'] ?? ''));

        if ($outcome === null || ! in_array($outcome, $allowed, true)) {
            throw ValidationException::withMessages(['outcome' => 'Resultado inválido para este tipo de item.']);
        }

        $produced = null;
        if ($outcome === ScreeningOutcome::Substitution) {
            $produced = trim((string) ($input['produced_sound'] ?? ''));

            if ($produced === '' || mb_strlen($produced) > 32) {
                throw ValidationException::withMessages(['produced_sound' => 'Indique o som produzido pela criança.']);
            }
        }

        return [$outcome, $produced];
    }

    /**
     * N failures in a row within one subsection close it: its remaining
     * items are stored as "Não aplicado / Sem pontuação", never silently
     * skipped, so the report can show exactly what was not presented.
     */
    private function applyStopRule(ScreeningSession $session, ScreeningItem $justAnswered): void
    {
        $limit = (int) config('screening.consecutive_errors_to_stop');

        if ($limit < 1 || ! in_array($justAnswered->kind->value, config('screening.stop_rule_kinds'), true)) {
            return;
        }

        $sameKind = $this->items($session)->filter(fn (ScreeningItem $i) => $i->kind === $justAnswered->kind)->values();
        $byItem = $session->responses()->get()->keyBy('screening_item_id');

        $streak = 0;
        foreach ($sameKind as $item) {
            $response = $byItem->get($item->id);

            if ($response === null || $response->outcome === ScreeningOutcome::NotApplied) {
                break;
            }

            $streak = $response->outcome === ScreeningOutcome::Correct ? 0 : $streak + 1;
        }

        if ($streak < $limit) {
            return;
        }

        foreach ($sameKind as $item) {
            if (! $byItem->has($item->id)) {
                $session->responses()->create([
                    'screening_item_id' => $item->id,
                    'outcome' => ScreeningOutcome::NotApplied,
                    'points' => null,
                    'answered_at' => now(),
                ]);
            }
        }
    }

    public function finalize(ScreeningSession $session): void
    {
        $session->update([
            'status' => 'completed',
            'completed_at' => now(),
            'result' => $this->scorer->summarize($session),
        ]);

        // The screening is complete and saved regardless of what happens to
        // the e-mail/PDF — a delivery problem is recorded, never lost data.
        $this->reports->generateAndSend($session->fresh());
    }
}

<?php

namespace App\Services\Screening;

use App\Enums\ScreeningItemKind;
use App\Models\ScreeningItem;
use Illuminate\Validation\ValidationException;

/**
 * Loads the clinic's item bank (JSON) for one organization, replacing that
 * organization's existing items of the same blocks. Validates every row —
 * a comprehension item must have a single correct option, a speech item a
 * known phoneme — so a typo is rejected at import, not discovered at a school.
 */
class ScreeningItemImporter
{
    public function import(int $organizationId, array $items, bool $demo = false): int
    {
        $this->validate($items);

        ScreeningItem::query()->where('organization_id', $organizationId)
            ->whereIn('age_block', array_unique(array_column($items, 'age_block')))
            ->delete();

        foreach ($items as $row) {
            ScreeningItem::create([
                'organization_id' => $organizationId,
                'age_block' => $row['age_block'],
                'kind' => $row['kind'],
                'position' => $row['position'],
                'prompt' => $row['prompt'],
                'target_word' => $row['target_word'] ?? null,
                'phoneme' => $row['phoneme'] ?? null,
                'options' => $row['options'] ?? null,
                'expected_answer' => $row['expected_answer'] ?? null,
                'is_demo' => $demo,
            ]);
        }

        return count($items);
    }

    private function validate(array $items): void
    {
        $errors = [];
        $blocks = array_map('strval', array_keys(config('screening.blocks')));
        $phonemes = array_keys(config('screening.phonemes'));

        foreach ($items as $i => $row) {
            $where = 'item #'.($i + 1);
            $kind = ScreeningItemKind::tryFrom($row['kind'] ?? '');

            if (! in_array((string) ($row['age_block'] ?? ''), $blocks, true)) {
                $errors[] = "{$where}: age_block inválido";
            }
            if ($kind === null) {
                $errors[] = "{$where}: kind inválido";

                continue;
            }
            if (blank($row['prompt'] ?? null) || ! is_int($row['position'] ?? null)) {
                $errors[] = "{$where}: prompt/position em falta";
            }
            if ($kind === ScreeningItemKind::Speech && (blank($row['target_word'] ?? null) || ! in_array($row['phoneme'] ?? '', $phonemes, true))) {
                $errors[] = "{$where}: fala exige target_word e um fonema conhecido";
            }
            if ($kind === ScreeningItemKind::Comprehension) {
                $options = $row['options'] ?? [];
                $correct = collect($options)->where('correct', true)->count();
                if (count($options) < 3 || count($options) > 4 || $correct !== 1) {
                    $errors[] = "{$where}: compreensão exige 3 ou 4 opções com exatamente uma correta";
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages(['items' => $errors]);
        }
    }
}

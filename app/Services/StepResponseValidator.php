<?php

namespace App\Services;

use App\Enums\ResponseType;
use App\Models\ActivityStep;
use Illuminate\Validation\ValidationException;

/**
 * Per-type validation and scoring for the seven response types, shared by
 * every caller that persists a step response (web + API, both go through
 * AttemptService::saveStep). Never trusts the shape the client sent —
 * choice answers are checked against the step's own configured option set,
 * never against whatever the request happened to include.
 */
class StepResponseValidator
{
    private const MAX_SHORT_TEXT_LENGTH = 5000;

    /**
     * @return array{value: mixed, is_correct: bool|null}
     */
    public function validateValue(ActivityStep $step, mixed $value): array
    {
        return match ($step->response_type) {
            ResponseType::SingleChoice => $this->validateSingleChoice($step, $value),
            ResponseType::MultipleChoice => $this->validateMultipleChoice($step, $value),
            ResponseType::ShortText => $this->validateShortText($step, $value),
            ResponseType::CompletionConfirmation => $this->validateCompletionConfirmation($step, $value),
            default => throw new \LogicException("validateValue() does not handle file-based response type {$step->response_type->value}."),
        };
    }

    private function validateSingleChoice(ActivityStep $step, mixed $value): array
    {
        $options = $step->options();

        if ($value === null || $value === '') {
            $this->rejectIfRequired($step, 'Escolhe uma opção.');

            return ['value' => null, 'is_correct' => null];
        }

        if (! is_string($value) || ! in_array($value, $options, true)) {
            throw ValidationException::withMessages(['value' => 'Opção inválida.']);
        }

        $correct = $step->correctAnswers();

        return ['value' => $value, 'is_correct' => $correct === [] ? null : in_array($value, $correct, true)];
    }

    private function validateMultipleChoice(ActivityStep $step, mixed $value): array
    {
        $options = $step->options();

        if ($value === null) {
            $value = [];
        }

        if (! is_array($value)) {
            throw ValidationException::withMessages(['value' => 'Resposta inválida.']);
        }

        $value = array_values(array_map('strval', $value));

        if (count($value) !== count(array_unique($value))) {
            throw ValidationException::withMessages(['value' => 'Opções repetidas.']);
        }

        foreach ($value as $option) {
            if (! in_array($option, $options, true)) {
                throw ValidationException::withMessages(['value' => 'Opção inválida.']);
            }
        }

        if ($value === []) {
            $this->rejectIfRequired($step, 'Escolhe pelo menos uma opção.');

            return ['value' => null, 'is_correct' => null];
        }

        $correct = $step->correctAnswers();
        // Set comparison, order-independent — sorted copies compared as
        // arrays rather than the raw `==`, which is positional for lists.
        $sortedValue = $value;
        sort($sortedValue);
        $sortedCorrect = $correct;
        sort($sortedCorrect);

        return ['value' => $value, 'is_correct' => $correct === [] ? null : $sortedValue === $sortedCorrect];
    }

    private function validateShortText(ActivityStep $step, mixed $value): array
    {
        $text = is_string($value) ? trim($value) : '';

        if ($text === '') {
            $this->rejectIfRequired($step, 'Escreve uma resposta.');

            return ['value' => null, 'is_correct' => null];
        }

        $maxLength = (int) ($step->response_config['max_length'] ?? self::MAX_SHORT_TEXT_LENGTH);

        if (mb_strlen($text) > $maxLength) {
            throw ValidationException::withMessages(['value' => "Resposta demasiado longa (máximo {$maxLength} caracteres)."]);
        }

        return ['value' => $text, 'is_correct' => null];
    }

    private function validateCompletionConfirmation(ActivityStep $step, mixed $value): array
    {
        if ($value === null || $value === false || $value === 'false') {
            $this->rejectIfRequired($step, 'Confirma que terminaste.');

            return ['value' => null, 'is_correct' => null];
        }

        if ($value !== true && $value !== 'true' && $value !== 1 && $value !== '1') {
            throw ValidationException::withMessages(['value' => 'Valor inválido.']);
        }

        return ['value' => true, 'is_correct' => null];
    }

    private function rejectIfRequired(ActivityStep $step, string $message): void
    {
        if ($step->isRequired()) {
            throw ValidationException::withMessages(['value' => $message]);
        }
    }
}

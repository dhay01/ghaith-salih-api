<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReservationRequest extends FormRequest
{
    /** Public endpoint — the portfolio never authenticates. */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'seats' => ['nullable', 'integer', 'min:1', 'max:6'],
            'locale' => ['nullable', 'string', Rule::in(array_keys(config('localization.supported')))],
        ];

        foreach ($this->questions() as $q) {
            $rules['answers.'.$q['id']] = $this->rulesForQuestion($q);

            if ($q['type'] === 'checkbox') {
                $rules['answers.'.$q['id'].'.*'] = [Rule::in($this->optionValues($q))];
            }
        }

        return $rules;
    }

    public function attributes(): array
    {
        return collect($this->questions())
            ->mapWithKeys(fn (array $q) => ['answers.'.$q['id'] => strtolower($q['label'])])
            ->all();
    }

    /**
     * Build rules from the question definition so validation can never drift
     * from the form the applicant actually saw.
     */
    private function rulesForQuestion(array $q): array
    {
        $rules = [$q['required'] ? 'required' : 'nullable'];

        return [...$rules, ...match ($q['type']) {
            'checkbox' => ['array', 'min:'.($q['required'] ? 1 : 0)],
            'radio' => ['string', Rule::in($this->optionValues($q))],
            'number' => ['integer', 'min:'.($q['min'] ?? 0), 'max:'.($q['max'] ?? 120)],
            'email' => ['email:rfc', 'max:255'],
            'tel' => ['string', 'max:32'],
            'textarea' => ['string', 'min:3', 'max:2000'],
            default => ['string', 'max:255'],
        }];
    }

    private function optionValues(array $q): array
    {
        return array_column($q['options'] ?? [], 'value');
    }

    /** @return array<int, array<string, mixed>> */
    public function questions(): array
    {
        return config('reservation_questions.'.config('reservation_questions.current'), []);
    }
}

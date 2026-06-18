<?php

namespace App\Http\Requests\Admin\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;

trait ValidatesTaskTimeEntryInput
{
    protected function prepareForValidation(): void
    {
        if ($this->filled('duration_minutes')) {
            $this->merge([
                'started_at' => null,
                'ended_at' => null,
            ]);
        }
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    protected function taskTimeEntryRules(): array
    {
        return [
            'duration_minutes' => [
                'nullable',
                'required_without_all:started_at,ended_at',
                'integer',
                'min:1',
                'max:'.(24 * 60),
            ],
            'started_at' => [
                'exclude_if:duration_minutes,*',
                'required_without:duration_minutes',
                'nullable',
                'date',
            ],
            'ended_at' => [
                'exclude_if:duration_minutes,*',
                'required_without:duration_minutes',
                'nullable',
                'date',
                'after:started_at',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:500',
            ],
        ];
    }
}

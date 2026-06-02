<?php

namespace App\Http\Requests\Admin\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Validator;

trait ValidatesTaskTimeEntryInput
{
    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    protected function taskTimeEntryRules(): array
    {
        return [
            'duration_minutes' => [
                'required_without_all:started_at,ended_at',
                'integer',
                'min:1',
                'max:'.(24 * 60),
            ],
            'started_at' => [
                'required_without:duration_minutes',
                'date',
            ],
            'ended_at' => [
                'required_without:duration_minutes',
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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $hasDuration = $this->filled('duration_minutes');
            $hasRange = $this->filled('started_at') || $this->filled('ended_at');

            if ($hasDuration && $hasRange) {
                $validator->errors()->add(
                    'duration_minutes',
                    __('Provide either duration or start/end times, not both.'),
                );
            }
        });
    }
}

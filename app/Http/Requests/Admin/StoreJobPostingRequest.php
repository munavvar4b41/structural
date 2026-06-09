<?php

namespace App\Http\Requests\Admin;

use App\Enums\JobEmploymentType;
use App\Enums\JobPostingStatus;
use App\Models\JobPosting;
use App\Models\Team;
use App\Support\TipTapDocument;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreJobPostingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', JobPosting::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if ($this->has('team_id') && $this->input('team_id') === '') {
            $merge['team_id'] = null;
        }

        foreach (['description', 'requirements'] as $field) {
            $value = $this->input($field);
            if (is_string($value) && $value !== '' && ! TipTapDocument::isValidDocumentJson($value)) {
                $merge[$field] = (string) json_encode([
                    'type' => 'doc',
                    'content' => [
                        [
                            'type' => 'paragraph',
                            'content' => [
                                ['type' => 'text', 'text' => $value],
                            ],
                        ],
                    ],
                ]);
            }
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    /**
     * @return array<string, array<int, ValidationRule|string|\Closure>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', 'unique:job_postings,slug'],
            'team_id' => ['nullable', 'integer', Rule::exists(Team::class, 'id')],
            'location' => ['required', 'string', 'max:255'],
            'employment_type' => ['required', 'string', Rule::in(JobEmploymentType::values())],
            'description' => [
                'nullable',
                'string',
                'max:65535',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === null || $value === '') {
                        return;
                    }
                    if (! is_string($value) || ! TipTapDocument::isValidDocumentJson($value)) {
                        $fail(__('The description must be valid rich text.'));
                    }
                },
            ],
            'requirements' => [
                'nullable',
                'string',
                'max:65535',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === null || $value === '') {
                        return;
                    }
                    if (! is_string($value) || ! TipTapDocument::isValidDocumentJson($value)) {
                        $fail(__('The requirements must be valid rich text.'));
                    }
                },
            ],
            'status' => ['required', 'string', Rule::in(JobPostingStatus::values())],
            'published_at' => ['nullable', 'date'],
            'closes_at' => ['nullable', 'date', 'after_or_equal:published_at'],
        ];
    }
}

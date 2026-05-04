<?php

namespace App\Http\Requests\Admin;

use App\Models\ProjectRequirement;
use App\Support\ProjectRequirementAssignableUsers;
use App\Support\TipTapDocument;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class MarkProjectRequirementReviewedRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ProjectRequirement|null $requirement */
        $requirement = $this->route('requirement');

        if (! $requirement instanceof ProjectRequirement) {
            return false;
        }

        return $this->user()?->can('markReviewed', $requirement) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        $raw = $this->input('review_understanding');
        if (is_string($raw) && $raw !== '' && ! TipTapDocument::isValidDocumentJson($raw)) {
            $merge['review_understanding'] = (string) json_encode([
                'type' => 'doc',
                'content' => [
                    [
                        'type' => 'paragraph',
                        'content' => [
                            ['type' => 'text', 'text' => $raw],
                        ],
                    ],
                ],
            ]);
        }
        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'review_understanding' => [
                'required',
                'string',
                'max:'.ProjectRequirementAssignableUsers::DESCRIPTION_MAX_LENGTH,
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_string($value) || ! TipTapDocument::isSubstantiveDocumentJson($value)) {
                        $fail(__('Enter your understanding of this requirement using the editor.'));
                    }
                },
            ],
        ];
    }
}

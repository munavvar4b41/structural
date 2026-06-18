<?php

namespace App\Http\Requests\Admin;

use App\Models\ProjectProposal;
use App\Support\ProjectRequirementAssignableUsers;
use App\Support\TipTapDocument;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectProposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ProjectProposal|null $proposal */
        $proposal = $this->route('proposal');

        if (! $proposal instanceof ProjectProposal) {
            return false;
        }

        return $this->user()?->can('update', $proposal) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $desc = $this->input('description');
        if (is_string($desc) && $desc !== '' && ! TipTapDocument::isValidDocumentJson($desc)) {
            $this->merge([
                'description' => (string) json_encode([
                    'type' => 'doc',
                    'content' => [
                        [
                            'type' => 'paragraph',
                            'content' => [
                                ['type' => 'text', 'text' => $desc],
                            ],
                        ],
                    ],
                ]),
            ]);
        }
    }

    /**
     * @return array<string, array<int, ValidationRule|string|\Closure>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => [
                'required',
                'string',
                'max:'.ProjectRequirementAssignableUsers::DESCRIPTION_MAX_LENGTH,
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_string($value) || ! TipTapDocument::isValidDocumentJson($value)) {
                        $fail(__('The description must be valid rich text.'));
                    }
                },
            ],
        ];
    }
}

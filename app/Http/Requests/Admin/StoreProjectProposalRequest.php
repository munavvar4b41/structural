<?php

namespace App\Http\Requests\Admin;

use App\Models\Project;
use App\Models\ProjectProposal;
use App\Support\ProjectRequirementAssignableUsers;
use App\Support\TipTapDocument;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectProposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Project|null $project */
        $project = $this->route('project');

        if (! $project instanceof Project) {
            return false;
        }

        return $this->user()?->can('create', [ProjectProposal::class, $project]) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if ($this->has('project_requirement_id') && $this->input('project_requirement_id') === '') {
            $merge['project_requirement_id'] = null;
        }

        $desc = $this->input('description');
        if (is_string($desc) && $desc !== '' && ! TipTapDocument::isValidDocumentJson($desc)) {
            $merge['description'] = (string) json_encode([
                'type' => 'doc',
                'content' => [
                    [
                        'type' => 'paragraph',
                        'content' => [
                            ['type' => 'text', 'text' => $desc],
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
     * @return array<string, array<int, ValidationRule|string|\Closure>>
     */
    public function rules(): array
    {
        /** @var Project $project */
        $project = $this->route('project');

        $requirementIds = $project->requirements()->pluck('id')->all();

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
            'project_requirement_id' => ['nullable', 'integer', Rule::in($requirementIds)],
        ];
    }
}

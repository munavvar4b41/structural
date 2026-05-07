<?php

namespace App\Http\Requests\Admin;

use App\Models\Project;
use App\Support\ProjectRequirementAssignableUsers;
use App\Support\TipTapDocument;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectRequirementRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Project|null $project */
        $project = $this->route('project');

        if (! $project instanceof Project) {
            return false;
        }

        return $this->user()?->can('create', [ProjectRequirement::class, $project]) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        if ($this->has('responsible_user_id') && $this->input('responsible_user_id') === '') {
            $merge['responsible_user_id'] = null;
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
        $project->loadMissing('teams');

        $allowedResponsibleIds = ProjectRequirementAssignableUsers::responsibleUserIds($project);

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => [
                'nullable',
                'string',
                'max:'.ProjectRequirementAssignableUsers::DESCRIPTION_MAX_LENGTH,
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === null || $value === '') {
                        return;
                    }
                    if (! is_string($value) || ! TipTapDocument::isValidDocumentJson($value)) {
                        $fail(__('The description must be valid rich text.'));
                    }
                },
            ],
            'responsible_user_id' => ['nullable', 'integer', Rule::in($allowedResponsibleIds)],
        ];
    }
}

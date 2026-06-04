<?php

namespace App\Http\Requests\Admin;

use App\Models\Project;
use App\Models\ProjectTag;
use App\Support\ProjectTagNormalizer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Project|null $project */
        $project = $this->route('project');

        if (! $project instanceof Project) {
            return false;
        }

        return $this->user()?->can('manageTags', $project) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $name = $this->input('name');

        if (is_string($name)) {
            $this->merge(['name' => ProjectTagNormalizer::normalize($name)]);
        }
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        /** @var Project $project */
        $project = $this->route('project');

        return [
            'name' => [
                'required',
                'string',
                'max:64',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_string($value) || ! ProjectTagNormalizer::isValid($value)) {
                        $fail(__('The tag must use lowercase letters, numbers, and hyphens only.'));
                    }
                },
                Rule::unique(ProjectTag::class, 'name')->where('project_id', $project->id),
            ],
        ];
    }
}

<?php

namespace App\Http\Requests\Admin;

use App\Models\Project;
use App\Models\ProjectMetadata;
use App\Support\ProjectMetadataNormalizer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectMetadataRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Project|null $project */
        $project = $this->route('project');

        if (! $project instanceof Project) {
            return false;
        }

        return $this->user()?->can('manageMetadata', $project) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if ($this->has('key') && is_string($this->input('key'))) {
            $merge['key'] = ProjectMetadataNormalizer::normalizeKey($this->input('key'));
        }

        if ($this->has('value') && is_string($this->input('value'))) {
            $merge['value'] = ProjectMetadataNormalizer::normalizeValue($this->input('value'));
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
        /** @var Project $project */
        $project = $this->route('project');

        return [
            'key' => [
                'required',
                'string',
                'max:128',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_string($value) || ! ProjectMetadataNormalizer::isValidKey($value)) {
                        $fail(__('The metadata key is invalid.'));
                    }
                },
                Rule::unique(ProjectMetadata::class, 'key')->where('project_id', $project->id),
            ],
            'value' => [
                'required',
                'string',
                'max:500',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_string($value) || ! ProjectMetadataNormalizer::isValidValue($value)) {
                        $fail(__('The metadata value is invalid.'));
                    }
                },
            ],
        ];
    }
}

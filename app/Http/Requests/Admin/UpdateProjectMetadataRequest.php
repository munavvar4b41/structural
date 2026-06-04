<?php

namespace App\Http\Requests\Admin;

use App\Models\Project;
use App\Models\ProjectMetadata;
use App\Support\ProjectMetadataNormalizer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectMetadataRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Project|null $project */
        $project = $this->route('project');
        /** @var ProjectMetadata|null $metadata */
        $metadata = $this->route('metadata');

        if (! $project instanceof Project || ! $metadata instanceof ProjectMetadata) {
            return false;
        }

        if ($metadata->project_id !== $project->id) {
            return false;
        }

        return $this->user()?->can('manageMetadata', $project) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('value') && is_string($this->input('value'))) {
            $this->merge(['value' => ProjectMetadataNormalizer::normalizeValue($this->input('value'))]);
        }
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
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

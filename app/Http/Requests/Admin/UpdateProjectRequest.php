<?php

namespace App\Http\Requests\Admin;

use App\Models\Project;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var Project|null $project */
        $project = $this->route('project');

        if (! $project instanceof Project) {
            return false;
        }

        return $this->user()?->can('update', $project) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        /** @var Project $project */
        $project = $this->route('project');

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', Rule::unique(Project::class)->ignore($project->id)],
            'description' => ['nullable', 'string', 'max:1000'],
            'team_ids' => ['required', 'array', 'min:1'],
            'team_ids.*' => ['required', 'integer', Rule::exists(Team::class, 'id')],
        ];
    }
}

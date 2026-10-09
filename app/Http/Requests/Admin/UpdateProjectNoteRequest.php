<?php

namespace App\Http\Requests\Admin;

use App\Models\Project;
use App\Models\ProjectNote;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Project|null $project */
        $project = $this->route('project');
        /** @var ProjectNote|null $note */
        $note = $this->route('note');

        if (! $project instanceof Project || ! $note instanceof ProjectNote) {
            return false;
        }

        if ($note->project_id !== $project->id) {
            abort(404);
        }

        return $this->user()?->can('update', $note) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if ($this->has('title') && is_string($this->input('title'))) {
            $merge['title'] = trim($this->input('title'));
        }

        if ($this->has('body') && is_string($this->input('body'))) {
            $merge['body'] = trim($this->input('body'));
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
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
        ];
    }
}

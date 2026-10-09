<?php

namespace App\Http\Requests\Admin;

use App\Models\Project;
use App\Models\ProjectNote;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreProjectNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Project|null $project */
        $project = $this->route('project');

        if (! $project instanceof Project) {
            return false;
        }

        return $this->user()?->can('create', [ProjectNote::class, $project]) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge($this->trimmedNoteFields());
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return $this->noteRules();
    }

    /**
     * @return array<string, string>
     */
    private function trimmedNoteFields(): array
    {
        $merge = [];

        if ($this->has('title') && is_string($this->input('title'))) {
            $merge['title'] = trim($this->input('title'));
        }

        if ($this->has('body') && is_string($this->input('body'))) {
            $merge['body'] = trim($this->input('body'));
        }

        return $merge;
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    private function noteRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
        ];
    }
}

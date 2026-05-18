<?php

namespace App\Http\Requests\Admin;

use App\Models\ProjectTask;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectTaskChecklistItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        $task = $this->route('task');

        if (! $task instanceof ProjectTask) {
            return false;
        }

        return $this->user()?->can('update', $task) ?? false;
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:500'],
            'is_completed' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if (! $this->hasAny(['title', 'is_completed'])) {
                $validator->errors()->add(
                    'title',
                    __('At least one field must be provided.'),
                );
            }
        });
    }
}

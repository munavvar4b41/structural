<?php

namespace App\Http\Requests\Admin;

use App\Models\ProjectTask;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreProjectTaskChecklistItemRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:500'],
        ];
    }
}

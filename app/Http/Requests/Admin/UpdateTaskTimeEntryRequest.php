<?php

namespace App\Http\Requests\Admin;

use App\Models\TaskTimeEntry;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskTimeEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $entry = $this->route('time_entry');

        if (! $entry instanceof TaskTimeEntry) {
            return false;
        }

        return $this->user()?->can('update', $entry) ?? false;
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'started_at' => ['required', 'date'],
            'ended_at' => ['required', 'date', 'after:started_at'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}

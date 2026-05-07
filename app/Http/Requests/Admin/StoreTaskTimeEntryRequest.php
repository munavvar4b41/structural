<?php

namespace App\Http\Requests\Admin;

use App\Models\ProjectTask;
use App\Models\TaskTimeEntry;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreTaskTimeEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $task = $this->route('task');

        if (! $task instanceof ProjectTask) {
            return false;
        }

        return $this->user()?->can('create', [TaskTimeEntry::class, $task]) ?? false;
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

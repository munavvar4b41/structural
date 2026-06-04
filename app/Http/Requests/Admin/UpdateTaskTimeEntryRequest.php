<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\ValidatesTaskTimeEntryInput;
use App\Models\TaskTimeEntry;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskTimeEntryRequest extends FormRequest
{
    use ValidatesTaskTimeEntryInput;

    public function authorize(): bool
    {
        $entry = $this->route('time_entry');

        if (! $entry instanceof TaskTimeEntry) {
            return false;
        }

        return $this->user()?->can('update', $entry) ?? false;
    }

    public function rules(): array
    {
        return $this->taskTimeEntryRules();
    }
}

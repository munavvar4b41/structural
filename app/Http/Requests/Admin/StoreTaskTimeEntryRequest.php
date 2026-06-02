<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\ValidatesTaskTimeEntryInput;
use App\Models\ProjectTask;
use App\Models\TaskTimeEntry;
use Illuminate\Foundation\Http\FormRequest;

class StoreTaskTimeEntryRequest extends FormRequest
{
    use ValidatesTaskTimeEntryInput;

    public function authorize(): bool
    {
        $task = $this->route('task');

        if (! $task instanceof ProjectTask) {
            return false;
        }

        return $this->user()?->can('create', [TaskTimeEntry::class, $task]) ?? false;
    }

    public function rules(): array
    {
        return $this->taskTimeEntryRules();
    }
}

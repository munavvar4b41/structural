<?php

namespace App\Http\Requests\Admin;

use App\Enums\TaskTagColor;
use App\Enums\TaskTagShade;
use App\Models\TaskTag;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var TaskTag|null $taskTag */
        $taskTag = $this->route('task_tag');

        if (! $taskTag instanceof TaskTag) {
            return false;
        }

        return $this->user()?->can('update', $taskTag) ?? false;
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        /** @var TaskTag $taskTag */
        $taskTag = $this->route('task_tag');

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique(TaskTag::class)->ignore($taskTag->id)],
            'color' => ['required', Rule::enum(TaskTagColor::class)],
            'shade' => ['required', Rule::enum(TaskTagShade::class)],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
        ];
    }
}

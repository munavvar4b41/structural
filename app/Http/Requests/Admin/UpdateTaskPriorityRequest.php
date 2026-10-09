<?php

namespace App\Http\Requests\Admin;

use App\Enums\TaskPriorityColor;
use App\Enums\TaskPriorityShade;
use App\Models\TaskPriority;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskPriorityRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var TaskPriority|null $taskPriority */
        $taskPriority = $this->route('task_priority');

        if (! $taskPriority instanceof TaskPriority) {
            return false;
        }

        return $this->user()?->can('update', $taskPriority) ?? false;
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        /** @var TaskPriority $taskPriority */
        $taskPriority = $this->route('task_priority');

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique(TaskPriority::class)->ignore($taskPriority->id)],
            'color' => ['required', Rule::enum(TaskPriorityColor::class)],
            'shade' => ['required', Rule::enum(TaskPriorityShade::class)],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
        ];
    }
}

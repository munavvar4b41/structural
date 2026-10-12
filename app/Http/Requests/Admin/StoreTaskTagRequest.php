<?php

namespace App\Http\Requests\Admin;

use App\Enums\TaskPriorityColor;
use App\Enums\TaskPriorityShade;
use App\Models\TaskPriority;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskPriorityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', TaskPriority::class) ?? false;
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique(TaskPriority::class)],
            'color' => ['required', Rule::enum(TaskPriorityColor::class)],
            'shade' => ['required', Rule::enum(TaskPriorityShade::class)],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
        ];
    }
}

<?php

namespace App\Http\Requests\Admin;

use App\Enums\TaskTagColor;
use App\Enums\TaskTagShade;
use App\Models\TaskTag;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', TaskTag::class) ?? false;
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique(TaskTag::class)],
            'color' => ['required', Rule::enum(TaskTagColor::class)],
            'shade' => ['required', Rule::enum(TaskTagShade::class)],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
        ];
    }
}

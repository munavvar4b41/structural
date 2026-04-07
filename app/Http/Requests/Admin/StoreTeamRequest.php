<?php

namespace App\Http\Requests\Admin;

use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Team::class) ?? false;
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique(Team::class)],
            'code' => ['nullable', 'string', 'max:50', Rule::unique(Team::class)],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}

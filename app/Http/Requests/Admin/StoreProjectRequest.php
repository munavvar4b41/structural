<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Project::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', Rule::unique(Project::class)],
            'description' => ['nullable', 'string', 'max:1000'],
            'client_user_id' => [
                'required',
                'integer',
                Rule::exists(User::class, 'id')->where('role', UserRole::Client->value),
            ],
            'team_ids' => ['required', 'array', 'min:1'],
            'team_ids.*' => ['required', 'integer', Rule::exists(Team::class, 'id')],
        ];
    }
}

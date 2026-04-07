<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', User::class) ?? false;
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        /** @var User $actor */
        $actor = $this->user();
        $allowedRoles = UserRole::assignableRoleValuesForActor($actor);

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'role' => ['required', 'string', Rule::in($allowedRoles)],
            'primary_team_id' => ['required', 'integer', Rule::exists(Team::class, 'id')],
            'team_ids' => ['nullable', 'array'],
            'team_ids.*' => ['integer', 'distinct', Rule::exists(Team::class, 'id')],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $primaryTeamId = (int) $this->input('primary_team_id');
                $teamIds = collect($this->input('team_ids', []))
                    ->map(static fn (mixed $value): int => (int) $value)
                    ->all();

                if (! in_array($primaryTeamId, $teamIds, true)) {
                    $validator->errors()->add(
                        'team_ids',
                        __('Primary team must be included in assigned teams.'),
                    );
                }
            },
        ];
    }
}

<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->route('user');

        if (! $user instanceof User) {
            return false;
        }

        return $this->user()?->can('update', $user) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('password') === '') {
            $this->merge(['password' => null]);
        }
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        /** @var User $actor */
        $actor = $this->user();
        /** @var User $user */
        $user = $this->route('user');
        $allowedRoles = UserRole::assignableRoleValuesForActor($actor);

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
            'password' => ['nullable', 'string', 'confirmed', Password::defaults()],
            'role' => ['required', 'string', Rule::in($allowedRoles)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var User|null $user */
            $user = $this->route('user');

            if (! $user instanceof User) {
                return;
            }

            $role = UserRole::tryFrom((string) $this->input('role'));

            if ($role === null) {
                return;
            }

            if ($user->role === UserRole::SuperAdmin && $role !== UserRole::SuperAdmin) {
                $count = User::query()->where('role', UserRole::SuperAdmin)->count();

                if ($count <= 1) {
                    $validator->errors()->add(
                        'role',
                        __('Cannot demote the last super admin.'),
                    );
                }
            }
        });
    }
}

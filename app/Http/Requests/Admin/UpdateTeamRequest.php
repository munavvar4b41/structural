<?php

namespace App\Http\Requests\Admin;

use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Team|null $team */
        $team = $this->route('team');

        if (! $team instanceof Team) {
            return false;
        }

        return $this->user()?->can('update', $team) ?? false;
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        /** @var Team $team */
        $team = $this->route('team');

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique(Team::class)->ignore($team->id)],
            'code' => ['nullable', 'string', 'max:50', Rule::unique(Team::class)->ignore($team->id)],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}

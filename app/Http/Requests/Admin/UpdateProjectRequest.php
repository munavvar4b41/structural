<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProjectRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var Project|null $project */
        $project = $this->route('project');

        if (! $project instanceof Project) {
            return false;
        }

        return $this->user()?->can('update', $project) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('lead_user_id') && $this->input('lead_user_id') === '') {
            $this->merge(['lead_user_id' => null]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        /** @var Project $project */
        $project = $this->route('project');

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', Rule::unique(Project::class)->ignore($project->id)],
            'description' => ['nullable', 'string', 'max:1000'],
            'client_user_id' => [
                'required',
                'integer',
                Rule::exists(User::class, 'id')->where('role', UserRole::Client->value),
            ],
            'team_ids' => ['required', 'array', 'min:1'],
            'team_ids.*' => ['required', 'integer', Rule::exists(Team::class, 'id')],
            'lead_user_id' => ['nullable', 'integer', Rule::exists(User::class, 'id')],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $leadId = $this->input('lead_user_id');
                if ($leadId === null || $leadId === '') {
                    return;
                }

                $leadId = (int) $leadId;
                $teamIds = collect($this->input('team_ids', []))
                    ->map(static fn (mixed $value): int => (int) $value)
                    ->unique()
                    ->values()
                    ->all();

                $user = User::query()->find($leadId);
                if ($user === null) {
                    return;
                }

                if (! in_array($user->role, [UserRole::TeamHead, UserRole::Staff], true)) {
                    $validator->errors()->add(
                        'lead_user_id',
                        __('The project lead must be a team head or staff member.'),
                    );

                    return;
                }

                $userTeamIds = $user->teams()->pluck('teams.id')->all();
                if (count(array_intersect($teamIds, $userTeamIds)) === 0) {
                    $validator->errors()->add(
                        'lead_user_id',
                        __('The project lead must belong to at least one assigned team.'),
                    );
                }
            },
        ];
    }
}

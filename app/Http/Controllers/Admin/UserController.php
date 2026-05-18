<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $actor = $request->user();

        $search = trim((string) $request->query('search', ''));
        $roleQuery = $request->query('role');
        $roleFilter = is_string($roleQuery) ? $roleQuery : '';
        $teamQuery = $request->query('team_id');
        $verifiedQuery = $request->query('verified');
        $verifiedFilter = is_string($verifiedQuery) ? $verifiedQuery : '';

        $assignableRoleValues = $actor !== null
            ? UserRole::assignableRoleValuesForActor($actor)
            : [];

        $roleApplied = $roleFilter !== '' && in_array($roleFilter, $assignableRoleValues, true);

        $teamId = null;
        if ($teamQuery !== null && $teamQuery !== '') {
            $tid = (int) $teamQuery;
            $teamId = $tid > 0 ? $tid : null;
        }

        $verifiedEffective = in_array($verifiedFilter, ['verified', 'unverified'], true)
            ? $verifiedFilter
            : '';

        $users = User::query()
            ->when($actor !== null, static fn ($query) => $query->whereKeyNot($actor->id))
            ->when($search !== '', static function ($query) use ($search): void {
                $term = '%'.addcslashes($search, '%_\\').'%';
                $query->where(static function ($query) use ($term): void {
                    $query->where('name', 'like', $term)
                        ->orWhere('email', 'like', $term);
                });
            })
            ->when($roleApplied, static fn ($query) => $query->where('role', $roleFilter))
            ->when($teamId !== null, static function ($query) use ($teamId): void {
                $query->whereHas('teams', static function ($query) use ($teamId): void {
                    $query->where('teams.id', $teamId);
                });
            })
            ->when($verifiedEffective === 'verified', static fn ($query) => $query->whereNotNull('email_verified_at'))
            ->when($verifiedEffective === 'unverified', static fn ($query) => $query->whereNull('email_verified_at'))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(static fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            ]);

        $roleOptions = collect($assignableRoleValues)
            ->map(static function (string $value): array {
                $enum = UserRole::from($value);

                return [
                    'value' => $value,
                    'label' => $enum->label(),
                ];
            })
            ->values()
            ->all();

        return Inertia::render('admin/users/Index', [
            'users' => $users,
            'filters' => [
                'search' => $search,
                'role' => $roleApplied ? $roleFilter : '',
                'team_id' => $teamId !== null ? (string) $teamId : '',
                'verified' => $verifiedEffective,
            ],
            'filter_options' => [
                'roles' => $roleOptions,
                'teams' => $this->teamsPayload(),
                'verified' => [
                    ['value' => 'verified', 'label' => 'Verified email'],
                    ['value' => 'unverified', 'label' => 'Unverified email'],
                ],
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', User::class);

        return Inertia::render('admin/users/Create', [
            'assignableRoles' => $this->assignableRolesPayload($request),
            'teams' => $this->teamsPayload(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $payload = $request->validated();
        $teamIds = $this->normalizedTeamIds($payload);

        $user = User::query()->create($payload);
        $user->teams()->sync($teamIds);

        return to_route('admin.users.index')->with('toast', 'User created.');
    }

    public function edit(Request $request, User $user): Response
    {
        $this->authorize('update', $user);

        return Inertia::render('admin/users/Edit', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
                'primary_team_id' => $user->primary_team_id,
                'team_ids' => $user->teams()->pluck('teams.id')->all(),
            ],
            'assignableRoles' => $this->assignableRolesPayload($request),
            'teams' => $this->teamsPayload(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $payload = $request->validated();

        if (empty($payload['password'])) {
            unset($payload['password']);
        }

        unset($payload['password_confirmation']);
        $teamIds = $this->normalizedTeamIds($payload);

        $user->update($payload);
        $user->teams()->sync($teamIds);

        return to_route('admin.users.index')->with('toast', 'User updated.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        $user->delete();

        return to_route('admin.users.index')->with('toast', 'User deleted.');
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function assignableRolesPayload(Request $request): array
    {
        $actor = $request->user();

        if ($actor === null) {
            return [];
        }

        return collect(UserRole::assignableRolesForActor($actor))
            ->map(static fn (UserRole $role): array => [
                'value' => $role->value,
                'label' => $role->label(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    private function teamsPayload(): array
    {
        return Team::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(static fn (Team $team): array => [
                'value' => $team->id,
                'label' => $team->name,
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<int>
     */
    private function normalizedTeamIds(array &$payload): array
    {
        $teamIds = collect($payload['team_ids'] ?? [])
            ->map(static fn (mixed $value): int => (int) $value)
            ->unique()
            ->values();
        $primaryTeamId = (int) $payload['primary_team_id'];

        if (! $teamIds->contains($primaryTeamId)) {
            $teamIds->push($primaryTeamId);
        }

        unset($payload['team_ids']);

        return $teamIds->all();
    }
}

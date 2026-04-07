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

        $users = User::query()
            ->orderBy('name')
            ->paginate(15)
            ->through(static fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            ]);

        return Inertia::render('admin/users/Index', [
            'users' => $users,
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

        return to_route('admin.users.index');
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

        return to_route('admin.users.index');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        $user->delete();

        return to_route('admin.users.index');
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

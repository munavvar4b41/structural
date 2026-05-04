<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTeamRequest;
use App\Http\Requests\Admin\UpdateTeamRequest;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Team::class);

        $teams = Team::query()
            ->withCount('users')
            ->orderBy('name')
            ->paginate(15)
            ->through(static fn (Team $team): array => [
                'id' => $team->id,
                'name' => $team->name,
                'code' => $team->code,
                'description' => $team->description,
                'users_count' => $team->users_count,
            ]);

        return Inertia::render('admin/teams/Index', [
            'teams' => $teams,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Team::class);

        return Inertia::render('admin/teams/Create');
    }

    public function store(StoreTeamRequest $request): RedirectResponse
    {
        Team::query()->create($request->validated());

        return to_route('admin.teams.index')->with('toast', 'Team created.');
    }

    public function edit(Team $team): Response
    {
        $this->authorize('update', $team);

        return Inertia::render('admin/teams/Edit', [
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
                'code' => $team->code,
                'description' => $team->description,
            ],
        ]);
    }

    public function update(UpdateTeamRequest $request, Team $team): RedirectResponse
    {
        $team->update($request->validated());

        return to_route('admin.teams.index')->with('toast', 'Team updated.');
    }

    public function destroy(Team $team): RedirectResponse
    {
        $this->authorize('delete', $team);

        if (User::query()->where('primary_team_id', $team->id)->exists()) {
            return back()->withErrors([
                'team' => __('This team cannot be deleted because it is assigned as a primary team.'),
            ]);
        }

        $team->users()->detach();
        $team->delete();

        return to_route('admin.teams.index')->with('toast', 'Team deleted.');
    }
}

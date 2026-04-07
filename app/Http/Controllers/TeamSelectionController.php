<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\SelectPrimaryTeamRequest;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TeamSelectionController extends Controller
{
    public function create(): Response|RedirectResponse
    {
        $user = request()->user();

        if ($user === null) {
            return to_route('login');
        }

        if (in_array($user->role, [UserRole::SuperAdmin, UserRole::Admin, UserRole::Client], true)) {
            return to_route('dashboard');
        }

        if ($user->primary_team_id !== null) {
            return to_route('dashboard');
        }

        $teams = Team::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(static fn (Team $team): array => [
                'value' => $team->id,
                'label' => $team->name,
            ])
            ->all();

        return Inertia::render('auth/SelectTeam', [
            'teams' => $teams,
        ]);
    }

    public function store(SelectPrimaryTeamRequest $request): RedirectResponse
    {
        $user = $request->user();

        if ($user === null) {
            return to_route('login');
        }

        $teamId = (int) $request->validated('primary_team_id');

        $user->teams()->syncWithoutDetaching([$teamId]);
        $user->update(['primary_team_id' => $teamId]);

        return to_route('dashboard');
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProjectRequest;
use App\Http\Requests\Admin\UpdateProjectRequest;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Project::class);

        $actor = $request->user();

        abort_if(! $actor instanceof User, 403);

        $projects = Project::query()->with('clientUser');

        if ($actor->isClient()) {
            $projects->where('client_user_id', $actor->id);
        } elseif (! $actor->role->canViewAllProjects()) {
            $projects->whereHas('teams.users', static fn ($query) => $query->whereKey($actor->id));
        }

        $projects = $projects
            ->withCount('teams')
            ->orderBy('name')
            ->paginate(15)
            ->through(static function (Project $project): array {
                $client = $project->clientUser;

                return [
                    'id' => $project->id,
                    'name' => $project->name,
                    'code' => $project->code,
                    'description' => $project->description,
                    'teams_count' => $project->teams_count,
                    'client_user' => $client === null ? null : [
                        'id' => $client->id,
                        'name' => $client->name,
                        'email' => $client->email,
                    ],
                ];
            });

        return Inertia::render('admin/projects/Index', [
            'projects' => $projects,
            'canManageProjects' => $actor->canManageProjects(),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Project::class);

        return Inertia::render('admin/projects/Create', [
            'teams' => $this->teamsPayload(),
            'clients' => $this->clientsPayload(),
            'lead_candidates' => $this->leadCandidatesPayload(),
        ]);
    }

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $payload = $request->validated();
        $teamIds = $this->normalizedTeamIds($payload);

        $project = Project::query()->create($payload);
        $project->teams()->sync($teamIds);
        $project->refresh();

        if ($project->lead_user_id === null) {
            $defaultLeadId = $project->defaultTeamHeadUserId();
            if ($defaultLeadId !== null) {
                $project->update(['lead_user_id' => $defaultLeadId]);
            }
        }

        return to_route('admin.projects.index')->with('toast', 'Project created.');
    }

    public function edit(Project $project): Response
    {
        $this->authorize('update', $project);

        return Inertia::render('admin/projects/Edit', [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'code' => $project->code,
                'description' => $project->description,
                'client_user_id' => $project->client_user_id,
                'lead_user_id' => $project->lead_user_id,
                'team_ids' => $project->teams()->pluck('teams.id')->all(),
            ],
            'teams' => $this->teamsPayload(),
            'clients' => $this->clientsPayload(),
            'lead_candidates' => $this->leadCandidatesPayload(),
        ]);
    }

    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        $payload = $request->validated();
        $teamIds = $this->normalizedTeamIds($payload);

        $project->update($payload);
        $project->teams()->sync($teamIds);
        $project->refresh();

        if ($project->lead_user_id === null) {
            $defaultLeadId = $project->defaultTeamHeadUserId();
            if ($defaultLeadId !== null) {
                $project->update(['lead_user_id' => $defaultLeadId]);
            }
        }

        return to_route('admin.projects.index')->with('toast', 'Project updated.');
    }

    public function destroy(Project $project): RedirectResponse
    {
        $this->authorize('delete', $project);

        $project->teams()->detach();
        $project->delete();

        return to_route('admin.projects.index')->with('toast', 'Project deleted.');
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
     * @return list<array{value: int, label: string}>
     */
    private function clientsPayload(): array
    {
        return User::query()
            ->where('role', UserRole::Client)
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(static fn (User $user): array => [
                'value' => $user->id,
                'label' => $user->name.' ('.$user->email.')',
            ])
            ->all();
    }

    /**
     * Project leads: team heads or staff on at least one team (team_ids for UI filtering by assignment).
     *
     * @return list<array{value: int, label: string, team_ids: list<int>}>
     */
    private function leadCandidatesPayload(): array
    {
        return User::query()
            ->whereIn('role', [UserRole::TeamHead, UserRole::Staff])
            ->with(['teams:id'])
            ->orderBy('name')
            ->get()
            ->map(static function (User $user): array {
                return [
                    'value' => $user->id,
                    'label' => $user->name.' ('.$user->email.')',
                    'team_ids' => $user->teams->pluck('id')->map(static fn (int $id): int => $id)->values()->all(),
                ];
            })
            ->unique('value')
            ->values()
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

        unset($payload['team_ids']);

        return $teamIds->all();
    }
}

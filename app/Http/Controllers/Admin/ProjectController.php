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
            $projects->whereHas('teams.users', static fn($query) => $query->whereKey($actor->id));
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
        ]);
    }

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $payload = $request->validated();
        $teamIds = $this->normalizedTeamIds($payload);

        $project = Project::query()->create($payload);
        $project->teams()->sync($teamIds);

        return to_route('admin.projects.index');
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
                'team_ids' => $project->teams()->pluck('teams.id')->all(),
            ],
            'teams' => $this->teamsPayload(),
            'clients' => $this->clientsPayload(),
        ]);
    }

    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        $payload = $request->validated();
        $teamIds = $this->normalizedTeamIds($payload);

        $project->update($payload);
        $project->teams()->sync($teamIds);

        return to_route('admin.projects.index');
    }

    public function destroy(Project $project): RedirectResponse
    {
        $this->authorize('delete', $project);

        $project->teams()->detach();
        $project->delete();

        return to_route('admin.projects.index');
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    private function teamsPayload(): array
    {
        return Team::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(static fn(Team $team): array => [
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
            ->map(static fn(User $user): array => [
                'value' => $user->id,
                'label' => $user->name . ' (' . $user->email . ')',
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
            ->map(static fn(mixed $value): int => (int) $value)
            ->unique()
            ->values();

        unset($payload['team_ids']);

        return $teamIds->all();
    }
}

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

        $search = trim((string) $request->query('search', ''));
        $teamQuery = $request->query('team_id');
        $leadQuery = $request->query('lead_user_id');

        $teamId = null;
        if ($teamQuery !== null && $teamQuery !== '') {
            $tid = (int) $teamQuery;
            $teamId = $tid > 0 ? $tid : null;
        }

        $leadUserId = null;
        if ($leadQuery !== null && $leadQuery !== '') {
            $lid = (int) $leadQuery;
            $leadUserId = $lid > 0 ? $lid : null;
        }

        $allowedTeamIds = $actor->role->canViewAllProjects()
            ? null
            : $actor->teams()->pluck('teams.id')->all();

        if ($teamId !== null && $allowedTeamIds !== null && ! in_array($teamId, $allowedTeamIds, true)) {
            $teamId = null;
        }

        $leadCandidateIds = collect($this->leadCandidatesPayload())
            ->pluck('value')
            ->all();

        if ($leadUserId !== null
            && ! in_array($leadUserId, $leadCandidateIds, true)
        ) {
            $leadUserId = null;
        }

        if ($leadUserId !== null && ! $actor->role->canViewAllProjects()) {
            $leadUserId = null;
        }

        $projects = Project::query()->with('clientUser');

        $projects->visibleToUser($actor);

        $projects = $projects
            ->when($search !== '', static function ($query) use ($search): void {
                $term = '%'.addcslashes($search, '%_\\').'%';
                $query->where(static function ($query) use ($term): void {
                    $query->where('projects.name', 'like', $term)
                        ->orWhere('projects.code', 'like', $term)
                        ->orWhere('projects.description', 'like', $term);
                });
            })
            ->when($teamId !== null, static function ($query) use ($teamId): void {
                $query->whereHas('teams', static function ($query) use ($teamId): void {
                    $query->where('teams.id', $teamId);
                });
            })
            ->when($leadUserId !== null, static fn ($query) => $query->where('lead_user_id', $leadUserId))
            ->withCount('teams')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
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

        $teamFilterOptions = $actor->role->canViewAllProjects()
            ? Team::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(static fn (Team $team): array => [
                    'value' => $team->id,
                    'label' => $team->name,
                ])
                ->all()
            : $actor->teams()
                ->orderBy('name')
                ->get()
                ->map(static fn (Team $team): array => [
                    'value' => $team->id,
                    'label' => $team->name,
                ])
                ->all();

        $leadFilterOptions = $actor->role->canViewAllProjects()
            ? collect($this->leadCandidatesPayload())
                ->map(static fn (array $row): array => [
                    'value' => $row['value'],
                    'label' => $row['label'],
                ])
                ->values()
                ->all()
            : [];

        return Inertia::render('admin/projects/Index', [
            'projects' => $projects,
            'canManageProjects' => $actor->canManageProjects(),
            'filters' => [
                'search' => $search,
                'team_id' => $teamId !== null ? (string) $teamId : '',
                'lead_user_id' => $leadUserId !== null ? (string) $leadUserId : '',
            ],
            'filter_options' => [
                'teams' => $teamFilterOptions,
                'leads' => $leadFilterOptions,
            ],
            'show_lead_filter' => $actor->role->canViewAllProjects(),
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
                'estimation_required' => $project->estimation_required,
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

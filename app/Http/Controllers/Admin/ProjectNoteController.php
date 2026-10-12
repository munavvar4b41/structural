<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProjectNoteRequest;
use App\Http\Requests\Admin\UpdateProjectNoteRequest;
use App\Models\Project;
use App\Models\ProjectNote;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ProjectNoteController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request, Project $project): Response
    {
        $this->authorize('viewAny', ProjectNote::class);
        $this->authorize('view', $project);

        $actor = $request->user();
        abort_if(! $actor instanceof User, 403);

        $search = trim((string) $request->query('search', ''));

        $notes = $project->notes()
            ->with('creator')
            ->when($search !== '', static function ($query) use ($search): void {
                $term = '%'.addcslashes($search, '%_\\').'%';
                $query->where(static function ($query) use ($term): void {
                    $query->where('title', 'like', $term)
                        ->orWhere('body', 'like', $term);
                });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(function (ProjectNote $note) use ($project, $actor): array {
                $note->setRelation('project', $project);

                return $this->noteRow($note, $actor);
            });

        return Inertia::render('admin/projects/notes/Index', [
            'project' => $this->projectSummary($project),
            'notes' => $notes,
            'can_create' => $actor->can('create', [ProjectNote::class, $project]),
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function create(Project $project): Response
    {
        $this->authorize('create', [ProjectNote::class, $project]);

        return Inertia::render('admin/projects/notes/Create', [
            'project' => $this->projectSummary($project),
        ]);
    }

    public function store(StoreProjectNoteRequest $request, Project $project): RedirectResponse
    {
        $actor = $request->user();
        abort_if(! $actor instanceof User, 403);

        $note = $project->notes()->create([
            ...$request->validated(),
            'created_by_user_id' => $actor->id,
        ]);

        return to_route('admin.projects.notes.show', [$project, $note])
            ->with('toast', __('Note added.'));
    }

    public function show(Request $request, Project $project, ProjectNote $note): Response
    {
        abort_if($note->project_id !== $project->id, 404);
        $this->authorize('view', $note);

        $actor = $request->user();
        abort_if(! $actor instanceof User, 403);

        $note->loadMissing('creator');

        return Inertia::render('admin/projects/notes/Show', [
            'project' => $this->projectSummary($project),
            'note' => [
                'id' => $note->id,
                'title' => $note->title,
                'body' => $note->body,
                'created_at' => $note->created_at?->toIso8601String(),
                'updated_at' => $note->updated_at?->toIso8601String(),
                'creator' => $this->userBrief($note->creator),
            ],
            'can_update' => $actor->can('update', $note),
            'can_delete' => $actor->can('delete', $note),
        ]);
    }

    public function edit(Project $project, ProjectNote $note): Response
    {
        abort_if($note->project_id !== $project->id, 404);
        $this->authorize('update', $note);

        return Inertia::render('admin/projects/notes/Edit', [
            'project' => $this->projectSummary($project),
            'note' => [
                'id' => $note->id,
                'title' => $note->title,
                'body' => $note->body,
            ],
        ]);
    }

    public function update(UpdateProjectNoteRequest $request, Project $project, ProjectNote $note): RedirectResponse
    {
        abort_if($note->project_id !== $project->id, 404);

        $note->update($request->validated());

        return to_route('admin.projects.notes.show', [$project, $note])
            ->with('toast', __('Note updated.'));
    }

    public function destroy(Project $project, ProjectNote $note): RedirectResponse
    {
        abort_if($note->project_id !== $project->id, 404);
        $this->authorize('delete', $note);

        $note->delete();

        return to_route('admin.projects.notes.index', $project)
            ->with('toast', __('Note removed.'));
    }

    /**
     * @return array{id: int, name: string, code: string|null}
     */
    private function projectSummary(Project $project): array
    {
        return [
            'id' => $project->id,
            'name' => $project->name,
            'code' => $project->code,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function noteRow(ProjectNote $note, User $actor): array
    {
        return [
            'id' => $note->id,
            'title' => $note->title,
            'body_preview' => Str::limit($note->body, 160),
            'created_at' => $note->created_at?->toIso8601String(),
            'creator' => $this->userBrief($note->creator),
            'can_update' => $actor->can('update', $note),
            'can_delete' => $actor->can('delete', $note),
        ];
    }

    /**
     * @return array{id: int, name: string, email: string}|null
     */
    private function userBrief(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ];
    }
}

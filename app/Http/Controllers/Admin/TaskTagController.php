<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TaskTagColor;
use App\Enums\TaskTagShade;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTaskTagRequest;
use App\Http\Requests\Admin\UpdateTaskTagRequest;
use App\Models\TaskTag;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TaskTagController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', TaskTag::class);

        $search = trim((string) $request->query('search', ''));

        $tags = TaskTag::query()
            ->withCount('tasks')
            ->when($search !== '', static function ($query) use ($search): void {
                $term = '%'.addcslashes($search, '%_\\').'%';
                $query->where('name', 'like', $term);
            })
            ->ordered()
            ->paginate(15)
            ->withQueryString()
            ->through(static fn (TaskTag $tag): array => [
                'id' => $tag->id,
                'name' => $tag->name,
                'color' => $tag->color->value,
                'shade' => $tag->shade->value,
                'sort_order' => $tag->sort_order,
                'tasks_count' => $tag->tasks_count,
            ]);

        return Inertia::render('admin/task-tags/Index', [
            'tags' => $tags,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', TaskTag::class);

        return Inertia::render('admin/task-tags/Create', [
            'color_options' => TaskTagColor::options(),
            'shade_options' => TaskTagShade::options(),
        ]);
    }

    public function store(StoreTaskTagRequest $request): RedirectResponse
    {
        TaskTag::query()->create($request->validated());

        return to_route('admin.task-tags.index')->with('toast', 'Tag created.');
    }

    public function edit(TaskTag $taskTag): Response
    {
        $this->authorize('update', $taskTag);

        return Inertia::render('admin/task-tags/Edit', [
            'tag' => [
                'id' => $taskTag->id,
                'name' => $taskTag->name,
                'color' => $taskTag->color->value,
                'shade' => $taskTag->shade->value,
                'sort_order' => $taskTag->sort_order,
            ],
            'color_options' => TaskTagColor::options(),
            'shade_options' => TaskTagShade::options(),
        ]);
    }

    public function update(UpdateTaskTagRequest $request, TaskTag $taskTag): RedirectResponse
    {
        $taskTag->update($request->validated());

        return to_route('admin.task-tags.index')->with('toast', 'Tag updated.');
    }

    public function destroy(TaskTag $taskTag): RedirectResponse
    {
        $this->authorize('delete', $taskTag);

        if ($taskTag->tasks()->exists()) {
            return back()->withErrors([
                'tag' => __('This tag cannot be deleted because tasks still use it.'),
            ]);
        }

        $taskTag->delete();

        return to_route('admin.task-tags.index')->with('toast', 'Tag deleted.');
    }
}

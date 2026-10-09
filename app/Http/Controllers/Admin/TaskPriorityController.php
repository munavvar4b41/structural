<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TaskPriorityColor;
use App\Enums\TaskPriorityShade;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTaskPriorityRequest;
use App\Http\Requests\Admin\UpdateTaskPriorityRequest;
use App\Models\TaskPriority;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TaskPriorityController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', TaskPriority::class);

        $search = trim((string) $request->query('search', ''));

        $priorities = TaskPriority::query()
            ->withCount('tasks')
            ->when($search !== '', static function ($query) use ($search): void {
                $term = '%'.addcslashes($search, '%_\\').'%';
                $query->where('name', 'like', $term);
            })
            ->ordered()
            ->paginate(15)
            ->withQueryString()
            ->through(static fn (TaskPriority $priority): array => [
                'id' => $priority->id,
                'name' => $priority->name,
                'color' => $priority->color->value,
                'shade' => $priority->shade->value,
                'sort_order' => $priority->sort_order,
                'tasks_count' => $priority->tasks_count,
            ]);

        return Inertia::render('admin/task-priorities/Index', [
            'priorities' => $priorities,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', TaskPriority::class);

        return Inertia::render('admin/task-priorities/Create', [
            'color_options' => TaskPriorityColor::options(),
            'shade_options' => TaskPriorityShade::options(),
        ]);
    }

    public function store(StoreTaskPriorityRequest $request): RedirectResponse
    {
        TaskPriority::query()->create($request->validated());

        return to_route('admin.task-priorities.index')->with('toast', 'Priority created.');
    }

    public function edit(TaskPriority $taskPriority): Response
    {
        $this->authorize('update', $taskPriority);

        return Inertia::render('admin/task-priorities/Edit', [
            'priority' => [
                'id' => $taskPriority->id,
                'name' => $taskPriority->name,
                'color' => $taskPriority->color->value,
                'shade' => $taskPriority->shade->value,
                'sort_order' => $taskPriority->sort_order,
            ],
            'color_options' => TaskPriorityColor::options(),
            'shade_options' => TaskPriorityShade::options(),
        ]);
    }

    public function update(UpdateTaskPriorityRequest $request, TaskPriority $taskPriority): RedirectResponse
    {
        $taskPriority->update($request->validated());

        return to_route('admin.task-priorities.index')->with('toast', 'Priority updated.');
    }

    public function destroy(TaskPriority $taskPriority): RedirectResponse
    {
        $this->authorize('delete', $taskPriority);

        if ($taskPriority->tasks()->exists()) {
            return back()->withErrors([
                'priority' => __('This priority cannot be deleted because tasks still use it.'),
            ]);
        }

        $taskPriority->delete();

        return to_route('admin.task-priorities.index')->with('toast', 'Priority deleted.');
    }
}

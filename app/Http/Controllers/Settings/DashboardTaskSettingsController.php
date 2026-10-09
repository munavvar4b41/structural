<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateDashboardTaskSettingsRequest;
use App\Models\TaskPriority;
use App\Support\DashboardTaskPreferences;
use App\Support\DashboardTasksBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardTaskSettingsController extends Controller
{
    /**
     * Show the signed-in user's dashboard task settings.
     */
    public function edit(
        Request $request,
        DashboardTaskPreferences $preferences,
        DashboardTasksBuilder $tasks,
    ): Response {
        $user = $request->user();
        $stored = $preferences->forUser($user);
        $projectOptions = $tasks->projectOptions($user);
        $visibleProjectIds = array_column($projectOptions, 'value');
        $storedProjectIds = array_map(
            static fn (int $id): string => (string) $id,
            $stored['project_ids'],
        );

        return Inertia::render('settings/DashboardTasks', [
            'preferences' => [
                'enabled' => $stored['enabled'],
                'statuses' => $stored['statuses'],
                'priorities' => TaskPriority::filterValues(TaskPriority::parseFilter($stored['priorities'])),
                'project_ids' => array_values(array_intersect($storedProjectIds, $visibleProjectIds)),
            ],
            'status_options' => $tasks->statusOptions(),
            'priority_options' => TaskPriority::filterOptions(),
            'project_options' => $projectOptions,
        ]);
    }

    /**
     * Save the signed-in user's dashboard task settings.
     */
    public function update(
        UpdateDashboardTaskSettingsRequest $request,
        DashboardTaskPreferences $preferences,
    ): RedirectResponse {
        $preferences->store($request->user(), $request->preferences());

        return to_route('dashboard-tasks.edit')->with('toast', 'Dashboard task settings saved.');
    }
}

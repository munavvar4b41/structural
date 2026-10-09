<?php

namespace App\Http\Controllers;

use App\Support\DashboardTasksBuilder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show the signed-in user's dashboard, including their configured tasks.
     */
    public function __invoke(Request $request, DashboardTasksBuilder $tasks): Response
    {
        return Inertia::render('Dashboard', [
            'dashboard_tasks' => $tasks->build($request->user()),
        ]);
    }
}

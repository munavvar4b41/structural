<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureHasPrimaryTeam
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        if (in_array($user->role, [UserRole::SuperAdmin, UserRole::Admin, UserRole::Client], true)) {
            return $next($request);
        }

        if ($user->primary_team_id !== null) {
            return $next($request);
        }

        if ($request->routeIs('teams.select.*')) {
            return $next($request);
        }

        return redirect()->route('teams.select.create');
    }
}

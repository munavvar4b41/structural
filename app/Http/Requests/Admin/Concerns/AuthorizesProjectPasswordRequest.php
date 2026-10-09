<?php

namespace App\Http\Requests\Admin\Concerns;

use App\Models\Project;
use App\Models\ProjectPassword;

trait AuthorizesProjectPasswordRequest
{
    protected function authorizeProjectPassword(string $ability): bool
    {
        $project = $this->route('project');
        $user = $this->user();

        if (! $project instanceof Project || $user === null) {
            return false;
        }

        $password = $this->route('password');

        if ($password instanceof ProjectPassword) {
            abort_if($password->project_id !== $project->id, 404);

            return $user->can($ability, $password);
        }

        return $user->can($ability, [ProjectPassword::class, $project]);
    }
}

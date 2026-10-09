<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProjectPassphraseRequest;
use App\Models\Project;
use App\Support\ProjectPasswordCipher;
use Illuminate\Http\RedirectResponse;

class ProjectPassphraseController extends Controller
{
    public function __construct(private readonly ProjectPasswordCipher $cipher) {}

    public function store(StoreProjectPassphraseRequest $request, Project $project): RedirectResponse
    {
        $project->refresh();

        $this->cipher->seal($project, $request->validated('passphrase'));

        return back()->with('toast', __('Project passphrase saved.'));
    }
}

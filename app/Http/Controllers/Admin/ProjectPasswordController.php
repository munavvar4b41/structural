<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DeleteProjectPasswordRequest;
use App\Http\Requests\Admin\RevealProjectPasswordRequest;
use App\Http\Requests\Admin\StoreProjectPasswordRequest;
use App\Http\Requests\Admin\UpdateProjectPasswordRequest;
use App\Models\Project;
use App\Models\ProjectPassword;
use App\Models\User;
use App\Support\ProjectPasswordCipher;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectPasswordController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly ProjectPasswordCipher $cipher) {}

    public function index(Request $request, Project $project): Response
    {
        $this->authorize('viewAny', [ProjectPassword::class, $project]);

        $passwords = $project->passwords()
            ->with('createdBy:id,name')
            ->orderBy('label')
            ->get()
            ->map(static function (ProjectPassword $password): array {
                $creator = $password->createdBy;

                return [
                    'id' => $password->id,
                    'label' => $password->label,
                    'username' => $password->username,
                    'url' => $password->url,
                    'has_notes' => is_string($password->notes_ciphertext) && $password->notes_ciphertext !== '',
                    'created_by' => $creator === null ? null : [
                        'id' => $creator->id,
                        'name' => $creator->name,
                    ],
                    'created_at' => $password->created_at?->toIso8601String(),
                ];
            })
            ->all();

        return Inertia::render('admin/projects/passwords/Index', [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'has_passphrase' => $project->hasPasswordPassphrase(),
            ],
            'passwords' => $passwords,
        ]);
    }

    public function store(StoreProjectPasswordRequest $request, Project $project): RedirectResponse
    {
        $validated = $request->validated();
        $actor = $request->user();
        abort_if(! $actor instanceof User, 403);

        $key = $this->cipher->verifiedKey($project, $validated['passphrase']);

        try {
            $secret = $this->cipher->encrypt($key, $validated['secret']);
            $notes = $this->cipher->encryptOptional($key, $validated['notes'] ?? null);

            $password = new ProjectPassword;
            $password->forceFill([
                'project_id' => $project->id,
                'created_by_user_id' => $actor->id,
                'label' => $validated['label'],
                'username' => $validated['username'] ?? null,
                'url' => $validated['url'] ?? null,
                'secret_nonce' => $secret['nonce'],
                'secret_ciphertext' => $secret['ciphertext'],
                'notes_nonce' => $notes['nonce'],
                'notes_ciphertext' => $notes['ciphertext'],
            ])->save();
        } finally {
            sodium_memzero($key);
        }

        return back()->with('toast', __('Password saved.'));
    }

    public function update(
        UpdateProjectPasswordRequest $request,
        Project $project,
        ProjectPassword $password,
    ): RedirectResponse {
        $validated = $request->validated();
        $key = $this->cipher->verifiedKey($project, $validated['passphrase']);

        try {
            $attributes = [
                'label' => $validated['label'],
                'username' => $validated['username'] ?? null,
                'url' => $validated['url'] ?? null,
            ];

            if (is_string($validated['secret'] ?? null) && $validated['secret'] !== '') {
                $secret = $this->cipher->encrypt($key, $validated['secret']);
                $attributes['secret_nonce'] = $secret['nonce'];
                $attributes['secret_ciphertext'] = $secret['ciphertext'];
            }

            $clearNotes = filter_var($validated['clear_notes'] ?? false, FILTER_VALIDATE_BOOLEAN);

            if (is_string($validated['notes'] ?? null) && $validated['notes'] !== '') {
                $notes = $this->cipher->encrypt($key, $validated['notes']);
                $attributes['notes_nonce'] = $notes['nonce'];
                $attributes['notes_ciphertext'] = $notes['ciphertext'];
            } elseif ($clearNotes) {
                $attributes['notes_nonce'] = null;
                $attributes['notes_ciphertext'] = null;
            }

            $password->forceFill($attributes)->save();
        } finally {
            sodium_memzero($key);
        }

        return back()->with('toast', __('Password updated.'));
    }

    public function destroy(
        DeleteProjectPasswordRequest $request,
        Project $project,
        ProjectPassword $password,
    ): RedirectResponse {
        $key = $this->cipher->verifiedKey($project, $request->validated('passphrase'));
        sodium_memzero($key);

        $password->delete();

        return back()->with('toast', __('Password removed.'));
    }

    public function reveal(
        RevealProjectPasswordRequest $request,
        Project $project,
        ProjectPassword $password,
    ): JsonResponse {
        $key = $this->cipher->verifiedKey($project, $request->validated('passphrase'));

        try {
            $notes = null;

            if (is_string($password->notes_nonce) && is_string($password->notes_ciphertext)) {
                $notes = $this->cipher->decrypt($key, $password->notes_nonce, $password->notes_ciphertext);
            }

            return response()->json([
                'secret' => $this->cipher->decrypt($key, $password->secret_nonce, $password->secret_ciphertext),
                'notes' => $notes,
            ]);
        } finally {
            sodium_memzero($key);
        }
    }
}

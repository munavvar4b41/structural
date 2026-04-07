<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()
            ->orderBy('name')
            ->paginate(15)
            ->through(static fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            ]);

        return Inertia::render('admin/users/Index', [
            'users' => $users,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', User::class);

        return Inertia::render('admin/users/Create', [
            'assignableRoles' => $this->assignableRolesPayload($request),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        User::query()->create($request->validated());

        return to_route('admin.users.index');
    }

    public function edit(Request $request, User $user): Response
    {
        $this->authorize('update', $user);

        return Inertia::render('admin/users/Edit', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
            ],
            'assignableRoles' => $this->assignableRolesPayload($request),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $payload = $request->validated();

        if (empty($payload['password'])) {
            unset($payload['password']);
        }

        unset($payload['password_confirmation']);

        $user->update($payload);

        return to_route('admin.users.index');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        $user->delete();

        return to_route('admin.users.index');
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function assignableRolesPayload(Request $request): array
    {
        $actor = $request->user();

        if ($actor === null) {
            return [];
        }

        return collect(UserRole::assignableRolesForActor($actor))
            ->map(static fn (UserRole $role): array => [
                'value' => $role->value,
                'label' => $role->label(),
            ])
            ->values()
            ->all();
    }
}

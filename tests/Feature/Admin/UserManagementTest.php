<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    protected function validUserPayload(array $overrides = []): array
    {
        $team = Team::factory()->create();

        return array_merge([
            'name' => 'New Person',
            'email' => 'newperson@example.com',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
            'role' => UserRole::Staff->value,
            'primary_team_id' => $team->id,
            'team_ids' => [$team->id],
        ], $overrides);
    }

    public function test_guest_is_redirected_from_users_index(): void
    {
        $this->get(route('admin.users.index'))
            ->assertRedirect(route('login'));
    }

    public function test_staff_cannot_view_users_index(): void
    {
        $user = User::factory()->withPrimaryTeam()->create();

        $this->actingAs($user)
            ->get(route('admin.users.index'))
            ->assertForbidden();
    }

    public function test_team_head_cannot_view_users_index(): void
    {
        $user = User::factory()->teamHead()->withPrimaryTeam()->create();

        $this->actingAs($user)
            ->get(route('admin.users.index'))
            ->assertForbidden();
    }

    public function test_client_cannot_view_users_index(): void
    {
        $user = User::factory()->client()->withPrimaryTeam()->create();

        $this->actingAs($user)
            ->get(route('admin.users.index'))
            ->assertForbidden();
    }

    public function test_super_admin_can_view_users_index(): void
    {
        $user = User::factory()->superAdmin()->withPrimaryTeam()->create();

        User::factory()->create(['name' => 'Listed User']);

        $this->actingAs($user)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/users/Index')
                ->has('users.data', fn (Assert $users) => $users
                    ->etc())
                ->where(
                    'users.data',
                    fn (Collection $rows) => ! $rows->contains(
                        fn (array $row): bool => (int) ($row['id'] ?? 0) === $user->id,
                    ),
                ));
    }

    public function test_admin_can_view_users_index(): void
    {
        $user = User::factory()->admin()->withPrimaryTeam()->create();

        $this->actingAs($user)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('admin/users/Index'));
    }

    public function test_admin_cannot_edit_super_admin_user(): void
    {
        $admin = User::factory()->admin()->withPrimaryTeam()->create();
        $super = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->get(route('admin.users.edit', $super))
            ->assertForbidden();
    }

    public function test_admin_cannot_update_super_admin_user(): void
    {
        $admin = User::factory()->admin()->withPrimaryTeam()->create();
        $super = User::factory()->superAdmin()->create();
        $superTeam = Team::factory()->create()->id;

        $this->actingAs($admin)
            ->put(route('admin.users.update', $super), [
                'name' => 'Hacked',
                'email' => $super->email,
                'role' => UserRole::SuperAdmin->value,
                'primary_team_id' => $superTeam,
                'team_ids' => [$superTeam],
            ])
            ->assertForbidden();
    }

    public function test_admin_cannot_delete_super_admin_user(): void
    {
        $admin = User::factory()->admin()->withPrimaryTeam()->create();
        $super = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $super))
            ->assertForbidden();
    }

    public function test_admin_cannot_store_user_with_super_admin_role(): void
    {
        $admin = User::factory()->admin()->withPrimaryTeam()->create();

        $this->actingAs($admin)
            ->post(route('admin.users.store'), $this->validUserPayload([
                'email' => 'evil@example.com',
                'role' => UserRole::SuperAdmin->value,
            ]))
            ->assertSessionHasErrors('role');
    }

    public function test_super_admin_can_store_user_with_super_admin_role(): void
    {
        $actor = User::factory()->superAdmin()->withPrimaryTeam()->create();

        $this->actingAs($actor)
            ->post(route('admin.users.store'), $this->validUserPayload([
                'email' => 'another-super@example.com',
                'role' => UserRole::SuperAdmin->value,
            ]))
            ->assertSessionHas('toast', 'User created.')
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'another-super@example.com',
            'role' => UserRole::SuperAdmin->value,
        ]);
    }

    public function test_admin_can_store_staff_user(): void
    {
        $admin = User::factory()->admin()->withPrimaryTeam()->create();

        $this->actingAs($admin)
            ->post(route('admin.users.store'), $this->validUserPayload([
                'email' => 'staffuser@example.com',
            ]))
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'staffuser@example.com',
            'role' => UserRole::Staff->value,
        ]);
    }

    public function test_admin_can_update_staff_user(): void
    {
        $admin = User::factory()->admin()->withPrimaryTeam()->create();
        $target = User::factory()->create(['name' => 'Before']);
        $teamA = Team::factory()->create()->id;
        $teamB = Team::factory()->create()->id;

        $this->actingAs($admin)
            ->put(route('admin.users.update', $target), [
                'name' => 'After',
                'email' => $target->email,
                'role' => UserRole::TeamHead->value,
                'primary_team_id' => $teamA,
                'team_ids' => [$teamA, $teamB],
            ])
            ->assertRedirect(route('admin.users.index'));

        $this->assertSame('After', $target->fresh()->name);
        $this->assertSame(UserRole::TeamHead, $target->fresh()->role);
        $this->assertSame($teamA, $target->fresh()->primary_team_id);
        $this->assertSame(2, $target->fresh()->teams()->count());
    }

    public function test_admin_can_delete_other_user(): void
    {
        $admin = User::factory()->admin()->withPrimaryTeam()->create();
        $target = User::factory()->create();

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $target))
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseMissing('users', ['id' => $target->id]);
    }

    public function test_user_cannot_delete_self(): void
    {
        $admin = User::factory()->admin()->withPrimaryTeam()->create();

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $admin))
            ->assertForbidden();
    }

    public function test_cannot_demote_last_super_admin(): void
    {
        $super = User::factory()->superAdmin()->withPrimaryTeam()->create();
        $superTeam = Team::factory()->create()->id;

        $this->actingAs($super)
            ->put(route('admin.users.update', $super), [
                'name' => $super->name,
                'email' => $super->email,
                'role' => UserRole::Admin->value,
                'primary_team_id' => $superTeam,
                'team_ids' => [$superTeam],
            ])
            ->assertSessionHasErrors('role');

        $this->assertSame(UserRole::SuperAdmin, $super->fresh()->role);
    }

    public function test_can_demote_super_admin_when_another_super_admin_exists(): void
    {
        $superA = User::factory()->superAdmin()->withPrimaryTeam()->create();
        User::factory()->superAdmin()->create(['email' => 'other-super@example.com']);
        $superTeam = Team::factory()->create()->id;

        $this->actingAs($superA)
            ->put(route('admin.users.update', $superA), [
                'name' => $superA->name,
                'email' => $superA->email,
                'role' => UserRole::Admin->value,
                'primary_team_id' => $superTeam,
                'team_ids' => [$superTeam],
            ])
            ->assertRedirect(route('admin.users.index'));

        $this->assertSame(UserRole::Admin, $superA->fresh()->role);
    }

    public function test_super_admin_can_delete_another_super_admin_when_two_exist(): void
    {
        $superA = User::factory()->superAdmin()->withPrimaryTeam()->create();
        $superB = User::factory()->superAdmin()->create(['email' => 'peer-super@example.com']);

        $this->actingAs($superA)
            ->delete(route('admin.users.destroy', $superB))
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseMissing('users', ['id' => $superB->id]);
        $this->assertDatabaseHas('users', ['id' => $superA->id]);
    }
}

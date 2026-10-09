<?php

namespace Tests\Feature\Admin;

use App\Models\Project;
use App\Models\ProjectPassword;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProjectPasswordTest extends TestCase
{
    use RefreshDatabase;

    private const string PASSPHRASE = 'correct horse battery';

    public function test_creating_a_project_stores_a_verifier_and_not_the_passphrase(): void
    {
        $team = Team::factory()->create();
        $client = User::factory()->client()->create();
        $admin = User::factory()->admin()->create(['primary_team_id' => null]);

        $this->actingAs($admin)
            ->post(route('admin.projects.store'), [
                'name' => 'Vault Project',
                'client_user_id' => $client->id,
                'team_ids' => [$team->id],
                'passphrase' => self::PASSPHRASE,
                'passphrase_confirmation' => self::PASSPHRASE,
            ])
            ->assertRedirect(route('admin.projects.index'));

        $project = Project::query()->where('name', 'Vault Project')->firstOrFail();
        $this->assertTrue($project->hasPasswordPassphrase());

        $row = (array) DB::table('projects')->where('id', $project->id)->first();

        foreach ($row as $value) {
            if (is_string($value)) {
                $this->assertStringNotContainsString(self::PASSPHRASE, $value);
            }
        }
    }

    public function test_password_is_stored_as_ciphertext_and_revealed_with_the_project_passphrase(): void
    {
        $admin = User::factory()->admin()->create(['primary_team_id' => null]);
        $project = $this->sealedProject();

        $this->actingAs($admin)
            ->post(route('admin.projects.passwords.store', $project), $this->passwordPayload())
            ->assertRedirect()
            ->assertSessionHas('toast');

        $password = ProjectPassword::query()->where('project_id', $project->id)->firstOrFail();
        $this->assertSame('Staging database', $password->label);
        $this->assertStringNotContainsString('s3cret-value', $password->secret_ciphertext);
        $this->assertStringNotContainsString('recovery code 42', (string) $password->notes_ciphertext);

        $this->actingAs($admin)
            ->postJson(route('admin.projects.passwords.reveal', [$project, $password]), [
                'passphrase' => self::PASSPHRASE,
            ])
            ->assertOk()
            ->assertJsonPath('secret', 's3cret-value')
            ->assertJsonPath('notes', 'recovery code 42');

        $this->actingAs($admin)
            ->put(route('admin.projects.passwords.update', [$project, $password]), [
                'passphrase' => self::PASSPHRASE,
                'label' => 'Staging database',
                'username' => 'deploy',
                'url' => 'https://db.example.test',
                'secret' => '',
                'notes' => '',
            ])
            ->assertRedirect()
            ->assertSessionHas('toast');

        $this->actingAs($admin)
            ->postJson(route('admin.projects.passwords.reveal', [$project, $password]), [
                'passphrase' => self::PASSPHRASE,
            ])
            ->assertOk()
            ->assertJsonPath('secret', 's3cret-value')
            ->assertJsonPath('notes', 'recovery code 42');
    }

    public function test_wrong_passphrase_is_rejected_before_any_password_is_written(): void
    {
        $admin = User::factory()->admin()->create(['primary_team_id' => null]);
        $project = $this->sealedProject();

        $this->actingAs($admin)
            ->post(route('admin.projects.passwords.store', $project), $this->passwordPayload('incorrect horse battery'))
            ->assertSessionHasErrors('passphrase');

        $this->assertDatabaseCount('project_passwords', 0);

        $this->actingAs($admin)
            ->post(route('admin.projects.passwords.store', $project), $this->passwordPayload())
            ->assertRedirect();

        $password = ProjectPassword::query()->where('project_id', $project->id)->firstOrFail();
        $ciphertext = $password->secret_ciphertext;

        $this->actingAs($admin)
            ->put(route('admin.projects.passwords.update', [$project, $password]), [
                ...$this->passwordPayload('incorrect horse battery'),
                'label' => 'Changed label',
                'secret' => 'replacement-secret',
            ])
            ->assertSessionHasErrors('passphrase');

        $password->refresh();
        $this->assertSame('Staging database', $password->label);
        $this->assertSame($ciphertext, $password->secret_ciphertext);

        $this->actingAs($admin)
            ->delete(route('admin.projects.passwords.destroy', [$project, $password]), [
                'passphrase' => 'incorrect horse battery',
            ])
            ->assertSessionHasErrors('passphrase');

        $this->assertDatabaseHas('project_passwords', ['id' => $password->id]);

        $this->actingAs($admin)
            ->postJson(route('admin.projects.passwords.reveal', [$project, $password]), [
                'passphrase' => 'incorrect horse battery',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('passphrase');
    }

    public function test_client_and_assigned_staff_can_add_and_reveal_passwords(): void
    {
        $passphrase = self::PASSPHRASE;
        $team = Team::factory()->create();
        $client = User::factory()->client()->create();
        $staff = User::factory()->withPrimaryTeam($team)->create();
        $project = Project::factory()->withPassphrase($passphrase)->create([
            'client_user_id' => $client->id,
        ]);
        $project->teams()->sync([$team->id]);

        $this->actingAs($client)
            ->post(route('admin.projects.passwords.store', $project), $this->passwordPayload())
            ->assertRedirect();

        $password = ProjectPassword::query()->where('project_id', $project->id)->firstOrFail();

        $this->actingAs($client)
            ->postJson(route('admin.projects.passwords.reveal', [$project, $password]), [
                'passphrase' => $passphrase,
            ])
            ->assertOk()
            ->assertJsonPath('secret', 's3cret-value');

        $this->actingAs($staff)
            ->post(route('admin.projects.passwords.store', $project), [
                ...$this->passwordPayload(),
                'label' => 'Staff entry',
                'secret' => 'staff-secret',
            ])
            ->assertRedirect();

        $staffPassword = ProjectPassword::query()->where('label', 'Staff entry')->firstOrFail();

        $this->actingAs($staff)
            ->postJson(route('admin.projects.passwords.reveal', [$project, $staffPassword]), [
                'passphrase' => $passphrase,
            ])
            ->assertOk()
            ->assertJsonPath('secret', 'staff-secret');
    }

    public function test_users_who_cannot_view_the_project_cannot_manage_passwords(): void
    {
        $team = Team::factory()->create();
        $otherTeam = Team::factory()->create();
        $client = User::factory()->client()->create();
        $otherClient = User::factory()->client()->create();
        $staff = User::factory()->withPrimaryTeam($otherTeam)->create();
        $project = Project::factory()->withPassphrase(self::PASSPHRASE)->create([
            'client_user_id' => $client->id,
        ]);
        $project->teams()->sync([$team->id]);
        $password = $this->storePassword($project);

        $this->actingAs($otherClient)
            ->post(route('admin.projects.passwords.store', $project), $this->passwordPayload())
            ->assertForbidden();

        $this->actingAs($otherClient)
            ->get(route('admin.projects.passwords.index', $project))
            ->assertForbidden();

        $this->actingAs($staff)
            ->postJson(route('admin.projects.passwords.reveal', [$project, $password]), [
                'passphrase' => self::PASSPHRASE,
            ])
            ->assertForbidden();
    }

    public function test_an_unsealed_project_can_be_sealed_once(): void
    {
        $admin = User::factory()->admin()->create(['primary_team_id' => null]);
        $project = Project::factory()->create();

        $this->actingAs($admin)
            ->get(route('admin.projects.passwords.index', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('project.has_passphrase', false));

        $this->actingAs($admin)
            ->post(route('admin.projects.passwords.store', $project), $this->passwordPayload())
            ->assertSessionHasErrors('passphrase');

        $this->assertDatabaseCount('project_passwords', 0);

        $this->actingAs($admin)
            ->post(route('admin.projects.passphrase.store', $project), [
                'passphrase' => self::PASSPHRASE,
                'passphrase_confirmation' => self::PASSPHRASE,
            ])
            ->assertRedirect()
            ->assertSessionHas('toast');

        $this->actingAs($admin)
            ->post(route('admin.projects.passphrase.store', $project), [
                'passphrase' => 'another project passphrase',
                'passphrase_confirmation' => 'another project passphrase',
            ])
            ->assertSessionHasErrors('passphrase');

        $this->assertTrue($project->refresh()->hasPasswordPassphrase());
    }

    public function test_project_pages_do_not_include_the_verifier_or_ciphertext(): void
    {
        $admin = User::factory()->admin()->create(['primary_team_id' => null]);
        $project = $this->sealedProject();
        $password = $this->storePassword($project);

        $this->actingAs($admin)
            ->get(route('admin.projects.show', $project))
            ->assertOk()
            ->assertDontSee('s3cret-value', false)
            ->assertDontSee(self::PASSPHRASE, false)
            ->assertInertia(fn (Assert $page) => $page
                ->missing('project.password_kdf_salt')
                ->missing('project.password_verifier_nonce')
                ->missing('project.password_verifier_ciphertext'));

        $this->actingAs($admin)
            ->get(route('admin.projects.passwords.index', $project))
            ->assertOk()
            ->assertDontSee('s3cret-value', false)
            ->assertDontSee('recovery code 42', false)
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/projects/passwords/Index')
                ->where('passwords.0.id', $password->id)
                ->where('passwords.0.label', 'Staging database')
                ->missing('passwords.0.secret')
                ->missing('passwords.0.secret_ciphertext')
                ->missing('passwords.0.notes_ciphertext')
                ->missing('project.password_kdf_salt')
                ->missing('project.password_verifier_ciphertext'));
    }

    private function sealedProject(): Project
    {
        return Project::factory()->withPassphrase(self::PASSPHRASE)->create();
    }

    /**
     * @return array<string, string>
     */
    private function passwordPayload(string $passphrase = self::PASSPHRASE): array
    {
        return [
            'passphrase' => $passphrase,
            'label' => 'Staging database',
            'username' => 'deploy',
            'url' => 'https://db.example.test',
            'secret' => 's3cret-value',
            'notes' => 'recovery code 42',
        ];
    }

    private function storePassword(Project $project): ProjectPassword
    {
        $admin = User::factory()->admin()->create(['primary_team_id' => null]);

        $this->actingAs($admin)
            ->post(route('admin.projects.passwords.store', $project), $this->passwordPayload())
            ->assertRedirect();

        return ProjectPassword::query()->where('project_id', $project->id)->firstOrFail();
    }
}

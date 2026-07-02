<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Settings\CompanySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CompanySettingsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    protected function validCompanyPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Acme IT',
            'legal_name' => null,
            'phone' => null,
            'website' => null,
            'address_line1' => null,
            'address_line2' => null,
            'city' => null,
            'region' => null,
            'postal_code' => null,
            'country' => null,
            'email_domain' => 'example.com',
            'work_day_start_time' => '09:00',
            'work_day_end_time' => '17:00',
        ], $overrides);
    }

    public function test_super_admin_can_view_company_settings(): void
    {
        $user = User::factory()->superAdmin()->create();

        $this->actingAs($user)
            ->get(route('admin.company.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/CompanySettings')
                ->has('company.name')
                ->where('company.work_day_start_time', '09:00')
                ->where('company.work_day_end_time', '17:00'));
    }

    public function test_admin_can_view_company_settings(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)
            ->get(route('admin.company.edit'))
            ->assertOk();
    }

    public function test_team_head_cannot_view_company_settings(): void
    {
        $user = User::factory()->teamHead()->withPrimaryTeam()->create();

        $this->actingAs($user)
            ->get(route('admin.company.edit'))
            ->assertForbidden();
    }

    public function test_staff_cannot_view_company_settings(): void
    {
        $user = User::factory()->withPrimaryTeam()->create();

        $this->actingAs($user)
            ->get(route('admin.company.edit'))
            ->assertForbidden();
    }

    public function test_client_cannot_view_company_settings(): void
    {
        $user = User::factory()->client()->create();

        $this->actingAs($user)
            ->get(route('admin.company.edit'))
            ->assertForbidden();
    }

    public function test_admin_can_update_company_settings(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)
            ->patch(route('admin.company.update'), $this->validCompanyPayload([
                'name' => 'Updated Org Name',
                'email_domain' => 'example.org',
            ]))
            ->assertRedirect(route('admin.company.edit'));

        $settings = app(CompanySettings::class);

        $this->assertSame('Updated Org Name', $settings->name);
        $this->assertSame('example.org', $settings->email_domain);
    }

    public function test_admin_can_update_working_hours(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)
            ->patch(route('admin.company.update'), $this->validCompanyPayload([
                'work_day_start_time' => '08:30',
                'work_day_end_time' => '18:00',
            ]))
            ->assertRedirect(route('admin.company.edit'));

        $settings = app(CompanySettings::class);

        $this->assertSame('08:30', $settings->work_day_start_time);
        $this->assertSame('18:00', $settings->work_day_end_time);
    }

    public function test_company_settings_rejects_end_before_start(): void
    {
        $user = User::factory()->superAdmin()->create();

        $this->actingAs($user)
            ->patch(route('admin.company.update'), $this->validCompanyPayload([
                'work_day_start_time' => '17:00',
                'work_day_end_time' => '09:00',
            ]))
            ->assertSessionHasErrors('work_day_end_time');
    }

    public function test_company_settings_update_validates_email_domain(): void
    {
        $user = User::factory()->superAdmin()->create();

        $this->actingAs($user)
            ->patch(route('admin.company.update'), $this->validCompanyPayload([
                'email_domain' => 'not a domain! ',
            ]))
            ->assertSessionHasErrors('email_domain');
    }

    public function test_guest_cannot_view_company_settings(): void
    {
        $this->get(route('admin.company.edit'))
            ->assertRedirect(route('login'));
    }
}

<?php

namespace Tests\Unit;

use App\Models\Team;
use App\Models\User;
use App\Settings\LeaveSettings;
use App\Support\LeaveRequestMailRecipients;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveRequestMailRecipientsTest extends TestCase
{
    use RefreshDatabase;

    public function test_collects_configured_emails_and_team_heads_on_shared_teams(): void
    {
        $team = Team::factory()->create();
        $requester = User::factory()->withPrimaryTeam($team)->create();

        $headOnTeam = User::factory()->teamHead()->create();
        $headOnTeam->teams()->attach($team->id);

        $otherHead = User::factory()->teamHead()->withPrimaryTeam()->create();

        $settings = app(LeaveSettings::class);
        $settings->notification_emails = ['Config@Example.com'];
        $settings->save();

        $resolver = app(LeaveRequestMailRecipients::class);
        $emails = $resolver->forRequester($requester);

        $this->assertContains('config@example.com', $emails);
        $this->assertContains(strtolower($headOnTeam->email), $emails);
        $this->assertNotContains(strtolower($otherHead->email), $emails);
        $this->assertCount(2, $emails);
    }
}

<?php

namespace Tests\Unit;

use App\Settings\CompanySettings;
use App\Support\WorkTimeEntryWindowResolver;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class WorkTimeEntryWindowResolverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $settings = app(CompanySettings::class);
        $settings->work_day_start_time = '09:00';
        $settings->work_day_end_time = '17:00';
        $settings->save();
    }

    public function test_resolves_duration_ending_at_now_within_work_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-07 12:00:00', config('app.timezone')));

        $resolver = app(WorkTimeEntryWindowResolver::class);
        $window = $resolver->resolve(40);

        $this->assertSame('2026-05-07 11:20:00', $window['start']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-05-07 12:00:00', $window['end']->format('Y-m-d H:i:s'));

        Carbon::setTestNow(null);
    }

    public function test_rejects_duration_longer_than_work_window(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-07 12:00:00', config('app.timezone')));

        $resolver = app(WorkTimeEntryWindowResolver::class);

        $this->expectException(ValidationException::class);
        $resolver->resolve(500);

        Carbon::setTestNow(null);
    }

    public function test_rejects_duration_that_does_not_fit_remaining_hours_today(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-07 09:30:00', config('app.timezone')));

        $resolver = app(WorkTimeEntryWindowResolver::class);

        $this->expectException(ValidationException::class);
        $resolver->resolve(60);

        Carbon::setTestNow(null);
    }
}

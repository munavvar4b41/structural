<?php

namespace Tests\Unit;

use App\Support\WorkTimeEntryWindowResolver;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class WorkTimeEntryWindowResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_duration_ending_at_now(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-07 12:00:00', config('app.timezone')));

        $resolver = app(WorkTimeEntryWindowResolver::class);
        $window = $resolver->resolve(40);

        $this->assertSame('2026-05-07 11:20:00', $window['start']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-05-07 12:00:00', $window['end']->format('Y-m-d H:i:s'));

        Carbon::setTestNow(null);
    }

    public function test_rejects_zero_duration(): void
    {
        $resolver = app(WorkTimeEntryWindowResolver::class);

        $this->expectException(ValidationException::class);
        $resolver->resolve(0);
    }
}

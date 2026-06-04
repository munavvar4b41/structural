<?php

namespace App\Http\Requests\Api\Desktop;

use App\Enums\TimerPauseReason;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DesktopTimerPauseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['sometimes', 'string', Rule::enum(TimerPauseReason::class)],
            'client_event_at' => ['sometimes', 'date'],
        ];
    }

    public function pauseReason(): ?TimerPauseReason
    {
        $value = $this->validated('reason');

        if ($value === null) {
            return null;
        }

        return TimerPauseReason::from($value);
    }

    public function clientEventAt(): ?CarbonInterface
    {
        $value = $this->validated('client_event_at');

        return $value !== null ? CarbonImmutable::parse($value) : null;
    }
}

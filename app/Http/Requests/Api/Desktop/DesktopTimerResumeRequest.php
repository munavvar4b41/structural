<?php

namespace App\Http\Requests\Api\Desktop;

use App\Enums\TimerResumedBy;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DesktopTimerResumeRequest extends FormRequest
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
            'resumed_by' => ['sometimes', 'string', Rule::enum(TimerResumedBy::class)],
            'client_event_at' => ['sometimes', 'date'],
        ];
    }

    public function resumedBy(): ?TimerResumedBy
    {
        $value = $this->validated('resumed_by');

        if ($value === null) {
            return null;
        }

        return TimerResumedBy::from($value);
    }

    public function clientEventAt(): ?CarbonInterface
    {
        $value = $this->validated('client_event_at');

        return $value !== null ? CarbonImmutable::parse($value) : null;
    }
}

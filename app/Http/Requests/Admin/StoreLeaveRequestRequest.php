<?php

namespace App\Http\Requests\Admin;

use App\Enums\LeaveHalfDayPeriod;
use App\Enums\LeaveType;
use App\Models\LeaveRequest;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreLeaveRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::check('create', LeaveRequest::class);
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::enum(LeaveType::class)],
            'date' => ['required', 'date', 'after_or_equal:today'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'half_day_period' => [
                Rule::requiredIf(fn (): bool => $this->string('type')->toString() === LeaveType::HalfDay->value),
                'nullable',
                'string',
                Rule::enum(LeaveHalfDayPeriod::class),
            ],
            'break_starts_at' => [
                Rule::requiredIf(fn (): bool => $this->string('type')->toString() === LeaveType::Break->value),
                'nullable',
                'date',
            ],
            'break_ends_at' => [
                Rule::requiredIf(fn (): bool => $this->string('type')->toString() === LeaveType::Break->value),
                'nullable',
                'date',
                'after:break_starts_at',
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $type = LeaveType::tryFrom((string) $this->input('type'));
                if ($type !== LeaveType::Break) {
                    return;
                }

                $date = (string) $this->input('date');
                $startRaw = $this->input('break_starts_at');
                $endRaw = $this->input('break_ends_at');
                if (! is_string($startRaw) && ! is_numeric($startRaw)) {
                    return;
                }
                if (! is_string($endRaw) && ! is_numeric($endRaw)) {
                    return;
                }

                $tz = (string) config('app.timezone');
                $start = CarbonImmutable::parse($startRaw, $tz);
                $end = CarbonImmutable::parse($endRaw, $tz);

                if (! $end->greaterThan($start)) {
                    $validator->errors()->add(
                        'break_ends_at',
                        __('Break end must be after the start time.'),
                    );
                }

                if ($start->toDateString() !== $date) {
                    $validator->errors()->add(
                        'break_starts_at',
                        __('Break start must fall on the selected date.'),
                    );
                }

                if ($end->toDateString() !== $date) {
                    $validator->errors()->add(
                        'break_ends_at',
                        __('Break end must fall on the selected date.'),
                    );
                }
            },
        ];
    }
}

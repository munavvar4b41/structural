<x-mail::message>
# {{ __('New leave request') }}

**{{ __('Requester') }}:** {{ $leaveRequest->user->name }} ({{ $leaveRequest->user->email }})

**{{ __('Type') }}:** {{ $leaveRequest->type->label() }}

**{{ __('Date') }}:** {{ $leaveRequest->date->toFormattedDateString() }}

@if($leaveRequest->type === \App\Enums\LeaveType::HalfDay && $leaveRequest->half_day_period)
**{{ __('Period') }}:** {{ $leaveRequest->half_day_period->label() }}
@endif

@if($leaveRequest->type === \App\Enums\LeaveType::Break && $leaveRequest->break_starts_at && $leaveRequest->break_ends_at)
**{{ __('Break') }}:** {{ $leaveRequest->break_starts_at->timezone(config('app.timezone'))->format('H:i') }} – {{ $leaveRequest->break_ends_at->timezone(config('app.timezone'))->format('H:i') }}
@endif

@if($leaveRequest->reason)
**{{ __('Reason') }}:** {{ $leaveRequest->reason }}
@endif

<x-mail::button :url="$manageUrl">
{{ __('Review leave requests') }}
</x-mail::button>

{{ __('Thanks') }},<br>
{{ config('app.name') }}
</x-mail::message>

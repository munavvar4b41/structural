<x-mail::message>
# {{ __('New job application') }}

**{{ __('Position') }}:** {{ $jobApplication->jobPosting->title }}

**{{ __('Candidate') }}:** {{ $jobApplication->candidate_name }} ({{ $jobApplication->candidate_email }})

**{{ __('Phone') }}:** {{ $jobApplication->candidate_phone }}

**{{ __('Experience') }}:** {{ $jobApplication->years_of_experience }} {{ __('years') }}

**{{ __('Skills') }}:** {{ $jobApplication->skills }}

<x-mail::button :url="$applicationsUrl">
{{ __('Review applications') }}
</x-mail::button>

{{ __('Thanks') }},<br>
{{ config('app.name') }}
</x-mail::message>

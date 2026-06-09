<x-mail::message>
# {{ __('Update on your application') }}

{{ __('Hello :name,', ['name' => $jobApplication->candidate_name]) }}

{{ __('Thank you for applying for the :title position.', ['title' => $jobApplication->jobPosting->title]) }}

{{ __('After careful review, we have decided not to move forward with your application at this time.') }}

@if($jobApplication->rejection_reason)
**{{ __('Note') }}:** {{ $jobApplication->rejection_reason }}
@endif

{{ __('We appreciate your interest and wish you the best in your job search.') }}

{{ __('Thanks') }},<br>
{{ config('app.name') }}
</x-mail::message>

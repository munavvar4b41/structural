<x-mail::message>
# {{ __('Your application has been updated') }}

{{ __('Hello :name,', ['name' => $jobApplication->candidate_name]) }}

{{ __('Thank you for your interest in the :title position.', ['title' => $jobApplication->jobPosting->title]) }}

{{ __('Your application has moved to the next stage:') }} **{{ $jobApplication->status->label() }}**

{{ __('We will be in touch with any next steps.') }}

{{ __('Thanks') }},<br>
{{ config('app.name') }}
</x-mail::message>

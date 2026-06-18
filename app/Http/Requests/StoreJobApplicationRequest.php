<?php

namespace App\Http\Requests;

use App\Models\JobPosting;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreJobApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var JobPosting|null $jobPosting */
        $jobPosting = $this->route('jobPosting');

        return $jobPosting instanceof JobPosting && $jobPosting->isPubliclyVisible();
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'candidate_name' => ['required', 'string', 'max:255'],
            'candidate_email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
            'candidate_phone' => ['required', 'string', 'max:50'],
            'linkedin_url' => ['nullable', 'string', 'url', 'max:500'],
            'portfolio_url' => ['nullable', 'string', 'url', 'max:500'],
            'cover_letter' => ['nullable', 'string', 'max:5000'],
            'skills' => ['required', 'string', 'max:2000'],
            'years_of_experience' => ['required', 'integer', 'min:0', 'max:50'],
            'salary_expectation' => ['required', 'string', 'max:255'],
            'preferred_location' => ['required', 'string', 'max:255'],
            'resume' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:5120'],
        ];
    }
}

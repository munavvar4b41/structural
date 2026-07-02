<?php

namespace App\Http\Requests\Admin;

use App\Models\CaseStudy;
use App\Models\Project;
use App\Support\CaseStudyValidation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCaseStudyRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var CaseStudy|null $caseStudy */
        $caseStudy = $this->route('case_study');

        if (! $caseStudy instanceof CaseStudy) {
            return false;
        }

        return $this->user()?->can('update', $caseStudy) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        CaseStudyValidation::prepareRichTextFields($merge, $this);

        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    /**
     * @return array<string, array<int, ValidationRule|string|\Closure>>
     */
    public function rules(): array
    {
        /** @var Project $project */
        $project = $this->route('project');

        $rules = CaseStudyValidation::rules($project);

        $rules['remove_attachment_ids'] = ['nullable', 'array'];
        $rules['remove_attachment_ids.*'] = [
            'integer',
            Rule::exists('case_study_attachments', 'id')->where('case_study_id', $this->route('case_study')?->id),
        ];

        return $rules;
    }
}

<?php

namespace App\Http\Requests\Admin;

use App\Models\CaseStudy;
use App\Models\Project;
use App\Support\CaseStudyValidation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCaseStudyRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Project|null $project */
        $project = $this->route('project');

        if (! $project instanceof Project) {
            return false;
        }

        return $this->user()?->can('create', [CaseStudy::class, $project]) ?? false;
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

        return CaseStudyValidation::rules($project);
    }
}

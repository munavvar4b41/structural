<?php

namespace App\Support;

use App\Enums\WorkloadPeriod;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class CaseStudyValidation
{
    public const RICH_TEXT_MAX_LENGTH = 500_000;

    /**
     * @return list<string>
     */
    public static function richTextFieldNames(): array
    {
        return [
            'client_issue',
            'business_impact',
            'solution_discovery',
            'proposed_solution',
            'implementation',
            'resolution',
            'workload_reduction_details',
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(Project $project): array
    {
        $taskIds = $project->tasks()->pluck('id')->all();

        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'summary' => ['nullable', 'string', 'max:2000'],
            'project_task_id' => ['nullable', 'integer', Rule::in($taskIds)],
            'workload_hours_saved' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
            'workload_percentage_reduction' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'workload_period' => ['nullable', 'string', Rule::enum(WorkloadPeriod::class)],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx', 'max:10240'],
        ];

        foreach (self::richTextFieldNames() as $field) {
            $rules[$field] = [
                'nullable',
                'string',
                'max:'.self::RICH_TEXT_MAX_LENGTH,
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === null || $value === '') {
                        return;
                    }
                    if (! is_string($value) || ! TipTapDocument::isValidDocumentJson($value)) {
                        $fail(__('The :attribute must be valid rich text.'));
                    }
                },
            ];
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $merge
     */
    public static function prepareRichTextFields(array &$merge, Request $request): void
    {
        if ($request->has('project_task_id') && $request->input('project_task_id') === '') {
            $merge['project_task_id'] = null;
        }

        foreach (self::richTextFieldNames() as $field) {
            $value = $request->input($field);
            if (is_string($value) && $value !== '' && ! TipTapDocument::isValidDocumentJson($value)) {
                $merge[$field] = (string) json_encode([
                    'type' => 'doc',
                    'content' => [
                        [
                            'type' => 'paragraph',
                            'content' => [
                                ['type' => 'text', 'text' => $value],
                            ],
                        ],
                    ],
                ]);
            }
        }

        if ($request->has('workload_hours_saved') && $request->input('workload_hours_saved') === '') {
            $merge['workload_hours_saved'] = null;
        }

        if ($request->has('workload_percentage_reduction') && $request->input('workload_percentage_reduction') === '') {
            $merge['workload_percentage_reduction'] = null;
        }

        if ($request->has('workload_period') && $request->input('workload_period') === '') {
            $merge['workload_period'] = null;
        }
    }
}

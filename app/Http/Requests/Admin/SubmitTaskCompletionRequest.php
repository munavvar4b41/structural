<?php

namespace App\Http\Requests\Admin;

use App\Enums\ProjectTaskStatus;
use App\Models\ProjectTask;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SubmitTaskCompletionRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ProjectTask|null $task */
        $task = $this->route('task');

        if (! $task instanceof ProjectTask) {
            return false;
        }

        return $this->user()?->can('submitCompletion', $task) ?? false;
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var ProjectTask $task */
                $task = $this->route('task');

                if (in_array($task->status, [
                    ProjectTaskStatus::Review,
                    ProjectTaskStatus::Done,
                    ProjectTaskStatus::Cancelled,
                ], true)) {
                    $validator->errors()->add(
                        'task',
                        __('This task cannot be submitted for completion in its current state.'),
                    );
                }
            },
        ];
    }
}

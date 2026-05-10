<?php

namespace App\Http\Requests\Admin;

use App\Enums\ProjectTaskStatus;
use App\Models\ProjectTask;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Illuminate\Support\Facades\Gate;

class ConfirmTaskCompletionRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ProjectTask|null $task */
        $task = $this->route('task');
        
        if (! $task instanceof ProjectTask) {
            return false;
        }
        
        return Gate::check('confirmCompletion', $task);
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        /** @var ProjectTask $task */
        $task = $this->route('task');

        return [
            'review_notes' => ['nullable', 'string', 'max:10000'],
            'task_rating' => ['required', 'integer', 'min:1', 'max:5'],
            'assignee_rating' => [
                Rule::requiredIf($task->assignee_user_id !== null),
                'nullable',
                'integer',
                'min:1',
                'max:5',
            ],
            'creator_rating' => ['required', 'integer', 'min:1', 'max:5'],
        ];
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

                if ($task->status !== ProjectTaskStatus::Review) {
                    $validator->errors()->add(
                        'task',
                        __('Only tasks awaiting review can be confirmed.'),
                    );
                }
            },
        ];
    }
}

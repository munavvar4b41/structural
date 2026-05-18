<?php

namespace App\Http\Requests\Api\Desktop;

use Illuminate\Foundation\Http\FormRequest;

class DesktopTimerStartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'task_id' => ['required', 'integer', 'exists:project_tasks,id'],
        ];
    }
}

<?php

namespace App\Http\Requests\Admin;

use App\Models\ProjectRequirement;
use App\Models\ProjectRequirementMessage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreProjectRequirementMessageRequest extends FormRequest
{
    public const BODY_MAX_LENGTH = 10_000;

    public function authorize(): bool
    {
        /** @var ProjectRequirement|null $requirement */
        $requirement = $this->route('requirement');

        if (! $requirement instanceof ProjectRequirement) {
            return false;
        }

        return $this->user()?->can('create', [ProjectRequirementMessage::class, $requirement]) ?? false;
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:'.self::BODY_MAX_LENGTH],
        ];
    }
}

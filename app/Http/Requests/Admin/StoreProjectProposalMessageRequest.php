<?php

namespace App\Http\Requests\Admin;

use App\Models\ProjectProposal;
use App\Models\ProjectProposalMessage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreProjectProposalMessageRequest extends FormRequest
{
    public const BODY_MAX_LENGTH = 10_000;

    public function authorize(): bool
    {
        /** @var ProjectProposal|null $proposal */
        $proposal = $this->route('proposal');

        if (! $proposal instanceof ProjectProposal) {
            return false;
        }

        return $this->user()?->can('create', [ProjectProposalMessage::class, $proposal]) ?? false;
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

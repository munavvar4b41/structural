<?php

namespace App\Http\Requests\Admin;

use App\Models\ProjectProposal;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReviewProjectProposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        $proposal = $this->resolveProposal();

        if ($proposal === null) {
            return false;
        }

        $ability = (string) $this->route()->getActionMethod();

        return match ($ability) {
            'confirm' => $this->user()?->can('confirm', $proposal) ?? false,
            'reject' => $this->user()?->can('reject', $proposal) ?? false,
            default => false,
        };
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        $ability = (string) $this->route()->getActionMethod();

        return match ($ability) {
            'reject' => [
                'rejection_reason' => ['nullable', 'string', 'max:2000'],
            ],
            default => [
                'review_notes' => ['nullable', 'string', 'max:5000'],
            ],
        };
    }

    private function resolveProposal(): ?ProjectProposal
    {
        $proposal = $this->route('proposal');

        return $proposal instanceof ProjectProposal ? $proposal : null;
    }
}

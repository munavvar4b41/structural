<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\AuthorizesProjectPasswordRequest;
use App\Models\Project;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreProjectPassphraseRequest extends FormRequest
{
    use AuthorizesProjectPasswordRequest;

    public function authorize(): bool
    {
        $project = $this->route('project');

        if (! $project instanceof Project) {
            return false;
        }

        return $this->authorizeProjectPassword('viewAny');
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'passphrase' => ['required', 'string', 'min:12', 'max:1024', 'confirmed'],
        ];
    }
}

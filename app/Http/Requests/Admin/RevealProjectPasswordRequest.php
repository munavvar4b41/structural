<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\AuthorizesProjectPasswordRequest;
use App\Http\Requests\Admin\Concerns\ValidatesProjectPasswordInput;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RevealProjectPasswordRequest extends FormRequest
{
    use AuthorizesProjectPasswordRequest;
    use ValidatesProjectPasswordInput;

    public function authorize(): bool
    {
        return $this->authorizeProjectPassword('reveal');
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return $this->passphraseRules();
    }
}

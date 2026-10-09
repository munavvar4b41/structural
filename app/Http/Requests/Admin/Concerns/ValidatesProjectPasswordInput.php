<?php

namespace App\Http\Requests\Admin\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;

trait ValidatesProjectPasswordInput
{
    protected function prepareForValidation(): void
    {
        $merge = [];

        foreach (['username', 'url', 'notes', 'secret'] as $field) {
            if ($this->has($field) && is_string($this->input($field)) && trim($this->input($field)) === '') {
                $merge[$field] = null;
            }
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    protected function passphraseRules(): array
    {
        return [
            'passphrase' => ['required', 'string', 'max:1024'],
        ];
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    protected function projectPasswordFieldRules(bool $secretRequired): array
    {
        return [
            'label' => ['required', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:255'],
            'url' => ['nullable', 'string', 'max:2048', 'url'],
            'secret' => [$secretRequired ? 'required' : 'nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'clear_notes' => ['sometimes', 'boolean'],
        ];
    }
}

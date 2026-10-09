<?php

namespace App\Support;

use App\Models\Project;
use Illuminate\Validation\ValidationException;

class ProjectPasswordCipher
{
    private const string VERIFIER_PLAINTEXT = 'project-passphrase-ok';

    public function seal(Project $project, string $passphrase): void
    {
        if ($project->hasPasswordPassphrase()) {
            throw ValidationException::withMessages([
                'passphrase' => __('This project already has a passphrase.'),
            ]);
        }

        $salt = random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES);
        $key = $this->deriveKey($passphrase, $salt);

        try {
            $verifier = $this->encrypt($key, self::VERIFIER_PLAINTEXT);

            $project->forceFill([
                'password_kdf_salt' => $salt,
                'password_verifier_nonce' => $verifier['nonce'],
                'password_verifier_ciphertext' => $verifier['ciphertext'],
            ])->save();
        } finally {
            sodium_memzero($key);
        }
    }

    public function verifiedKey(Project $project, string $passphrase): string
    {
        if (! $project->hasPasswordPassphrase()) {
            throw ValidationException::withMessages([
                'passphrase' => __('Set a project passphrase before storing passwords.'),
            ]);
        }

        $salt = $project->password_kdf_salt;
        $nonce = $project->password_verifier_nonce;
        $ciphertext = $project->password_verifier_ciphertext;

        if (! is_string($salt) || ! is_string($nonce) || ! is_string($ciphertext)) {
            throw ValidationException::withMessages([
                'passphrase' => __('The project passphrase is incorrect.'),
            ]);
        }

        $key = $this->deriveKey($passphrase, $salt);
        $opened = sodium_crypto_secretbox_open($ciphertext, $nonce, $key);

        if ($opened === false || ! hash_equals(self::VERIFIER_PLAINTEXT, $opened)) {
            sodium_memzero($key);

            throw ValidationException::withMessages([
                'passphrase' => __('The project passphrase is incorrect.'),
            ]);
        }

        return $key;
    }

    /**
     * @return array{nonce: string, ciphertext: string}
     */
    public function encrypt(string $key, string $plaintext): array
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return [
            'nonce' => $nonce,
            'ciphertext' => sodium_crypto_secretbox($plaintext, $nonce, $key),
        ];
    }

    /**
     * @return array{nonce: string|null, ciphertext: string|null}
     */
    public function encryptOptional(string $key, ?string $plaintext): array
    {
        if ($plaintext === null || $plaintext === '') {
            return [
                'nonce' => null,
                'ciphertext' => null,
            ];
        }

        return $this->encrypt($key, $plaintext);
    }

    public function decrypt(string $key, string $nonce, string $ciphertext): string
    {
        $opened = sodium_crypto_secretbox_open($ciphertext, $nonce, $key);

        if ($opened === false) {
            throw ValidationException::withMessages([
                'passphrase' => __('The stored password could not be decrypted.'),
            ]);
        }

        return $opened;
    }

    private function deriveKey(string $passphrase, string $salt): string
    {
        return sodium_crypto_pwhash(
            SODIUM_CRYPTO_SECRETBOX_KEYBYTES,
            $passphrase,
            $salt,
            (int) config('project_passwords.ops_limit'),
            (int) config('project_passwords.mem_limit'),
            SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13,
        );
    }
}

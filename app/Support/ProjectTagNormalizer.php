<?php

namespace App\Support;

class ProjectTagNormalizer
{
    public static function normalize(string $value): string
    {
        $normalized = strtolower(trim($value));
        $normalized = preg_replace('/[\s_]+/', '-', $normalized) ?? $normalized;
        $normalized = preg_replace('/[^a-z0-9-]+/', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/-+/', '-', $normalized) ?? $normalized;

        return trim($normalized, '-');
    }

    public static function isValid(string $value): bool
    {
        if ($value === '' || strlen($value) > 64) {
            return false;
        }

        return (bool) preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value);
    }
}

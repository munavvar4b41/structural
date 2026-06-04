<?php

namespace App\Support;

class ProjectMetadataNormalizer
{
    public static function normalizeKey(string $value): string
    {
        return strtolower(trim($value));
    }

    public static function normalizeValue(string $value): string
    {
        return trim($value);
    }

    public static function isValidKey(string $value): bool
    {
        return $value !== '' && strlen($value) <= 128;
    }

    public static function isValidValue(string $value): bool
    {
        return $value !== '' && strlen($value) <= 500;
    }
}

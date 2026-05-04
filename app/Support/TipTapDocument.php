<?php

namespace App\Support;

use Illuminate\Support\Str;

final class TipTapDocument
{
    /**
     * @param  array<string, mixed>  $node
     */
    public static function extractPlainText(array $node): string
    {
        $parts = [];
        if (isset($node['text']) && is_string($node['text'])) {
            $parts[] = $node['text'];
        }
        if (isset($node['content']) && is_array($node['content'])) {
            foreach ($node['content'] as $child) {
                if (is_array($child)) {
                    $parts[] = self::extractPlainText($child);
                }
            }
        }

        return trim(preg_replace('/\s+/', ' ', implode(' ', $parts)) ?? '');
    }

    public static function previewFromStored(?string $stored, int $limit = 200): ?string
    {
        if ($stored === null || $stored === '') {
            return null;
        }

        $decoded = json_decode($stored, true);
        if (is_array($decoded) && ($decoded['type'] ?? null) === 'doc') {
            return Str::limit(self::extractPlainText($decoded), $limit) ?: null;
        }

        return Str::limit($stored, $limit);
    }

    public static function isValidDocumentJson(?string $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) && ($decoded['type'] ?? null) === 'doc';
    }

    /**
     * True when the value is valid TipTap doc JSON with non-empty visible text.
     */
    public static function isSubstantiveDocumentJson(?string $value): bool
    {
        if ($value === null || $value === '' || ! self::isValidDocumentJson($value)) {
            return false;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) && self::extractPlainText($decoded) !== '';
    }
}

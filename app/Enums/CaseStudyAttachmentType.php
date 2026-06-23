<?php

namespace App\Enums;

enum CaseStudyAttachmentType: string
{
    case Image = 'image';
    case Document = 'document';

    public static function fromMime(string $mime): self
    {
        if (str_starts_with($mime, 'image/')) {
            return self::Image;
        }

        return self::Document;
    }
}

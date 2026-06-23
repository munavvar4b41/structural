<?php

namespace App\Models;

use App\Enums\CaseStudyAttachmentType;
use Database\Factories\CaseStudyAttachmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'case_study_id',
    'path',
    'original_name',
    'mime',
    'type',
    'sort_order',
])]
class CaseStudyAttachment extends Model
{
    /** @use HasFactory<CaseStudyAttachmentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CaseStudyAttachmentType::class,
        ];
    }

    public function caseStudy(): BelongsTo
    {
        return $this->belongsTo(CaseStudy::class);
    }
}

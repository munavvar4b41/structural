<?php

namespace App\Support;

use App\Enums\CaseStudyAttachmentType;
use App\Models\CaseStudy;
use App\Models\CaseStudyAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class CaseStudyAttachmentStorage
{
    /**
     * @param  list<UploadedFile>  $files
     */
    public function storeMany(CaseStudy $caseStudy, array $files): void
    {
        $sortOrder = (int) $caseStudy->attachments()->max('sort_order');

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $sortOrder++;
            $this->storeOne($caseStudy, $file, $sortOrder);
        }
    }

    public function storeOne(CaseStudy $caseStudy, UploadedFile $file, int $sortOrder): CaseStudyAttachment
    {
        $extension = $file->getClientOriginalExtension() ?: $file->extension() ?: 'bin';
        $path = $file->storeAs(
            'case-studies/'.$caseStudy->id,
            Str::uuid()->toString().'.'.$extension,
            'local',
        );

        $mime = (string) $file->getMimeType();

        return $caseStudy->attachments()->create([
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $mime,
            'type' => CaseStudyAttachmentType::fromMime($mime),
            'sort_order' => $sortOrder,
        ]);
    }

    /**
     * @param  list<int>  $attachmentIds
     */
    public function deleteMany(CaseStudy $caseStudy, array $attachmentIds): void
    {
        if ($attachmentIds === []) {
            return;
        }

        $attachments = $caseStudy->attachments()
            ->whereIn('id', $attachmentIds)
            ->get();

        $this->deleteAttachments($attachments);
    }

    /**
     * @param  Collection<int, CaseStudyAttachment>  $attachments
     */
    public function deleteAttachments(Collection $attachments): void
    {
        foreach ($attachments as $attachment) {
            if (Storage::disk('local')->exists($attachment->path)) {
                Storage::disk('local')->delete($attachment->path);
            }

            $attachment->delete();
        }
    }

    public function deleteAllForCaseStudy(CaseStudy $caseStudy): void
    {
        $this->deleteAttachments($caseStudy->attachments()->get());
    }
}

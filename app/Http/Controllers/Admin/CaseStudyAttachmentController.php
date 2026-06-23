<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CaseStudy;
use App\Models\CaseStudyAttachment;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CaseStudyAttachmentController extends Controller
{
    use AuthorizesRequests;

    public function show(CaseStudy $caseStudy, CaseStudyAttachment $attachment): StreamedResponse
    {
        $this->ensureAttachmentBelongsToCaseStudy($caseStudy, $attachment);
        $this->authorize('downloadAttachment', $caseStudy);

        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

        return Storage::disk('local')->download(
            $attachment->path,
            $attachment->original_name,
            ['Content-Type' => $attachment->mime],
        );
    }

    public function destroy(CaseStudy $caseStudy, CaseStudyAttachment $attachment): RedirectResponse
    {
        $this->ensureAttachmentBelongsToCaseStudy($caseStudy, $attachment);
        $this->authorize('update', $caseStudy);

        $actor = request()->user();
        abort_if(! $actor instanceof User, 403);

        if (Storage::disk('local')->exists($attachment->path)) {
            Storage::disk('local')->delete($attachment->path);
        }

        $attachment->delete();

        return back()->with('toast', __('Attachment removed.'));
    }

    private function ensureAttachmentBelongsToCaseStudy(CaseStudy $caseStudy, CaseStudyAttachment $attachment): void
    {
        abort_if($attachment->case_study_id !== $caseStudy->id, 404);
    }
}

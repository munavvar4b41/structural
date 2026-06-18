<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\JobApplication;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class JobApplicationResumeController extends Controller
{
    use AuthorizesRequests;

    public function show(JobApplication $jobApplication): StreamedResponse
    {
        $actor = request()->user();
        abort_if(! $actor instanceof User || ! $actor->can('downloadResume', $jobApplication), 403);

        abort_unless(Storage::disk('local')->exists($jobApplication->resume_path), 404);

        return Storage::disk('local')->download(
            $jobApplication->resume_path,
            $jobApplication->resume_original_name,
            ['Content-Type' => $jobApplication->resume_mime],
        );
    }
}

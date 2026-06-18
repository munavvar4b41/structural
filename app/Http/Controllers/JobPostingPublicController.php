<?php

namespace App\Http\Controllers;

use App\Models\JobPosting;
use App\Support\TipTapDocument;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class JobPostingPublicController extends Controller
{
    public function index(): Response
    {
        $postings = JobPosting::query()
            ->publiclyVisible()
            ->with('team:id,name')
            ->orderByDesc('published_at')
            ->orderBy('title')
            ->get()
            ->map(static fn (JobPosting $posting): array => [
                'slug' => $posting->slug,
                'title' => $posting->title,
                'location' => $posting->location,
                'employment_type' => $posting->employment_type->value,
                'employment_type_label' => $posting->employment_type->label(),
                'team_name' => $posting->team?->name,
                'description_preview' => TipTapDocument::previewFromStored($posting->description, 160),
            ]);

        return Inertia::render('careers/Index', [
            'job_postings' => $postings,
        ]);
    }

    public function show(Request $request, JobPosting $jobPosting): Response
    {
        abort_unless($jobPosting->isPubliclyVisible(), 404);

        $jobPosting->load('team:id,name');

        return Inertia::render('careers/Show', [
            'job_posting' => [
                'slug' => $jobPosting->slug,
                'title' => $jobPosting->title,
                'location' => $jobPosting->location,
                'employment_type' => $jobPosting->employment_type->value,
                'employment_type_label' => $jobPosting->employment_type->label(),
                'team_name' => $jobPosting->team?->name,
                'description' => $jobPosting->description,
                'requirements' => $jobPosting->requirements,
            ],
        ]);
    }
}

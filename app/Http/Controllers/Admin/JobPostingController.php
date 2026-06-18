<?php

namespace App\Http\Controllers\Admin;

use App\Enums\JobEmploymentType;
use App\Enums\JobPostingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreJobPostingRequest;
use App\Http\Requests\Admin\UpdateJobPostingRequest;
use App\Models\JobPosting;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class JobPostingController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', JobPosting::class);

        $search = trim((string) $request->query('search', ''));
        $statusQuery = $request->query('status');
        $statusFilter = is_string($statusQuery) ? $statusQuery : '';
        $statusApplied = $statusFilter !== '' && in_array($statusFilter, JobPostingStatus::values(), true);

        $postings = JobPosting::query()
            ->with(['team:id,name', 'createdBy:id,name'])
            ->withCount('applications')
            ->when($search !== '', static function ($query) use ($search): void {
                $term = '%'.addcslashes($search, '%_\\').'%';
                $query->where(static function ($query) use ($term): void {
                    $query->where('title', 'like', $term)
                        ->orWhere('slug', 'like', $term)
                        ->orWhere('location', 'like', $term);
                });
            })
            ->when($statusApplied, static fn ($query) => $query->where('status', $statusFilter))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString()
            ->through(static fn (JobPosting $posting): array => [
                'id' => $posting->id,
                'slug' => $posting->slug,
                'title' => $posting->title,
                'location' => $posting->location,
                'employment_type' => $posting->employment_type->value,
                'employment_type_label' => $posting->employment_type->label(),
                'status' => $posting->status->value,
                'status_label' => $posting->status->label(),
                'team' => $posting->team ? [
                    'id' => $posting->team->id,
                    'name' => $posting->team->name,
                ] : null,
                'applications_count' => $posting->applications_count,
                'published_at' => $posting->published_at?->toIso8601String(),
                'closes_at' => $posting->closes_at?->toIso8601String(),
                'created_by' => $posting->createdBy ? [
                    'id' => $posting->createdBy->id,
                    'name' => $posting->createdBy->name,
                ] : null,
            ]);

        return Inertia::render('admin/job-postings/Index', [
            'job_postings' => $postings,
            'filters' => [
                'search' => $search,
                'status' => $statusApplied ? $statusFilter : '',
            ],
            'status_options' => $this->statusOptions(),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', JobPosting::class);

        return Inertia::render('admin/job-postings/Create', [
            'teams' => $this->teamOptions(),
            'status_options' => $this->statusOptions(),
            'employment_type_options' => $this->employmentTypeOptions(),
        ]);
    }

    public function store(StoreJobPostingRequest $request): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        JobPosting::query()->create([
            ...$request->validated(),
            'created_by_user_id' => $actor->id,
        ]);

        return to_route('admin.job-postings.index')->with('toast', __('Job posting created.'));
    }

    public function edit(JobPosting $jobPosting): Response
    {
        $this->authorize('update', $jobPosting);

        return Inertia::render('admin/job-postings/Edit', [
            'job_posting' => [
                'id' => $jobPosting->id,
                'slug' => $jobPosting->slug,
                'title' => $jobPosting->title,
                'team_id' => $jobPosting->team_id,
                'location' => $jobPosting->location,
                'employment_type' => $jobPosting->employment_type->value,
                'description' => $jobPosting->description,
                'requirements' => $jobPosting->requirements,
                'status' => $jobPosting->status->value,
                'published_at' => $jobPosting->published_at?->format('Y-m-d\TH:i'),
                'closes_at' => $jobPosting->closes_at?->format('Y-m-d\TH:i'),
            ],
            'teams' => $this->teamOptions(),
            'status_options' => $this->statusOptions(),
            'employment_type_options' => $this->employmentTypeOptions(),
        ]);
    }

    public function update(UpdateJobPostingRequest $request, JobPosting $jobPosting): RedirectResponse
    {
        $jobPosting->update($request->validated());

        return to_route('admin.job-postings.index')->with('toast', __('Job posting updated.'));
    }

    public function destroy(JobPosting $jobPosting): RedirectResponse
    {
        $this->authorize('delete', $jobPosting);

        $jobPosting->delete();

        return to_route('admin.job-postings.index')->with('toast', __('Job posting deleted.'));
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        return array_map(
            static fn (JobPostingStatus $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
            ],
            JobPostingStatus::cases(),
        );
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function employmentTypeOptions(): array
    {
        return array_map(
            static fn (JobEmploymentType $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
            ],
            JobEmploymentType::cases(),
        );
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    private function teamOptions(): array
    {
        return Team::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(static fn (Team $team): array => [
                'value' => $team->id,
                'label' => $team->name,
            ])
            ->all();
    }
}

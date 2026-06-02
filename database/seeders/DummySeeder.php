<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\ProjectRequirement;
use App\Models\ProjectTask;
use App\Models\TaskTimeEntry;
use App\Models\Team;
use App\Models\User;
use App\Models\ProjectTaskReview;
use Illuminate\Database\Seeder;

class DummySeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->count(10)->create();
        Team::factory()->count(10)->create();
        Project::factory()->count(10)->create();
        ProjectRequirement::factory()->count(10)->create()
            ->each(function (ProjectRequirement $requirement) {
                ProjectTask::factory()->withRequirement($requirement)->count(10)
                    ->create()->each(function (ProjectTask $task) {
                        TaskTimeEntry::factory()->forTask($task)->count(10)->create();
                    });
            });
        ProjectTaskReview::factory()->count(10)->create()
            ->each(function (ProjectTaskReview $review) {
                TaskTimeEntry::factory()->forTask($review->task)->count(10)->create();
            });
    }
}

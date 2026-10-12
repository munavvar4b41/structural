<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('project_tasks', function (Blueprint $table) {
            $table->dropForeign(['task_priority_id']);
        });

        Schema::rename('task_priorities', 'task_tags');

        Schema::create('project_task_task_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_task_id')->constrained('project_tasks')->cascadeOnDelete();
            $table->foreignId('task_tag_id')->constrained('task_tags')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['project_task_id', 'task_tag_id']);
        });

        $now = now();

        DB::table('project_tasks')
            ->whereNotNull('task_priority_id')
            ->select(['id', 'task_priority_id'])
            ->orderBy('id')
            ->chunk(500, function ($tasks) use ($now): void {
                DB::table('project_task_task_tag')->insert(
                    $tasks->map(static fn (object $task): array => [
                        'project_task_id' => $task->id,
                        'task_tag_id' => $task->task_priority_id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all(),
                );
            });

        Schema::table('project_tasks', function (Blueprint $table) {
            $table->dropColumn('task_priority_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_tasks', function (Blueprint $table) {
            $table->unsignedBigInteger('task_priority_id')->nullable()->after('status');
        });

        DB::table('project_task_task_tag')
            ->selectRaw('project_task_id, MIN(task_tag_id) as task_tag_id')
            ->groupBy('project_task_id')
            ->orderBy('project_task_id')
            ->chunk(500, function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('project_tasks')
                        ->where('id', $row->project_task_id)
                        ->update(['task_priority_id' => $row->task_tag_id]);
                }
            });

        Schema::dropIfExists('project_task_task_tag');

        Schema::rename('task_tags', 'task_priorities');

        Schema::table('project_tasks', function (Blueprint $table) {
            $table->foreign('task_priority_id')->references('id')->on('task_priorities')->restrictOnDelete();
        });
    }
};

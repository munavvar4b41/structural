<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_time_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_task_id')->constrained('project_tasks')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('source');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'ended_at']);
            $table->index(['user_id', 'started_at']);
            $table->index(['project_id', 'started_at']);
            $table->index(['project_task_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_time_entries');
    }
};

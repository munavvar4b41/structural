<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('project_task_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_task_id')->constrained('project_tasks')->cascadeOnDelete();
            $table->foreignId('reviewer_user_id')->constrained('users')->restrictOnDelete();
            $table->text('review_notes')->nullable();
            $table->unsignedTinyInteger('task_rating');
            $table->unsignedTinyInteger('assignee_rating')->nullable();
            $table->unsignedTinyInteger('creator_rating')->nullable();
            $table->timestamps();

            $table->index(['project_task_id', 'created_at']);
            $table->index('reviewer_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_task_reviews');
    }
};

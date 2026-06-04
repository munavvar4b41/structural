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
        if (
            Schema::hasTable('project_requirement_estimations')
            && ! Schema::hasTable('project_requirement_estimation_items')
        ) {
            Schema::drop('project_requirement_estimations');
        }

        if (Schema::hasTable('project_requirement_estimations')) {
            return;
        }

        Schema::create('project_requirement_estimations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_requirement_id')->constrained('project_requirements')->cascadeOnDelete();
            $table->unsignedSmallInteger('version')->default(1);
            $table->string('status');
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('submission_notes')->nullable();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamp('transferred_at')->nullable();
            $table->foreignId('transferred_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('superseded_by_estimation_id')
                ->nullable()
                ->constrained('project_requirement_estimations', 'id', 'pr_est_superseded_by_fk')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['project_requirement_id', 'status'], 'pr_est_req_status_idx');
            $table->index(['submitted_to_user_id', 'status'], 'pr_est_submitted_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_requirement_estimations');
    }
};

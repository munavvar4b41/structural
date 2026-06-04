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
        Schema::create('project_requirement_estimation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_requirement_estimation_id')
                ->constrained('project_requirement_estimations', 'id', 'pr_est_items_estimation_fk')
                ->cascadeOnDelete();
            $table->foreignId('parent_estimation_item_id')
                ->nullable()
                ->constrained('project_requirement_estimation_items', 'id', 'pr_est_items_parent_fk')
                ->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('estimated_minutes')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['project_requirement_estimation_id', 'sort_order'], 'pr_est_items_est_sort_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_requirement_estimation_items');
    }
};

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
        Schema::table('project_tasks', function (Blueprint $table) {
            $table->foreignId('project_requirement_estimation_item_id')
                ->nullable()
                ->after('project_requirement_id')
                ->constrained('project_requirement_estimation_items', 'id', 'project_tasks_pr_est_item_fk')
                ->nullOnDelete();

            $table->unique('project_requirement_estimation_item_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_requirement_estimation_item_id');
        });
    }
};

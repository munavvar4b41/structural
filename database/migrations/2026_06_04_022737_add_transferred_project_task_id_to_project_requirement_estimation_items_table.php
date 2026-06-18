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
        Schema::table('project_requirement_estimation_items', function (Blueprint $table) {
            $table->foreignId('transferred_project_task_id')
                ->nullable()
                ->after('sort_order')
                ->constrained('project_tasks', 'id', 'pr_est_items_transferred_task_fk')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_requirement_estimation_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('transferred_project_task_id');
        });
    }
};

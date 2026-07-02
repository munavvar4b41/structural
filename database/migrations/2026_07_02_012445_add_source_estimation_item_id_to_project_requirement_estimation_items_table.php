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
        Schema::table('project_requirement_estimation_items', function (Blueprint $table): void {
            $table->foreignId('source_estimation_item_id')
                ->nullable()
                ->after('parent_estimation_item_id')
                ->constrained('project_requirement_estimation_items', 'id', 'pr_est_items_source_fk')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_requirement_estimation_items', function (Blueprint $table): void {
            $table->dropForeign('pr_est_items_source_fk');
            $table->dropColumn('source_estimation_item_id');
        });
    }
};

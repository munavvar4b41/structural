<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Recover from a failed partial migrate that created project_requirement_estimations
     * without recording the migration or creating related tables.
     */
    public function up(): void
    {
        if (! Schema::hasTable('project_requirement_estimations')) {
            return;
        }

        if (Schema::hasTable('project_requirement_estimation_items')) {
            return;
        }

        if (DB::table('project_requirement_estimations')->exists()) {
            return;
        }

        Schema::drop('project_requirement_estimations');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_requirements', function (Blueprint $table): void {
            $table->unsignedSmallInteger('max_generated_phase')->default(1)->after('understanding_confirmed_by_user_id');
        });

        Schema::table('project_requirement_estimation_items', function (Blueprint $table): void {
            $table->unsignedSmallInteger('phase')->default(1)->after('sort_order');
        });

        Schema::table('project_tasks', function (Blueprint $table): void {
            $table->unsignedSmallInteger('phase')->nullable()->after('estimated_minutes');
        });

        DB::table('project_requirement_estimation_items')->update(['phase' => 1]);

        DB::table('project_tasks')
            ->whereNotNull('project_requirement_id')
            ->update(['phase' => 1]);
    }

    public function down(): void
    {
        Schema::table('project_tasks', function (Blueprint $table): void {
            $table->dropColumn('phase');
        });

        Schema::table('project_requirement_estimation_items', function (Blueprint $table): void {
            $table->dropColumn('phase');
        });

        Schema::table('project_requirements', function (Blueprint $table): void {
            $table->dropColumn('max_generated_phase');
        });
    }
};

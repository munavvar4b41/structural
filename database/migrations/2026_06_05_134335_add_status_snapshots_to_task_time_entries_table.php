<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_time_entries', function (Blueprint $table): void {
            $table->json('status_snapshots')->nullable()->after('previous_task_status');
        });
    }

    public function down(): void
    {
        Schema::table('task_time_entries', function (Blueprint $table): void {
            $table->dropColumn('status_snapshots');
        });
    }
};

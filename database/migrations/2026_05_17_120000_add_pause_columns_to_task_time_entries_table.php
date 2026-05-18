<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_time_entries', function (Blueprint $table): void {
            $table->timestamp('paused_at')->nullable()->after('ended_at');
            $table->unsignedInteger('accumulated_pause_seconds')->default(0)->after('paused_at');
        });
    }

    public function down(): void
    {
        Schema::table('task_time_entries', function (Blueprint $table): void {
            $table->dropColumn(['paused_at', 'accumulated_pause_seconds']);
        });
    }
};

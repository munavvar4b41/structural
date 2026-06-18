<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_time_entries', function (Blueprint $table): void {
            $table->string('pause_reason', 32)->nullable()->after('paused_at');
            $table->string('resumed_by', 32)->nullable()->after('pause_reason');
            $table->timestamp('last_client_event_at')->nullable()->after('resumed_by');
        });
    }

    public function down(): void
    {
        Schema::table('task_time_entries', function (Blueprint $table): void {
            $table->dropColumn(['pause_reason', 'resumed_by', 'last_client_event_at']);
        });
    }
};

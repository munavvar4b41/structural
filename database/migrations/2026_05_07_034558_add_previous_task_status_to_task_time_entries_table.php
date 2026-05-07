<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_time_entries', function (Blueprint $table): void {
            $table->string('previous_task_status')->nullable()->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('task_time_entries', function (Blueprint $table): void {
            $table->dropColumn('previous_task_status');
        });
    }
};

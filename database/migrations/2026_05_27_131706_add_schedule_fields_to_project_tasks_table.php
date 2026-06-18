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
            $table->timestamp('display_after_at')->nullable()->after('estimated_minutes');
            $table->timestamp('notify_at')->nullable()->after('display_after_at');
            $table->timestamp('notified_at')->nullable()->after('notify_at');

            $table->index(['notify_at', 'notified_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_tasks', function (Blueprint $table) {
            $table->dropIndex(['notify_at', 'notified_at']);
            $table->dropColumn(['display_after_at', 'notify_at', 'notified_at']);
        });
    }
};

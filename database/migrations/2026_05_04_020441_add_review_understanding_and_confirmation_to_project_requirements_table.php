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
        Schema::table('project_requirements', function (Blueprint $table) {
            $table->longText('review_understanding')->nullable()->after('reviewed_at');
            $table->timestamp('understanding_confirmed_at')->nullable()->after('review_understanding');
            $table->foreignId('understanding_confirmed_by_user_id')->nullable()->after('understanding_confirmed_at')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_requirements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('understanding_confirmed_by_user_id');
            $table->dropColumn(['understanding_confirmed_at', 'review_understanding']);
        });
    }
};

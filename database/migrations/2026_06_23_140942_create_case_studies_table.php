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
        Schema::create('case_studies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_task_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('title');
            $table->text('overview')->nullable();
            $table->text('client_issue')->nullable();
            $table->text('our_solution')->nullable();
            $table->text('implementation')->nullable();
            $table->text('other_details')->nullable();
            $table->text('result_and_impact')->nullable();
            $table->text('conclusion')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'created_at']);
            $table->index('project_task_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('case_studies');
    }
};

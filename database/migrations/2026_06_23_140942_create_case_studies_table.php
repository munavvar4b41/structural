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
            $table->text('summary')->nullable();
            $table->text('client_issue')->nullable();
            $table->text('proposed_solution')->nullable();
            $table->text('resolution')->nullable();
            $table->text('workload_reduction_details')->nullable();
            $table->decimal('workload_hours_saved', 8, 2)->nullable();
            $table->decimal('workload_percentage_reduction', 5, 2)->nullable();
            $table->string('workload_period')->nullable();
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

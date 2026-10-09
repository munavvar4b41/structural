<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_passwords', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('label');
            $table->string('username')->nullable();
            $table->string('url', 2048)->nullable();
            $table->binary('secret_nonce');
            $table->binary('secret_ciphertext');
            $table->binary('notes_nonce')->nullable();
            $table->binary('notes_ciphertext')->nullable();
            $table->timestamps();

            $table->index('label');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_passwords');
    }
};

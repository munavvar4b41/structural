<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->binary('password_kdf_salt')->nullable();
            $table->binary('password_verifier_nonce')->nullable();
            $table->binary('password_verifier_ciphertext')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn([
                'password_kdf_salt',
                'password_verifier_nonce',
                'password_verifier_ciphertext',
            ]);
        });
    }
};

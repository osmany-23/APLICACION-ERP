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
        Schema::table('user_api_tokens', function (Blueprint $table) {
            // Agregamos el campo last_used_at después de token_hash o abilities
            $table->timestamp('last_used_at')->nullable()->after('token_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_api_tokens', function (Blueprint $table) {
            $table->dropColumn('last_used_at');
        });
    }
};
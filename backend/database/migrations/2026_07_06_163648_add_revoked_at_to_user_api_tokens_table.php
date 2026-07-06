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
            // Agregamos el campo revoked_at después de expires_at
            $table->timestamp('revoked_at')->nullable()->after('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_api_tokens', function (Blueprint $table) {
            $table->dropColumn('revoked_at');
        });
    }
};
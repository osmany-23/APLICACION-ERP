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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            // ID del usuario que hace la acción (puede ser NULL si es un evento del sistema o antes de loguearse)
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('table_name'); // Ejemplo: 'users', 'products'
            $table->string('action_type'); // Ejemplo: 'LOGIN', 'CREATE', 'UPDATE'
            $table->unsignedBigInteger('record_id')->nullable(); // ID del registro afectado
            $table->json('old_values')->nullable(); // Valores antes del cambio
            $table->json('new_values')->nullable(); // Valores nuevos después del cambio
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * payment_terms.code nunca tuvo una restriccion UNIQUE real (solo el
     * indice compuesto no-unico [company_id, code] agregado en
     * 2026_07_13_000002_enhance_payment_terms_table.php). PaymentTermsSeeder
     * hace upsert($rows, ['code'], [...]) asumiendo que 'code' es unico,
     * lo cual falla en SQLite ("ON CONFLICT clause does not match any
     * PRIMARY KEY or UNIQUE constraint") y en MySQL simplemente inserta
     * filas duplicadas en cada reseed en vez de actualizar. Se agrega la
     * restriccion real, igual que ya la tiene payment_methods.code desde
     * su migracion original.
     */
    public function up(): void
    {
        if (! Schema::hasTable('payment_terms') || ! Schema::hasColumn('payment_terms', 'code')) {
            return;
        }

        if ($this->hasUniqueIndex('payment_terms', 'payment_terms_code_unique')) {
            return;
        }

        Schema::table('payment_terms', function (Blueprint $table) {
            $table->unique('code', 'payment_terms_code_unique');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('payment_terms') && $this->hasUniqueIndex('payment_terms', 'payment_terms_code_unique')) {
            Schema::table('payment_terms', function (Blueprint $table) {
                $table->dropUnique('payment_terms_code_unique');
            });
        }
    }

    private function hasUniqueIndex(string $table, string $indexName): bool
    {
        return collect(Schema::getIndexes($table))
            ->contains(fn (array $index) => ($index['name'] ?? null) === $indexName);
    }
};

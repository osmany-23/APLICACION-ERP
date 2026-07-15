<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_terms', function (Blueprint $table) {
            if (!Schema::hasColumn('payment_terms', 'company_id')) {
                $table->foreignId('company_id')->nullable()->after('id')->constrained('companies')->nullOnDelete();
            }

            if (!Schema::hasColumn('payment_terms', 'code')) {
                $table->string('code', 20)->nullable()->after('company_id');
            }

            if (!Schema::hasColumn('payment_terms', 'description')) {
                $table->text('description')->nullable()->after('code');
            }

            if (!Schema::hasColumn('payment_terms', 'type')) {
                $table->enum('type', ['CASH','CREDIT','ADVANCE','INSTALLMENTS','OTHER'])->default('CREDIT')->after('description');
            }

            // booleans
            if (!Schema::hasColumn('payment_terms', 'cash')) $table->boolean('cash')->default(false)->after('type');
            if (!Schema::hasColumn('payment_terms', 'credit')) $table->boolean('credit')->default(false)->after('cash');
            if (!Schema::hasColumn('payment_terms', 'advance')) $table->boolean('advance')->default(false)->after('credit');

            if (!Schema::hasColumn('payment_terms', 'late_fee_percent')) $table->decimal('late_fee_percent', 10, 4)->nullable()->after('discount_days');
            if (!Schema::hasColumn('payment_terms', 'down_payment_percent')) $table->decimal('down_payment_percent', 10, 4)->nullable()->after('late_fee_percent');

            if (!Schema::hasColumn('payment_terms', 'allow_partial_payments')) $table->boolean('allow_partial_payments')->default(false)->after('down_payment_percent');
            if (!Schema::hasColumn('payment_terms', 'installments')) $table->unsignedSmallInteger('installments')->nullable()->after('allow_partial_payments');

            if (!Schema::hasColumn('payment_terms', 'is_default')) $table->boolean('is_default')->default(false)->after('installments');
            if (!Schema::hasColumn('payment_terms', 'sort_order')) $table->unsignedSmallInteger('sort_order')->default(100)->after('is_default');
        });

        // ensure default term flags
        if (Schema::hasTable('payment_terms')) {
            DB::table('payment_terms')
                ->whereNull('cash')
                ->update(['cash' => DB::raw('CASE WHEN days = 0 THEN 1 ELSE 0 END')]);
        }

        $this->createIndexIfMissing('payment_terms', 'payment_terms_company_id_code_index', ['company_id', 'code']);
        $this->createIndexIfMissing('payment_terms', 'payment_terms_company_id_is_active_index', ['company_id', 'is_active']);
    }

    public function down(): void
    {
        $this->dropIndexIfExists('payment_terms', 'payment_terms_company_id_code_index');
        $this->dropIndexIfExists('payment_terms', 'payment_terms_company_id_is_active_index');

        Schema::table('payment_terms', function (Blueprint $table) {
            if (Schema::hasColumn('payment_terms', 'company_id')) {
                $table->dropConstrainedForeignId('company_id');
            }

            $cols = [
                'code', 'description', 'type', 'cash', 'credit', 'advance', 'late_fee_percent', 'down_payment_percent', 'allow_partial_payments', 'installments', 'is_default', 'sort_order'
            ];

            foreach ($cols as $col) {
                if (Schema::hasColumn('payment_terms', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }

    private function createIndexIfMissing(string $table, string $indexName, array $columns): void
    {
        if (! $this->indexExists($table, $indexName)) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->index($columns, $indexName));
        }
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if ($this->indexExists($table, $indexName)) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropIndex($indexName));
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        return collect(Schema::getIndexes($table))
            ->contains(fn (array $index) => ($index['name'] ?? null) === $indexName);
    }
};

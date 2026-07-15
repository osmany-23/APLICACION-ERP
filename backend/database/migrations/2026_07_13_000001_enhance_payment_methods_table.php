<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            if (!Schema::hasColumn('payment_methods', 'company_id')) {
                $table->foreignId('company_id')->nullable()->after('id')->constrained('companies')->nullOnDelete();
            }

            if (!Schema::hasColumn('payment_methods', 'type')) {
                $table->enum('type', ['CASH','BANK_TRANSFER','CARD','CHECK','DEPOSIT','DIGITAL_WALLET','CREDIT_INTERNAL','OTHER'])->default('OTHER')->after('description');
            }

            // feature flags
            if (!Schema::hasColumn('payment_methods', 'cash')) $table->boolean('cash')->default(false)->after('type');
            if (!Schema::hasColumn('payment_methods', 'card')) $table->boolean('card')->default(false)->after('cash');
            if (!Schema::hasColumn('payment_methods', 'bank')) $table->boolean('bank')->default(false)->after('card');
            if (!Schema::hasColumn('payment_methods', 'check')) $table->boolean('check')->default(false)->after('bank');
            if (!Schema::hasColumn('payment_methods', 'digital_wallet')) $table->boolean('digital_wallet')->default(false)->after('check');
            if (!Schema::hasColumn('payment_methods', 'credit')) $table->boolean('credit')->default(false)->after('digital_wallet');
            if (!Schema::hasColumn('payment_methods', 'other')) $table->boolean('other')->default(false)->after('credit');

            if (!Schema::hasColumn('payment_methods', 'requires_authorization')) $table->boolean('requires_authorization')->default(false)->after('other');
            if (!Schema::hasColumn('payment_methods', 'allow_change')) $table->boolean('allow_change')->default(false)->after('requires_authorization');
            if (!Schema::hasColumn('payment_methods', 'allow_partial_payment')) $table->boolean('allow_partial_payment')->default(false)->after('allow_change');
            if (!Schema::hasColumn('payment_methods', 'is_online')) $table->boolean('is_online')->default(false)->after('allow_partial_payment');
            if (!Schema::hasColumn('payment_methods', 'is_default')) $table->boolean('is_default')->default(false)->after('is_online');

            if (!Schema::hasColumn('payment_methods', 'sort_order')) $table->unsignedSmallInteger('sort_order')->default(100)->after('is_default');
        });

        // Populate basic flags for legacy rows
        if (Schema::hasTable('payment_methods')) {
            DB::table('payment_methods')
                ->whereNull('cash')
                ->update(['cash' => DB::raw("CASE WHEN code = 'CASH' THEN 1 ELSE 0 END")]);
        }

        $this->createIndexIfMissing('payment_methods', 'payment_methods_company_id_code_index', ['company_id', 'code']);
        $this->createIndexIfMissing('payment_methods', 'payment_methods_company_id_is_active_index', ['company_id', 'is_active']);
    }

    public function down(): void
    {
        $this->dropIndexIfExists('payment_methods', 'payment_methods_company_id_code_index');
        $this->dropIndexIfExists('payment_methods', 'payment_methods_company_id_is_active_index');

        Schema::table('payment_methods', function (Blueprint $table) {
            if (Schema::hasColumn('payment_methods', 'company_id')) {
                $table->dropConstrainedForeignId('company_id');
            }

            $cols = [
                'type', 'cash', 'card', 'bank', 'check', 'digital_wallet', 'credit', 'other',
                'requires_authorization', 'allow_change', 'allow_partial_payment', 'is_online', 'is_default', 'sort_order'
            ];

            foreach ($cols as $col) {
                if (Schema::hasColumn('payment_methods', $col)) {
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

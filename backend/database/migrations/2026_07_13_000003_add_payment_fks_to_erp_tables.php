<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $targets = [
            'sales' => ['method_after' => 'payment_term_id', 'term_after' => null],
            'purchases' => ['method_after' => 'payment_term_id', 'term_after' => null],
            'customers' => ['method_after' => 'payment_term_id', 'term_after' => null],
            'suppliers' => ['method_after' => 'payment_term_id', 'term_after' => null],
            'quotations' => ['method_after' => 'customer_id', 'term_after' => 'payment_method_id'],
            'payments' => ['method_after' => 'payment_method', 'term_after' => 'payment_method_id'],
        ];

        foreach ($targets as $tableName => $position) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName, $position) {
                    if (!Schema::hasColumn($tableName, 'payment_method_id')) {
                        $column = $table->foreignId('payment_method_id')->nullable();

                        if ($this->canUseAfter($tableName, $position['method_after'])) {
                            $column->after($position['method_after']);
                        }

                        $column->constrained('payment_methods')->nullOnDelete();
                    }

                    if (!Schema::hasColumn($tableName, 'payment_term_id')) {
                        $column = $table->foreignId('payment_term_id')->nullable();

                        if ($this->canUseAfter($tableName, $position['term_after'])) {
                            $column->after($position['term_after']);
                        }

                        $column->constrained('payment_terms')->nullOnDelete();
                    }
                });
            }
        }
    }

    public function down(): void
    {
        $methodTargets = ['sales', 'purchases', 'quotations', 'customers', 'suppliers', 'payments'];
        $termTargets = ['quotations', 'payments'];

        foreach ($methodTargets as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    if (Schema::hasColumn($tableName, 'payment_method_id')) {
                        $table->dropConstrainedForeignId('payment_method_id');
                    }
                });
            }
        }

        foreach ($termTargets as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    if (Schema::hasColumn($tableName, 'payment_term_id')) {
                        $table->dropConstrainedForeignId('payment_term_id');
                    }
                });
            }
        }
    }

    private function canUseAfter(string $tableName, ?string $columnName): bool
    {
        return $columnName !== null && Schema::hasColumn($tableName, $columnName);
    }
};

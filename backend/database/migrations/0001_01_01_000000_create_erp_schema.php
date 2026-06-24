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
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36);
            $table->string('name', 150);
            $table->string('legal_name', 200)->nullable();
            $table->string('tax_id', 50)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 120)->nullable();
            $table->text('address')->nullable();
            $table->string('logo')->nullable();
            $table->tinyInteger('status')->nullable()->default(1);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable()->useCurrentOnUpdate();
        });

        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->char('uuid', 36);
            $table->string('name', 120);
            $table->string('phone', 30)->nullable();
            $table->text('address')->nullable();
            $table->tinyInteger('status')->nullable()->default(1);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('company_id')->references('id')->on('companies');
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('company_id')->references('id')->on('companies');
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->char('uuid', 36);
            $table->string('username', 80)->unique();
            $table->string('password_hash');
            $table->string('full_name', 150)->nullable();
            $table->string('email', 120)->nullable();
            $table->string('phone', 30)->nullable();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->tinyInteger('status')->nullable()->default(1);
            $table->dateTime('last_login')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('company_id')->references('id')->on('companies');
            $table->foreign('branch_id')->references('id')->on('branches');
            $table->foreign('role_id', 'fk_users_roles')->references('id')->on('roles');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('module_name', 100)->nullable();
            $table->string('action_name', 100)->nullable();
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('permission_id');

            $table->primary(['role_id', 'permission_id']);
            $table->foreign('role_id')->references('id')->on('roles');
            $table->foreign('permission_id')->references('id')->on('permissions');
        });

        Schema::create('customer_types', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('name', 100)->nullable();
            $table->integer('credit_days')->nullable()->default(0);
            $table->decimal('discount_percent', 10, 2)->nullable()->default(0);

            $table->foreign('company_id')->references('id')->on('companies');
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('customer_type_id')->nullable();
            $table->string('code', 50)->nullable()->unique();
            $table->string('full_name', 150);
            $table->string('business_name', 150)->nullable();
            $table->string('tax_id', 50)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 120)->nullable();
            $table->text('address')->nullable();
            $table->decimal('credit_limit', 18, 4)->nullable()->default(0);
            $table->decimal('current_balance', 18, 4)->nullable()->default(0);
            $table->decimal('interest_percent', 10, 2)->nullable()->default(0);
            $table->tinyInteger('status')->nullable()->default(1);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('company_id')->references('id')->on('companies');
            $table->foreign('customer_type_id')->references('id')->on('customer_types');
            $table->foreign('created_by')->references('id')->on('users');
            $table->index('full_name', 'idx_customers_name');
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('code', 50)->nullable()->unique();
            $table->string('name', 150);
            $table->string('tax_id', 50)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 120)->nullable();
            $table->text('address')->nullable();
            $table->string('bank_name', 120)->nullable();
            $table->string('bank_account', 100)->nullable();
            $table->integer('payment_days')->nullable()->default(0);
            $table->tinyInteger('status')->nullable()->default(1);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('company_id')->references('id')->on('companies');
            $table->index('name', 'idx_suppliers_name');
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('name', 120)->nullable();
            $table->decimal('margin_percent', 10, 2)->nullable()->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('company_id')->references('id')->on('companies');
            $table->foreign('parent_id')->references('id')->on('categories');
        });

        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('name', 120)->nullable();

            $table->foreign('company_id')->references('id')->on('companies');
        });

        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('name', 50)->nullable();
            $table->string('short_name', 20)->nullable();

            $table->foreign('company_id')->references('id')->on('companies');
        });

        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('name', 120)->nullable();
            $table->text('address')->nullable();
            $table->string('manager_name', 120)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('company_id')->references('id')->on('companies');
            $table->foreign('branch_id')->references('id')->on('branches');
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('brand_id')->nullable();
            $table->unsignedBigInteger('unit_id')->nullable();
            $table->string('code', 100)->nullable()->unique();
            $table->string('barcode', 120)->nullable();
            $table->string('short_name', 120)->nullable();
            $table->text('long_name')->nullable();
            $table->string('model', 120)->nullable();
            $table->decimal('cost', 18, 4)->nullable()->default(0);
            $table->decimal('price', 18, 4)->nullable()->default(0);
            $table->decimal('minimum_stock', 18, 4)->nullable()->default(0);
            $table->tinyInteger('manages_lots')->nullable()->default(0);
            $table->tinyInteger('manages_expiration')->nullable()->default(0);
            $table->decimal('tax_percent', 10, 2)->nullable()->default(0);
            $table->string('image_url')->nullable();
            $table->tinyInteger('status')->nullable()->default(1);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('company_id')->references('id')->on('companies');
            $table->foreign('category_id')->references('id')->on('categories');
            $table->foreign('brand_id')->references('id')->on('brands');
            $table->foreign('unit_id')->references('id')->on('units');
            $table->index('code', 'idx_products_code');
            $table->index('barcode', 'idx_products_barcode');
        });

        Schema::create('inventory_stock', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('warehouse_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->decimal('quantity', 18, 4)->nullable()->default(0);
            $table->decimal('average_cost', 18, 4)->nullable()->default(0);

            $table->unique(['warehouse_id', 'product_id'], 'uq_stock');
            $table->foreign('company_id')->references('id')->on('companies');
            $table->foreign('warehouse_id')->references('id')->on('warehouses');
            $table->foreign('product_id')->references('id')->on('products');
        });

        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('warehouse_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->enum('movement_type', ['ENTRY', 'EXIT', 'TRANSFER', 'ADJUSTMENT'])->nullable();
            $table->string('reference_table', 100)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->decimal('quantity', 18, 4)->nullable();
            $table->decimal('unit_cost', 18, 4)->nullable();
            $table->decimal('total_cost', 18, 4)->nullable();
            $table->decimal('stock_before', 18, 4)->nullable();
            $table->decimal('stock_after', 18, 4)->nullable();
            $table->dateTime('movement_date')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('company_id')->references('id')->on('companies');
            $table->foreign('warehouse_id')->references('id')->on('warehouses');
            $table->foreign('product_id')->references('id')->on('products');
            $table->foreign('created_by')->references('id')->on('users');
            $table->index('product_id', 'idx_inventory_product');
        });

        Schema::create('inventory_lots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('warehouse_id')->nullable();
            $table->string('lot_number', 100)->nullable();
            $table->date('expiration_date')->nullable();
            $table->decimal('quantity', 18, 4)->nullable();
            $table->decimal('cost', 18, 4)->nullable();

            $table->foreign('product_id')->references('id')->on('products');
            $table->foreign('warehouse_id')->references('id')->on('warehouses');
        });

        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('name', 100)->nullable();
            $table->tinyInteger('requires_reference')->nullable()->default(0);

            $table->foreign('company_id')->references('id')->on('companies');
        });

        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('warehouse_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('invoice_number', 100)->nullable()->unique();
            $table->enum('sale_type', ['CASH', 'CREDIT'])->nullable();
            $table->enum('status', ['PENDING', 'PAID', 'PARTIAL', 'CANCELLED'])->nullable();
            $table->decimal('subtotal', 18, 4)->nullable();
            $table->decimal('discount', 18, 4)->nullable();
            $table->decimal('tax', 18, 4)->nullable();
            $table->decimal('total', 18, 4)->nullable();
            $table->decimal('paid_amount', 18, 4)->nullable()->default(0);
            $table->decimal('balance', 18, 4)->nullable()->default(0);
            $table->date('due_date')->nullable();
            $table->text('notes')->nullable();
            $table->text('cancelled_reason')->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('company_id')->references('id')->on('companies');
            $table->foreign('branch_id')->references('id')->on('branches');
            $table->foreign('warehouse_id')->references('id')->on('warehouses');
            $table->foreign('customer_id')->references('id')->on('customers');
            $table->foreign('user_id')->references('id')->on('users');
            $table->index('invoice_number', 'idx_sales_invoice');
        });

        Schema::create('sale_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sale_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->decimal('quantity', 18, 4)->nullable();
            $table->decimal('unit_price', 18, 4)->nullable();
            $table->decimal('unit_cost', 18, 4)->nullable();
            $table->decimal('discount', 18, 4)->nullable();
            $table->decimal('tax', 18, 4)->nullable();
            $table->decimal('subtotal', 18, 4)->nullable();
            $table->decimal('total', 18, 4)->nullable();

            $table->foreign('sale_id')->references('id')->on('sales');
            $table->foreign('product_id')->references('id')->on('products');
        });

        Schema::create('sale_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sale_id')->nullable();
            $table->unsignedBigInteger('payment_method_id')->nullable();
            $table->decimal('amount', 18, 4)->nullable();
            $table->string('reference_number', 120)->nullable();
            $table->dateTime('payment_date')->nullable();

            $table->foreign('sale_id')->references('id')->on('sales');
            $table->foreign('payment_method_id')->references('id')->on('payment_methods');
        });

        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('warehouse_id')->nullable();
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('invoice_number', 100)->nullable();
            $table->enum('purchase_type', ['CASH', 'CREDIT'])->nullable();
            $table->enum('status', ['PENDING', 'PAID', 'PARTIAL', 'CANCELLED'])->nullable();
            $table->decimal('subtotal', 18, 4)->nullable();
            $table->decimal('discount', 18, 4)->nullable();
            $table->decimal('tax', 18, 4)->nullable();
            $table->decimal('total', 18, 4)->nullable();
            $table->decimal('balance', 18, 4)->nullable();
            $table->date('due_date')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('company_id')->references('id')->on('companies');
            $table->foreign('branch_id')->references('id')->on('branches');
            $table->foreign('warehouse_id')->references('id')->on('warehouses');
            $table->foreign('supplier_id')->references('id')->on('suppliers');
            $table->foreign('user_id')->references('id')->on('users');
        });

        Schema::create('purchase_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->decimal('quantity', 18, 4)->nullable();
            $table->decimal('unit_cost', 18, 4)->nullable();
            $table->decimal('tax', 18, 4)->nullable();
            $table->decimal('total', 18, 4)->nullable();

            $table->foreign('purchase_id')->references('id')->on('purchases');
            $table->foreign('product_id')->references('id')->on('products');
        });

        Schema::create('accounts_receivable', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('sale_id')->nullable();
            $table->decimal('original_amount', 18, 4)->nullable();
            $table->decimal('paid_amount', 18, 4)->nullable();
            $table->decimal('balance', 18, 4)->nullable();
            $table->date('due_date')->nullable();
            $table->enum('status', ['PENDING', 'PARTIAL', 'PAID', 'OVERDUE'])->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('customer_id')->references('id')->on('customers');
            $table->foreign('sale_id')->references('id')->on('sales');
        });

        Schema::create('accounts_receivable_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('accounts_receivable_id')->nullable();
            $table->unsignedBigInteger('payment_method_id')->nullable();
            $table->decimal('amount', 18, 4)->nullable();
            $table->dateTime('payment_date')->nullable();
            $table->string('reference_number', 120)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();

            $table->foreign('accounts_receivable_id')->references('id')->on('accounts_receivable');
            $table->foreign('payment_method_id')->references('id')->on('payment_methods');
            $table->foreign('created_by')->references('id')->on('users');
        });

        Schema::create('accounts_payable', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->unsignedBigInteger('purchase_id')->nullable();
            $table->decimal('original_amount', 18, 4)->nullable();
            $table->decimal('paid_amount', 18, 4)->nullable();
            $table->decimal('balance', 18, 4)->nullable();
            $table->date('due_date')->nullable();
            $table->enum('status', ['PENDING', 'PARTIAL', 'PAID', 'OVERDUE'])->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('supplier_id')->references('id')->on('suppliers');
            $table->foreign('purchase_id')->references('id')->on('purchases');
        });

        Schema::create('accounts_payable_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('accounts_payable_id')->nullable();
            $table->unsignedBigInteger('payment_method_id')->nullable();
            $table->decimal('amount', 18, 4)->nullable();
            $table->dateTime('payment_date')->nullable();
            $table->string('reference_number', 120)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();

            $table->foreign('accounts_payable_id')->references('id')->on('accounts_payable');
            $table->foreign('payment_method_id')->references('id')->on('payment_methods');
            $table->foreign('created_by')->references('id')->on('users');
        });

        Schema::create('cash_registers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('name', 120)->nullable();

            $table->foreign('company_id')->references('id')->on('companies');
            $table->foreign('branch_id')->references('id')->on('branches');
        });

        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cash_register_id')->nullable();
            $table->enum('movement_type', ['IN', 'OUT'])->nullable();
            $table->string('reference_table', 100)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->decimal('amount', 18, 4)->nullable();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('cash_register_id')->references('id')->on('cash_registers');
            $table->foreign('created_by')->references('id')->on('users');
        });

        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('bank_name', 120)->nullable();
            $table->string('account_number', 120)->nullable();
            $table->string('account_type', 50)->nullable();
            $table->decimal('current_balance', 18, 4)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('company_id')->references('id')->on('companies');
        });

        Schema::create('bank_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bank_account_id')->nullable();
            $table->enum('transaction_type', ['DEPOSIT', 'WITHDRAW', 'TRANSFER', 'FEE'])->nullable();
            $table->decimal('amount', 18, 4)->nullable();
            $table->string('reference_number', 120)->nullable();
            $table->text('description')->nullable();
            $table->dateTime('transaction_date')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();

            $table->foreign('bank_account_id')->references('id')->on('bank_accounts');
            $table->foreign('created_by')->references('id')->on('users');
        });

        Schema::create('accounting_account_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->nullable();
        });

        Schema::create('accounting_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('account_type_id')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('account_code', 50)->nullable()->unique();
            $table->string('account_name', 150)->nullable();
            $table->tinyInteger('allows_movements')->nullable()->default(1);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('company_id')->references('id')->on('companies');
            $table->foreign('account_type_id')->references('id')->on('accounting_account_types');
            $table->foreign('parent_id')->references('id')->on('accounting_accounts');
        });

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('entry_number', 100)->nullable()->unique();
            $table->date('entry_date')->nullable();
            $table->string('reference_table', 100)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('description')->nullable();
            $table->enum('status', ['DRAFT', 'POSTED', 'CANCELLED'])->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('company_id')->references('id')->on('companies');
            $table->foreign('branch_id')->references('id')->on('branches');
            $table->foreign('created_by')->references('id')->on('users');
            $table->index('entry_date', 'idx_journal_entry_date');
        });

        Schema::create('journal_entry_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->unsignedBigInteger('account_id')->nullable();
            $table->decimal('debit', 18, 4)->nullable()->default(0);
            $table->decimal('credit', 18, 4)->nullable()->default(0);
            $table->text('description')->nullable();

            $table->foreign('journal_entry_id')->references('id')->on('journal_entries');
            $table->foreign('account_id')->references('id')->on('accounting_accounts');
        });

        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('name', 120)->nullable();

            $table->foreign('company_id')->references('id')->on('companies');
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('expense_category_id')->nullable();
            $table->unsignedBigInteger('payment_method_id')->nullable();
            $table->decimal('amount', 18, 4)->nullable();
            $table->text('description')->nullable();
            $table->string('document_number', 120)->nullable();
            $table->date('expense_date')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('company_id')->references('id')->on('companies');
            $table->foreign('branch_id')->references('id')->on('branches');
            $table->foreign('expense_category_id')->references('id')->on('expense_categories');
            $table->foreign('payment_method_id')->references('id')->on('payment_methods');
            $table->foreign('created_by')->references('id')->on('users');
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('table_name', 120)->nullable();
            $table->enum('action_type', ['INSERT', 'UPDATE', 'DELETE', 'LOGIN'])->nullable();
            $table->unsignedBigInteger('record_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 80)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('user_id')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        foreach ([
            'audit_logs',
            'expenses',
            'expense_categories',
            'journal_entry_details',
            'journal_entries',
            'accounting_accounts',
            'accounting_account_types',
            'bank_transactions',
            'bank_accounts',
            'cash_movements',
            'cash_registers',
            'accounts_payable_payments',
            'accounts_payable',
            'accounts_receivable_payments',
            'accounts_receivable',
            'purchase_details',
            'purchases',
            'sale_payments',
            'sale_details',
            'sales',
            'payment_methods',
            'inventory_lots',
            'inventory_movements',
            'inventory_stock',
            'products',
            'warehouses',
            'units',
            'brands',
            'categories',
            'suppliers',
            'customers',
            'customer_types',
            'role_permissions',
            'permissions',
            'sessions',
            'password_reset_tokens',
            'users',
            'roles',
            'branches',
            'companies',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::enableForeignKeyConstraints();
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ============================================================
        // 1. Tablas independientes (sin dependencias)
        // ============================================================

        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->char('code', 3)->unique()->comment('ISO 4217');
            $table->string('name', 60);
            $table->string('symbol', 10);
            $table->unsignedTinyInteger('decimal_places')->default(2);
            $table->boolean('is_base')->default(false)->comment('Moneda base del sistema');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('taxes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 100);
            $table->decimal('rate', 8, 4);
            $table->enum('type', ['VAT', 'EXCISE', 'RETAINED', 'OTHER'])->default('VAT');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('payment_terms', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->integer('days')->default(0)->comment('Días de crédito');
            $table->decimal('discount_percent', 10, 2)->default(0.00)->comment('Descuento por pronto pago');
            $table->integer('discount_days')->nullable()->comment('Días para aplicar descuento');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('document_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 80);
            $table->string('prefix', 10)->nullable();
            $table->unsignedBigInteger('next_number')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('module_name', 100);
            $table->string('action_name', 100);
            $table->unique(['module_name', 'action_name']);
            $table->timestamps();
        });

        // ============================================================
        // 2. Empresa y estructuras organizacionales
        // ============================================================

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 150);
            $table->string('legal_name', 200)->nullable();
            $table->string('tax_id', 50)->nullable();
            $table->string('tax_regime', 50)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 120)->nullable();
            $table->string('website', 200)->nullable();
            $table->text('fiscal_address')->nullable();
            $table->text('commercial_address')->nullable();
            $table->string('logo', 255)->nullable();
            $table->foreignId('currency_id')->default(1)->constrained('currencies');
            $table->string('timezone', 80)->default('America/Managua');
            $table->string('country', 80)->default('Nicaragua');
            $table->tinyInteger('status')->default(1);
            $table->timestamps();
        });

        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('code', 20);
            $table->string('name', 120);
            $table->string('phone', 30)->nullable();
            $table->string('email', 120)->nullable();
            $table->text('address')->nullable();
            $table->string('contact_person', 120)->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->boolean('is_headquarters')->default(false);
            $table->tinyInteger('status')->default(1);
            $table->unique(['company_id', 'code']);
            $table->timestamps();
        });

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('manager', 120)->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('username', 80);
            $table->string('password_hash', 255);
            $table->string('full_name', 150);
            $table->string('email', 120)->nullable();
            $table->string('phone', 30)->nullable();
            $table->foreignId('role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->tinyInteger('status')->default(1);
            $table->dateTime('last_login')->nullable();
            $table->unique(['company_id', 'username']);
            $table->timestamps();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete()->comment('Relación con usuarios del sistema');
            $table->string('first_name', 80);
            $table->string('last_name', 80);
            $table->string('phone', 30)->nullable();
            $table->string('email', 120)->nullable();
            $table->decimal('salary', 18, 4)->nullable();
            $table->date('hire_date')->nullable();
            $table->enum('status', ['ACTIVE', 'INACTIVE', 'TERMINATED'])->default('ACTIVE');
            $table->timestamps();
        });

        Schema::create('cost_centers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name', 120);
            $table->foreignId('parent_id')->nullable()->constrained('cost_centers')->nullOnDelete();
            $table->boolean('status')->default(true);
            $table->unique(['company_id', 'code']);
            $table->timestamps();
        });

        // ============================================================
        // 3. Maestros de productos
        // ============================================================

        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained('companies')->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name', 50);
            $table->string('short_name', 20);
            $table->enum('category', ['UNIDAD', 'PESO', 'VOLUMEN', 'LONGITUD', 'OTRO'])->default('UNIDAD');
            $table->unsignedTinyInteger('decimal_places')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unique(['company_id', 'code']);
            $table->timestamps();
        });

        // MODIFICADO: Añadido image_url
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained('companies')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('code', 20);
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('level')->default(1);
            $table->decimal('margin_percent', 10, 2)->default(0.00);
            $table->string('image_url', 255)->nullable()->comment('Imagen representativa de la categoría');
            $table->boolean('is_active')->default(true);
            $table->unique(['company_id', 'code']);
            $table->timestamps();
        });

        // MODIFICADO: Añadido image_url
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained('companies')->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->string('image_url', 255)->nullable()->comment('Logo o imagen de la marca');
            $table->boolean('is_active')->default(true);
            $table->unique(['company_id', 'code']);
            $table->timestamps();
        });

        Schema::create('price_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unique(['company_id', 'code']);
            $table->timestamps();
        });

        // ============================================================
        // 4. Proveedores y clientes
        // ============================================================

        // MODIFICADO: Añadido image_url
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('code', 50)->nullable();
            $table->string('name', 150);
            $table->string('tax_id', 50)->nullable();
            $table->enum('tax_id_type', ['RUC', 'CÉDULA', 'PASAPORTE', 'OTRO'])->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 120)->nullable();
            $table->string('website', 200)->nullable();
            $table->text('address')->nullable();
            $table->string('image_url', 255)->nullable()->comment('Logo o imagen del proveedor');
            $table->string('contact_person', 120)->nullable();
            $table->string('contact_phone', 30)->nullable();
            $table->string('contact_email', 120)->nullable();
            $table->foreignId('payment_term_id')->nullable()->constrained('payment_terms')->nullOnDelete();
            $table->foreignId('currency_id')->default(1)->constrained('currencies');
            $table->string('bank_name', 120)->nullable();
            $table->string('bank_account', 100)->nullable();
            $table->string('swift', 20)->nullable();
            $table->string('iban', 60)->nullable();
            $table->decimal('credit_limit', 18, 4)->nullable();
            $table->integer('credit_days')->nullable();
            $table->tinyInteger('rating')->nullable()->comment('1-5');
            $table->text('notes')->nullable();
            $table->tinyInteger('status')->default(1);
            $table->unique(['company_id', 'code']);
            $table->timestamps();
        });

        Schema::create('customer_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained('companies')->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name', 100);
            $table->integer('credit_days')->default(0);
            $table->decimal('discount_percent', 10, 2)->default(0.00);
            $table->boolean('is_active')->default(true);
            $table->unique(['company_id', 'code']);
            $table->timestamps();
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('customer_type_id')->nullable()->constrained('customer_types')->nullOnDelete();
            $table->string('code', 50)->nullable();
            $table->string('full_name', 150);
            $table->string('business_name', 150)->nullable();
            $table->string('tax_id', 50)->nullable();
            $table->enum('tax_id_type', ['RUC', 'CÉDULA', 'PASAPORTE', 'OTRO'])->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 120)->nullable();
            $table->text('address')->nullable();
            $table->string('contact_person', 120)->nullable();
            $table->string('contact_phone', 30)->nullable();
            $table->string('contact_email', 120)->nullable();
            $table->foreignId('payment_term_id')->nullable()->constrained('payment_terms')->nullOnDelete();
            $table->foreignId('currency_id')->default(1)->constrained('currencies');
            $table->foreignId('price_list_id')->nullable()->constrained('price_lists')->nullOnDelete();
            $table->decimal('credit_limit', 18, 4)->default(0);
            $table->integer('credit_days')->nullable();
            $table->decimal('current_balance', 18, 4)->default(0);
            $table->decimal('discount_rate', 10, 2)->default(0.00);
            $table->date('birthday')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->foreignId('salesperson_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->tinyInteger('rating')->nullable();
            $table->text('notes')->nullable();
            $table->tinyInteger('status')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unique(['company_id', 'code']);
            $table->timestamps();
        });

        // ============================================================
        // 5. Productos
        // ============================================================

        // MODIFICADO: Añadido campo measurement (medida)
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('code', 100)->nullable();
            $table->string('barcode', 120)->nullable();
            $table->string('barcode_type', 20)->nullable();
            $table->string('short_name', 120);
            $table->text('long_name')->nullable();
            $table->text('description')->nullable();
            $table->string('model', 120)->nullable();
            $table->string('measurement', 50)->nullable()->comment('Medidas físicas. Ej: 33X24X66, 1/4 x 1');
            $table->enum('type', ['PRODUCTO', 'SERVICIO', 'KIT'])->default('PRODUCTO');
            $table->boolean('track_serial')->default(false);
            $table->boolean('track_lot')->default(false);
            $table->decimal('weight', 18, 4)->nullable();
            $table->decimal('volume', 18, 4)->nullable();
            $table->string('dimensions', 80)->nullable();
            $table->string('physical_location', 255)->nullable();
            $table->text('notes')->nullable();
            $table->string('image_url', 255)->nullable();
            $table->tinyInteger('status')->default(1);
            $table->boolean('is_inventory')->default(true);
            $table->boolean('is_service')->default(false);
            $table->boolean('is_kit')->default(false);
            $table->boolean('allow_sale')->default(true);
            $table->boolean('allow_purchase')->default(true);
            $table->boolean('is_favorite')->default(false);
            $table->decimal('minimum_stock', 18, 4)->nullable();
            $table->decimal('maximum_stock', 18, 4)->nullable();
            $table->decimal('reorder_point', 18, 4)->nullable();
            $table->string('country_origin', 80)->nullable();
            $table->integer('warranty_days')->nullable();
            $table->enum('cost_method', ['AVERAGE', 'FIFO', 'LIFO', 'STANDARD'])->default('AVERAGE');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unique(['company_id', 'code']);
            $table->unique(['company_id', 'barcode']);
            $table->timestamps();
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('sku', 100)->comment('Código propio de la variante');
            $table->json('attributes')->nullable()->comment('Ej: {"talla":"M","color":"Rojo"}');
            $table->decimal('cost', 18, 4)->default(0);
            $table->decimal('sale_price', 18, 4)->default(0);
            $table->foreignId('price_list_id')->nullable()->constrained('price_lists')->nullOnDelete();
            $table->decimal('stock', 18, 4)->default(0);
            $table->string('image_url', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unique(['product_id', 'sku']);
            $table->timestamps();
        });

        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();
            $table->string('image_url', 255);
            $table->boolean('is_primary')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('product_taxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('tax_id')->constrained('taxes')->cascadeOnDelete();
            $table->boolean('is_default')->default(false);
            $table->unique(['product_id', 'tax_id']);
            $table->timestamps();
        });

        // CORREGIDO: Indexación con nombre corto personalizado
        Schema::create('product_price_list_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();
            $table->foreignId('price_list_id')->constrained('price_lists')->cascadeOnDelete();
            $table->decimal('price', 18, 4);
            $table->boolean('is_active')->default(true);
            $table->unique(['product_id', 'variant_id', 'price_list_id'], 'prod_variant_price_list_unique');
            $table->timestamps();
        });

        // CORREGIDO: Indexación con nombre corto personalizado
        Schema::create('product_unit_conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('unit_from_id')->constrained('units')->cascadeOnDelete();
            $table->foreignId('unit_to_id')->constrained('units')->cascadeOnDelete();
            $table->decimal('factor', 18, 6);
            $table->unique(['product_id', 'unit_from_id', 'unit_to_id'], 'prod_unit_conversion_unique');
            $table->timestamps();
        });

        Schema::create('product_price_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();
            $table->decimal('old_price', 18, 4);
            $table->decimal('new_price', 18, 4);
            $table->foreignId('price_list_id')->nullable()->constrained('price_lists')->nullOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at')->useCurrent();
            $table->timestamps();
        });

        Schema::create('product_cost_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();
            $table->decimal('old_cost', 18, 4);
            $table->decimal('new_cost', 18, 4);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at')->useCurrent();
            $table->timestamps();
        });

        // ============================================================
        // 8. Bodegas (Adelantada por dependencia estructural en Ventas)
        // ============================================================

        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('code', 20);
            $table->string('name', 120);
            $table->enum('type', ['PRINCIPAL', 'SECUNDARIO', 'VIRTUAL'])->default('PRINCIPAL');
            $table->text('address')->nullable();
            $table->string('manager_name', 120)->nullable();
            $table->decimal('capacity', 18, 4)->nullable()->comment('Capacidad en unidades');
            $table->decimal('used_capacity', 18, 4)->default(0);
            $table->tinyInteger('status')->default(1);
            $table->unique(['company_id', 'code']);
            $table->timestamps();
        });

        // ============================================================
        // 6. Transacciones principales (ventas, compras, cuentas)
        // ============================================================

        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete()->comment('Bodega de salida');
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('payment_term_id')->nullable()->constrained('payment_terms')->nullOnDelete()->comment('Condición de pago');
            $table->string('sale_number', 100)->unique();
            $table->date('sale_date');
            $table->enum('status', ['DRAFT', 'PENDING', 'COMPLETED', 'CANCELLED'])->default('DRAFT');
            $table->decimal('subtotal', 18, 4)->default(0);
            $table->decimal('discount', 18, 4)->default(0);
            $table->decimal('tax', 18, 4)->default(0);
            $table->decimal('total', 18, 4)->default(0);
            $table->foreignId('currency_id')->default(1)->constrained('currencies');
            $table->decimal('exchange_rate', 18, 8)->default(1);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_price', 18, 4);
            $table->decimal('discount', 18, 4)->default(0);
            $table->decimal('tax', 18, 4)->default(0);
            $table->decimal('subtotal', 18, 4);
            $table->decimal('total', 18, 4);
            $table->timestamps();
        });

        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete()->comment('Bodega de ingreso');
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->foreignId('payment_term_id')->nullable()->constrained('payment_terms')->nullOnDelete()->comment('Condición de pago');
            $table->string('purchase_number', 100)->unique();
            $table->date('purchase_date');
            $table->enum('status', ['DRAFT', 'PENDING', 'RECEIVED', 'CANCELLED'])->default('DRAFT');
            $table->decimal('subtotal', 18, 4)->default(0);
            $table->decimal('discount', 18, 4)->default(0);
            $table->decimal('tax', 18, 4)->default(0);
            $table->decimal('total', 18, 4)->default(0);
            $table->foreignId('currency_id')->default(1)->constrained('currencies');
            $table->decimal('exchange_rate', 18, 8)->default(1);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained('purchases')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_cost', 18, 4);
            $table->decimal('discount', 18, 4)->default(0);
            $table->decimal('tax', 18, 4)->default(0);
            $table->decimal('subtotal', 18, 4);
            $table->decimal('total', 18, 4);
            $table->timestamps();
        });

        Schema::create('accounting_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('accounting_accounts')->nullOnDelete()->comment('Cuenta madre estructural');
            $table->string('code', 20);
            $table->string('name', 120);
            $table->enum('type', ['ASSET', 'LIABILITY', 'EQUITY', 'REVENUE', 'EXPENSE'])->default('ASSET');
            $table->boolean('is_active')->default(true);
            $table->unique(['company_id', 'code']);
            $table->timestamps();
        });

        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name', 120);
            $table->string('bank_name', 120)->nullable();
            $table->string('account_number', 50);
            $table->foreignId('currency_id')->default(1)->constrained('currencies');
            $table->decimal('current_balance', 18, 4)->default(0);
            $table->boolean('is_active')->default(true);
            $table->unique(['company_id', 'code']);
            $table->timestamps();
        });

        Schema::create('bank_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_account_id')->constrained('bank_accounts')->cascadeOnDelete();
            $table->date('transaction_date');
            $table->enum('type', ['DEPOSIT', 'WITHDRAWAL', 'TRANSFER', 'FEE', 'INTEREST']);
            $table->decimal('amount', 18, 4);
            $table->string('reference', 120)->nullable();
            $table->text('description')->nullable();
            $table->decimal('balance_after', 18, 4)->nullable();
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->morphs('payable'); 
            $table->date('payment_date');
            $table->decimal('amount', 18, 4);
            $table->enum('payment_method', ['CASH', 'BANK_TRANSFER', 'CHECK', 'CREDIT_CARD', 'OTHER'])->default('CASH');
            $table->string('reference', 120)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // ============================================================
        // 7. Retornos de ventas y compras
        // ============================================================

        Schema::create('sales_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained('sales')->nullOnDelete();
            $table->string('return_number', 100);
            $table->date('return_date');
            $table->enum('status', ['DRAFT', 'APPROVED', 'PROCESSED', 'CANCELLED'])->default('DRAFT');
            $table->decimal('subtotal', 18, 4)->default(0);
            $table->decimal('tax', 18, 4)->default(0);
            $table->decimal('total', 18, 4)->default(0);
            $table->text('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unique(['company_id', 'return_number']);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('sales_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_return_id')->constrained('sales_returns')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_price', 18, 4);
            $table->decimal('discount', 18, 4)->default(0);
            $table->decimal('tax', 18, 4)->default(0);
            $table->decimal('subtotal', 18, 4);
            $table->decimal('total', 18, 4);
            $table->timestamps();
        });

        Schema::create('purchase_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->foreignId('purchase_id')->nullable()->constrained('purchases')->nullOnDelete();
            $table->string('return_number', 100);
            $table->date('return_date');
            $table->enum('status', ['DRAFT', 'APPROVED', 'PROCESSED', 'CANCELLED'])->default('DRAFT');
            $table->decimal('subtotal', 18, 4)->default(0);
            $table->decimal('tax', 18, 4)->default(0);
            $table->decimal('total', 18, 4)->default(0);
            $table->text('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unique(['company_id', 'return_number']);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('purchase_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_return_id')->constrained('purchase_returns')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_cost', 18, 4);
            $table->decimal('discount', 18, 4)->default(0);
            $table->decimal('tax', 18, 4)->default(0);
            $table->decimal('subtotal', 18, 4);
            $table->decimal('total', 18, 4);
            $table->timestamps();
        });

        // ============================================================
        // 8. Inventario operativo
        // ============================================================

        // CORREGIDO: Indexación con nombre corto personalizado
        Schema::create('inventory_stock', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();
            $table->decimal('quantity', 18, 4)->default(0);
            $table->decimal('reserved_quantity', 18, 4)->default(0);
            $table->decimal('available_quantity', 18, 4)->storedAs('quantity - reserved_quantity');
            $table->decimal('damaged_quantity', 18, 4)->default(0);
            $table->decimal('in_transit_quantity', 18, 4)->default(0);
            $table->decimal('average_cost', 18, 4)->default(0);
            $table->dateTime('last_movement_date')->nullable();
            $table->unique(['warehouse_id', 'product_id', 'variant_id'], 'wh_prod_variant_stock_unique');
            $table->timestamps();
        });

        Schema::create('inventory_lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();
            $table->string('lot_number', 100);
            $table->date('expiration_date')->nullable();
            $table->date('manufacturing_date')->nullable();
            $table->decimal('initial_quantity', 18, 4);
            $table->decimal('remaining_quantity', 18, 4);
            $table->decimal('cost', 18, 4);
            $table->decimal('purchase_price', 18, 4)->nullable();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->enum('status', ['ACTIVE', 'DEPLETED', 'EXPIRED', 'BLOCKED'])->default('ACTIVE');
            $table->timestamps();
        });

        Schema::create('inventory_serials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();
            $table->string('serial_number', 100);
            $table->foreignId('lot_id')->nullable()->constrained('inventory_lots')->nullOnDelete();
            $table->enum('status', ['IN_STOCK', 'RESERVED', 'SOLD', 'RETURNED', 'DAMAGED'])->default('IN_STOCK');
            $table->decimal('purchase_price', 18, 4)->nullable();
            $table->decimal('sale_price', 18, 4)->nullable();
            $table->unique(['company_id', 'serial_number']);
            $table->timestamps();
        });

        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();
            $table->enum('movement_type', [
                'INITIAL_INVENTORY', 'PURCHASE_ENTRY', 'SALE_EXIT',
                'TRANSFER_IN', 'TRANSFER_OUT', 'ADJUSTMENT', 'RETURN_IN', 'RETURN_OUT'
            ]);
            $table->string('reference_table', 100)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_cost', 18, 4)->nullable();
            $table->decimal('total_cost', 18, 4)->nullable();
            $table->decimal('stock_before', 18, 4);
            $table->decimal('stock_after', 18, 4);
            $table->foreignId('lot_id')->nullable()->constrained('inventory_lots')->nullOnDelete();
            $table->string('serial_number', 100)->nullable();
            $table->dateTime('movement_date');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->index(['reference_table', 'reference_id']);
            $table->timestamps();
        });

        // ============================================================
        // 9. Promociones y descuentos
        // ============================================================

        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('discount_type', ['PERCENT', 'FIXED']);
            $table->decimal('discount_value', 18, 4);
            $table->enum('status', ['DRAFT', 'ACTIVE', 'EXPIRED', 'CANCELLED'])->default('DRAFT');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unique(['company_id', 'code']);
            $table->timestamps();
        });

        Schema::create('promotion_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained('promotions')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();
            $table->decimal('quantity_required', 18, 4)->nullable();
            $table->decimal('discount_override', 18, 4)->nullable();
            $table->timestamps();
        });

        Schema::create('promotion_customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained('promotions')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->unique(['promotion_id', 'customer_id']);
            $table->timestamps();
        });

        Schema::create('discount_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->integer('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->enum('apply_to', ['ALL', 'CUSTOMER', 'PRODUCT', 'CATEGORY']);
            $table->decimal('min_quantity', 18, 4)->nullable();
            $table->decimal('min_amount', 18, 4)->nullable();
            $table->enum('discount_type', ['PERCENT', 'FIXED']);
            $table->decimal('discount_value', 18, 4);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // ============================================================
        // 10. Puntos y recompensas
        // ============================================================

        Schema::create('customer_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->integer('points')->default(0);
            $table->date('expiration_date')->nullable();
            $table->enum('source', ['PURCHASE', 'PROMOTION', 'ADJUSTMENT']);
            $table->string('reference_table', 100)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->timestamps();
        });

        Schema::create('customer_rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->enum('reward_type', ['DISCOUNT', 'GIFT', 'FREE_SHIPPING']);
            $table->decimal('value', 18, 4)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_redeemed')->default(false);
            $table->dateTime('redeemed_at')->nullable();
            $table->date('expiration_date')->nullable();
            $table->timestamps();
        });

        // ============================================================
        // 11. Conteos, transferencias y ajustes de inventario
        // ============================================================

        Schema::create('stock_counts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->string('count_number', 100);
            $table->date('count_date');
            $table->enum('status', ['DRAFT', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED'])->default('DRAFT');
            $table->integer('expected_count')->nullable();
            $table->integer('actual_count')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unique(['company_id', 'count_number']);
            $table->timestamps();
        });

        Schema::create('stock_count_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_count_id')->constrained('stock_counts')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();
            $table->decimal('system_quantity', 18, 4);
            $table->decimal('physical_quantity', 18, 4);
            $table->decimal('difference', 18, 4)->storedAs('physical_quantity - system_quantity');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('warehouse_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('from_warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('to_warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->string('transfer_number', 100);
            $table->date('transfer_date');
            $table->enum('status', ['DRAFT', 'PENDING', 'IN_TRANSIT', 'RECEIVED', 'CANCELLED'])->default('DRAFT');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('received_at')->nullable();
            $table->unique(['company_id', 'transfer_number']);
            $table->timestamps();
        });

        Schema::create('warehouse_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_transfer_id')->constrained('warehouse_transfers')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->decimal('cost', 18, 4)->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->string('adjustment_number', 100);
            $table->date('adjustment_date');
            $table->enum('type', ['INCREASE', 'DECREASE']);
            $table->text('reason')->nullable();
            $table->enum('status', ['DRAFT', 'POSTED', 'CANCELLED'])->default('DRAFT');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unique(['company_id', 'adjustment_number']);
            $table->timestamps();
        });

        Schema::create('inventory_adjustment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_adjustment_id')->constrained('inventory_adjustments')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->decimal('cost', 18, 4)->nullable();
            $table->timestamps();
        });

        // ============================================================
        // 12. Manufactura
        // ============================================================

        Schema::create('manufacturing_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('order_number', 100);
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->date('planned_start_date')->nullable();
            $table->date('planned_end_date')->nullable();
            $table->dateTime('actual_start_date')->nullable();
            $table->dateTime('actual_end_date')->nullable();
            $table->enum('status', ['DRAFT', 'PLANNED', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED'])->default('DRAFT');
            $table->tinyInteger('priority')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unique(['company_id', 'order_number']);
            $table->timestamps();
        });

        Schema::create('bill_of_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name', 120);
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete()->comment('Producto final');
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();
            $table->string('version', 20)->default('1.0');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unique(['company_id', 'code']);
            $table->timestamps();
        });

        Schema::create('bom_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bill_of_materials_id')->constrained('bill_of_materials')->cascadeOnDelete();
            $table->foreignId('component_id')->constrained('products')->cascadeOnDelete()->comment('Producto componente');
            $table->foreignId('component_variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->decimal('scrap_percent', 10, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('production_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manufacturing_order_id')->constrained('manufacturing_orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->enum('entry_type', ['PRODUCED', 'SCRAP', 'RETURN']);
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('lot_id')->nullable()->constrained('inventory_lots')->nullOnDelete();
            $table->timestamps();
        });

        // ============================================================
        // 13. CRM (Leads, Oportunidades, Actividades)
        // ============================================================

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('name', 150);
            $table->string('phone', 30)->nullable();
            $table->string('email', 120)->nullable();
            $table->string('company', 120)->nullable();
            $table->enum('status', ['NEW', 'CONTACTED', 'QUALIFIED', 'LOST', 'CONVERTED'])->default('NEW');
            $table->string('source', 80)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('employees')->nullOnDelete();
            $table->boolean('converted_to_customer')->default(false);
            $table->timestamps();
        });

        Schema::create('opportunities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->enum('stage', ['PROSPECTING', 'QUALIFICATION', 'PROPOSAL', 'NEGOTIATION', 'CLOSED_WON', 'CLOSED_LOST'])->default('PROSPECTING');
            $table->decimal('amount', 18, 4)->nullable();
            $table->tinyInteger('probability')->nullable();
            $table->date('expected_close_date')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->foreignId('opportunity_id')->nullable()->constrained('opportunities')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->enum('type', ['CALL', 'MEETING', 'EMAIL', 'TASK', 'NOTE']);
            $table->string('subject', 255);
            $table->text('description')->nullable();
            $table->dateTime('scheduled_date')->nullable();
            $table->dateTime('completed_date')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // ============================================================
        // 14. Cotizaciones, solicitudes de compra y materiales
        // ============================================================

        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('quotation_number', 100);
            $table->date('quotation_date');
            $table->date('valid_until')->nullable();
            $table->enum('status', ['DRAFT', 'SENT', 'ACCEPTED', 'REJECTED', 'EXPIRED'])->default('DRAFT');
            $table->decimal('subtotal', 18, 4)->default(0);
            $table->decimal('discount', 18, 4)->default(0);
            $table->decimal('tax', 18, 4)->default(0);
            $table->decimal('total', 18, 4)->default(0);
            $table->foreignId('currency_id')->default(1)->constrained('currencies');
            $table->decimal('exchange_rate', 18, 8)->default(1);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unique(['company_id', 'quotation_number']);
            $table->timestamps();
        });

        Schema::create('quotation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained('quotations')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_price', 18, 4);
            $table->decimal('discount', 18, 4)->default(0);
            $table->decimal('tax', 18, 4)->default(0);
            $table->decimal('subtotal', 18, 4);
            $table->decimal('total', 18, 4);
            $table->timestamps();
        });

        Schema::create('purchase_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('request_number', 100);
            $table->date('request_date');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->enum('status', ['DRAFT', 'PENDING', 'APPROVED', 'REJECTED', 'ORDERED'])->default('DRAFT');
            $table->text('notes')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->unique(['company_id', 'request_number']);
            $table->timestamps();
        });

        Schema::create('purchase_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_request_id')->constrained('purchase_requests')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->decimal('estimated_cost', 18, 4)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('material_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('from_warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('to_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('request_number', 100);
            $table->date('request_date');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['DRAFT', 'PENDING', 'APPROVED', 'REJECTED', 'FULFILLED'])->default('DRAFT');
            $table->text('notes')->nullable();
            $table->unique(['company_id', 'request_number']);
            $table->timestamps();
        });

        Schema::create('material_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_request_id')->constrained('material_requests')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // ============================================================
        // 15. Activos fijos
        // ============================================================

        Schema::create('asset_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name', 120);
            $table->decimal('depreciation_rate', 10, 4)->nullable();
            $table->integer('useful_life_years')->nullable();
            $table->timestamps();
        });

        Schema::create('fixed_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('asset_category_id')->nullable()->constrained('asset_categories')->nullOnDelete();
            $table->string('code', 50);
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_cost', 18, 4);
            $table->decimal('residual_value', 18, 4)->nullable();
            $table->integer('useful_life_years')->nullable();
            $table->enum('depreciation_method', ['LINEAL', 'DECRECIENTE', 'UNIDADES_PRODUCCION'])->default('LINEAL');
            $table->decimal('current_value', 18, 4)->nullable();
            $table->decimal('accumulated_depreciation', 18, 4)->default(0);
            $table->string('location', 255)->nullable();
            $table->enum('status', ['ACTIVE', 'INACTIVE', 'DISPOSED'])->default('ACTIVE');
            $table->date('disposal_date')->nullable();
            $table->decimal('disposal_value', 18, 4)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unique(['company_id', 'code']);
            $table->timestamps();
        });

        Schema::create('asset_depreciation', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fixed_asset_id')->constrained('fixed_assets')->cascadeOnDelete();
            $table->date('period')->comment('Fecha de cierre del período');
            $table->decimal('depreciation_amount', 18, 4);
            $table->decimal('accumulated', 18, 4);
            $table->decimal('net_book_value', 18, 4);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // ============================================================
        // 16. Presupuestos
        // ============================================================

        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name', 120);
            $table->enum('type', ['OPERATIVO', 'INVERSION', 'FLUJO_CAJA']);
            $table->integer('year');
            $table->enum('status', ['DRAFT', 'ACTIVE', 'CLOSED'])->default('DRAFT');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unique(['company_id', 'code']);
            $table->timestamps();
        });

        Schema::create('budget_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained('budgets')->cascadeOnDelete();
            $table->foreignId('account_id')->nullable()->constrained('accounting_accounts')->nullOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained('cost_centers')->nullOnDelete();
            $table->tinyInteger('month');
            $table->decimal('amount', 18, 4);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // ============================================================
        // 17. Bancos y conciliaciones
        // ============================================================

        Schema::create('bank_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('bank_account_id')->constrained('bank_accounts')->cascadeOnDelete();
            $table->date('reconciliation_date');
            $table->decimal('statement_balance', 18, 4);
            $table->decimal('system_balance', 18, 4);
            $table->decimal('difference', 18, 4);
            $table->enum('status', ['DRAFT', 'RECONCILED', 'CLOSED'])->default('DRAFT');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('bank_reconciliation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_reconciliation_id')->constrained('bank_reconciliations')->cascadeOnDelete();
            $table->foreignId('bank_transaction_id')->nullable()->constrained('bank_transactions')->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->enum('type', ['BANK', 'SYSTEM']);
            $table->decimal('amount', 18, 4);
            $table->date('transaction_date');
            $table->string('reference', 120)->nullable();
            $table->boolean('is_matched')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // ============================================================
        // 18. Años y períodos fiscales
        // ============================================================

        Schema::create('fiscal_years', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name', 60);
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['OPEN', 'CLOSED', 'ARCHIVED'])->default('OPEN');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('fiscal_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fiscal_year_id')->constrained('fiscal_years')->cascadeOnDelete();
            $table->tinyInteger('period_number');
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['OPEN', 'CLOSED', 'ARCHIVED'])->default('OPEN');
            $table->unique(['fiscal_year_id', 'period_number']);
            $table->timestamps();
        });

        // ============================================================
        // 19. Grupos de impuestos
        // ============================================================

        Schema::create('tax_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained('companies')->cascadeOnDelete();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('tax_group_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tax_group_id')->constrained('tax_groups')->cascadeOnDelete();
            $table->foreignId('tax_id')->constrained('taxes')->cascadeOnDelete();
            $table->unique(['tax_group_id', 'tax_id']);
            $table->timestamps();
        });

        // ============================================================
        // 20. Seguridad, auditoría y configuraciones
        // ============================================================

        Schema::create('login_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('ip_address', 80)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('browser', 80)->nullable();
            $table->string('device', 80)->nullable();
            $table->string('country', 80)->nullable();
            $table->boolean('login_success')->default(true);
            $table->timestamps();
        });

        Schema::create('password_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('password_hash', 255);
            $table->timestamps();
        });

        Schema::create('two_factor_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('code', 10);
            $table->dateTime('expires_at');
            $table->dateTime('used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('number_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('code', 20);
            $table->text('description')->nullable();
            $table->string('prefix', 20)->nullable();
            $table->string('suffix', 20)->nullable();
            $table->bigInteger('current_value')->default(1);
            $table->tinyInteger('pad_length')->nullable();
            $table->enum('reset_on', ['DAILY', 'MONTHLY', 'YEARLY', 'NEVER'])->default('NEVER');
            $table->date('last_reset')->nullable();
            $table->unique(['company_id', 'code']);
            $table->timestamps();
        });

        Schema::create('report_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('report_type', 80);
            $table->json('parameters')->nullable();
            $table->string('schedule', 80)->comment('Cron expression o interval');
            $table->text('recipients')->nullable();
            $table->enum('format', ['PDF', 'EXCEL', 'CSV'])->default('PDF');
            $table->boolean('is_active')->default(true);
            $table->dateTime('last_run')->nullable();
            $table->text('last_result')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // ============================================================
        // 21. Aprobaciones
        // ============================================================

        Schema::create('approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('reference_table', 100);
            $table->unsignedBigInteger('reference_id');
            $table->enum('status', ['PENDING', 'APPROVED', 'REJECTED', 'CANCELLED'])->default('PENDING');
            $table->integer('current_step')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->index(['reference_table', 'reference_id']);
            $table->timestamps();
        });

        Schema::create('approval_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_id')->constrained('approvals')->cascadeOnDelete();
            $table->integer('step_order');
            $table->foreignId('role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['PENDING', 'APPROVED', 'REJECTED'])->default('PENDING');
            $table->text('comment')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('approval_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_id')->constrained('approvals')->cascadeOnDelete();
            $table->foreignId('step_id')->nullable()->constrained('approval_steps')->nullOnDelete();
            $table->enum('action', ['REQUESTED', 'APPROVED', 'REJECTED', 'CANCELLED']);
            $table->text('comment')->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // ============================================================
        // 22. Notificaciones y adjuntos
        // ============================================================

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('type', 80);
            $table->string('title', 255);
            $table->text('body')->nullable();
            $table->json('data')->nullable();
            $table->tinyInteger('priority')->default(0);
            $table->boolean('is_broadcast')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('notification_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_id')->constrained('notifications')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_read')->default(false);
            $table->dateTime('read_at')->nullable();
            $table->unique(['notification_id', 'user_id']);
            $table->timestamps();
        });

        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('reference_table', 100);
            $table->unsignedBigInteger('reference_id');
            $table->string('file_name', 255);
            $table->string('file_path', 255);
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('mime_type', 80)->nullable();
            $table->text('description')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->index(['reference_table', 'reference_id']);
            $table->timestamps();
        });

        // ============================================================
        // 23. Tipos de cambio (depende de currencies y users)
        // ============================================================

        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_currency_id')->constrained('currencies')->cascadeOnDelete();
            $table->foreignId('to_currency_id')->constrained('currencies')->cascadeOnDelete();
            $table->decimal('rate', 18, 8);
            $table->date('date');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'exchange_rates',
            'attachments',
            'notification_users',
            'notifications',
            'approval_history',
            'approval_steps',
            'approvals',
            'report_schedules',
            'number_sequences',
            'two_factor_codes',
            'password_history',
            'login_history',
            'tax_group_items',
            'tax_groups',
            'fiscal_periods',
            'fiscal_years',
            'bank_reconciliation_items',
            'bank_reconciliations',
            'budget_details',
            'budgets',
            'asset_depreciation',
            'fixed_assets',
            'asset_categories',
            'material_request_items',
            'material_requests',
            'purchase_request_items',
            'purchase_requests',
            'quotation_items',
            'quotations',
            'activities',
            'opportunities',
            'leads',
            'production_entries',
            'bom_items',
            'bill_of_materials',
            'manufacturing_orders',
            'inventory_adjustment_items',
            'inventory_adjustments',
            'warehouse_transfer_items',
            'warehouse_transfers',
            'stock_count_items',
            'stock_counts',
            'customer_rewards',
            'customer_points',
            'discount_rules',
            'promotion_customers',
            'promotion_items',
            'promotions',
            'inventory_movements',
            'inventory_serials',
            'inventory_lots',
            'inventory_stock',
            'purchase_return_items',
            'purchase_returns',
            'sales_return_items',
            'sales_returns',
            'payments',
            'bank_transactions',
            'bank_accounts',
            'accounting_accounts',
            'purchase_items',
            'purchases',
            'sale_items',
            'sales',
            'warehouses',
            'product_cost_history',
            'product_price_history',
            'product_unit_conversions',
            'product_price_list_items',
            'product_taxes',
            'product_images',
            'product_variants',
            'products',
            'customers',
            'customer_types',
            'suppliers',
            'price_lists',
            'brands',
            'categories',
            'units',
            'cost_centers',
            'employees',
            'users',
            'role_permissions',
            'roles',
            'departments',
            'branches',
            'companies',
            'permissions',
            'document_types',
            'payment_terms',
            'taxes',
            'currencies',
        ];

        foreach ($tables as $table) {
            Schema::dropIfExists($table);
        }
    }
};
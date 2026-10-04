<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Core NuttyLand schema, based on the ERD in the draft report (Figure 8),
 * extended with:
 *  - product_variants  (one product, many weights/prices – e.g. 100g, 500g, 1kg)
 *  - locations         (the ERD "Market" entity, plus the warehouse as a location type)
 *  - market_days       (market schedule: which market runs on which date/time)
 *  - stock_movements   (ledger of every stock change – sale, transfer, adjustment …)
 *  - sale_items        (a stall sale can contain several products)
 *  - audit_logs        (administrative change history)
 *  - suppliers         (future-phase entity, created now so the model is ready)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contact_name')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->text('ingredients')->nullable();
            $table->string('allergen_info')->nullable();
            $table->string('flavour')->nullable();
            $table->enum('roast_style', ['raw', 'dry_roasted', 'roasted_salted', 'activated', 'other'])->nullable();
            $table->boolean('is_organic')->default(false);
            $table->string('country_of_origin')->nullable();
            $table->string('image_path')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->enum('status', ['draft', 'active', 'inactive'])->default('draft')->index();
            // Controlled approval of product / allergen information (report §13.1)
            $table->foreignId('allergen_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('allergen_approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('sku')->unique();
            $table->unsignedInteger('weight_grams');
            $table->unsignedInteger('price_cents');
            $table->unsignedInteger('low_stock_threshold')->default(10);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['product_id', 'weight_grams']);
        });

        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['warehouse', 'market', 'store'])->default('market')->index();
            $table->string('suburb')->nullable();
            $table->string('address')->nullable();
            $table->string('operating_days')->nullable(); // e.g. "Sat, Sun"
            $table->time('default_opens_at')->nullable();
            $table->time('default_closes_at')->nullable();
            $table->boolean('click_collect_enabled')->default(true);
            $table->boolean('is_confirmed')->default(true); // two markets are still "to be confirmed"
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('market_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->time('opens_at');
            $table->time('closes_at');
            $table->enum('status', ['scheduled', 'cancelled', 'completed'])->default('scheduled');
            $table->string('notes')->nullable();
            $table->timestamps();
            $table->unique(['location_id', 'date']);
        });

        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity')->default(0);
            $table->timestamps(); // updated_at = ERD "LastUpdated"
            $table->unique(['product_variant_id', 'location_id']);
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->index();
            $table->string('phone', 30)->nullable();
            $table->string('address')->nullable();
            $table->boolean('marketing_opt_in')->default(false);
            $table->unsignedInteger('loyalty_points')->default(0); // future phase
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->enum('fulfilment_method', ['click_collect', 'delivery'])->default('click_collect');
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete(); // collection market
            $table->foreignId('market_day_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('status', ['pending_payment', 'confirmed', 'preparing', 'ready', 'collected', 'cancelled'])
                ->default('pending_payment')->index();
            $table->enum('payment_status', ['unpaid', 'paid', 'failed', 'refunded'])->default('unpaid');
            $table->string('payment_reference')->nullable()->unique();
            $table->unsignedInteger('subtotal_cents')->default(0);
            $table->unsignedInteger('total_cents')->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('collected_at')->nullable();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->string('product_name');
            $table->unsignedInteger('weight_grams');
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('unit_price_cents');
            $table->unsignedInteger('subtotal_cents');
            $table->timestamps();
        });

        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            // Generated on the staff device so an offline sale synced twice is only stored once.
            $table->uuid('client_uuid')->unique();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->foreignId('market_day_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('payment_method', ['cash', 'eftpos'])->default('eftpos');
            $table->unsignedInteger('total_cents')->default(0);
            $table->timestamp('sold_at')->index();
            $table->boolean('recorded_offline')->default(false);
            $table->timestamps();
        });

        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('unit_price_cents');
            $table->unsignedInteger('subtotal_cents');
            $table->timestamps();
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->integer('quantity_change');
            $table->integer('quantity_after');
            $table->enum('type', [
                'receipt', 'market_sale', 'online_order', 'order_cancelled',
                'transfer_out', 'transfer_in', 'return', 'damaged', 'stocktake', 'adjustment',
            ])->index();
            $table->nullableMorphs('reference');
            $table->uuid('transfer_group')->nullable()->index();
            $table->string('reason')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 40);
            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
            $table->index(['auditable_type', 'auditable_id']);
        });
    }

    public function down(): void
    {
        foreach ([
            'audit_logs', 'stock_movements', 'sale_items', 'sales', 'order_items', 'orders',
            'customers', 'inventories', 'market_days', 'locations', 'product_variants',
            'products', 'suppliers', 'categories',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};

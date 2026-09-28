<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The item library (Master Data spec §2): everything a factory buys, stocks
 * or issues — fabric, yarn, trims, chemicals, general stores — as one
 * admin-maintained list that requisitions and purchase orders pick from.
 * Nobody types an item into a transaction; if it is not here, an admin adds
 * it first.
 *
 * Its own tables, deliberately separate from `spare_parts`: that is the
 * maintenance store's catalogue and stays as it is. How stock of an item is
 * held is decided with goods receipt completion (Phase 1), not here.
 *
 * Categories are three levels deep in one self-referencing table; the
 * type-specific technical spec (GSM, count, expiry tracking) lives in a
 * one-to-one extension table per type so a bearing never carries a GSM
 * column.
 */
return new class extends Migration
{
    public function up(): void
    {
        // The library's own department axis (Yarn Store, Garments Store,
        // General Store...), separate from the organisation's departments
        // (HR, IT, Finishing): where an item is kept, not who works where.
        // `default_item_type` is what a new item filed here defaults to,
        // stored on the row so renaming "Yarn Store" cannot break it.
        Schema::create('item_departments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('code', 32);
            $table->string('name');
            $table->string('name_bn')->nullable();
            $table->string('default_item_type', 16)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code'], 'item_departments_code_unique');
        });

        Schema::create('item_categories', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->ulid('parent_id')->nullable();
            $table->string('name');
            $table->string('name_bn')->nullable();
            $table->string('code', 48);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code'], 'item_categories_code_unique');
            $table->index(['company_id', 'parent_id']);
        });

        // Added after the table exists: Postgres wants the primary key in
        // place before a self-referencing constraint can point at it.
        Schema::table('item_categories', function (Blueprint $table): void {
            $table->foreign('parent_id')->references('id')->on('item_categories')->nullOnDelete();
        });

        Schema::create('items', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('item_department_id')->nullable()->constrained('item_departments')->nullOnDelete();
            $table->foreignUlid('category_id')->nullable()->constrained('item_categories')->nullOnDelete();
            $table->foreignUlid('sub_category_id')->nullable()->constrained('item_categories')->nullOnDelete();
            $table->foreignUlid('detail_category_id')->nullable()->constrained('item_categories')->nullOnDelete();

            $table->string('item_code', 64);
            // The id the item had in the system it was migrated from.
            $table->string('legacy_item_id', 32)->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('item_type', 16)->default('GENERAL');
            $table->string('tracking_mode', 16)->default('QUANTITY');

            $table->foreignUlid('uom_id')->constrained('units_of_measure')->restrictOnDelete();
            $table->foreignUlid('purchase_uom_id')->nullable()->constrained('units_of_measure')->nullOnDelete();

            $table->string('model', 100)->nullable();
            $table->string('brand')->nullable();
            $table->foreignUlid('manufacturer_id')->nullable()->constrained('manufacturers')->nullOnDelete();
            $table->string('origin', 100)->nullable();

            $table->foreignUlid('default_store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->foreignUlid('hs_code_id')->nullable()->constrained('hs_codes')->nullOnDelete();
            $table->foreignUlid('default_vendor_id')->nullable()->constrained('vendors')->nullOnDelete();

            $table->decimal('minimum_stock', 18, 4)->default(0);
            $table->decimal('reorder_level', 18, 4)->default(0);
            $table->boolean('is_returnable')->default(false);
            $table->boolean('hazardous')->default(false);
            $table->boolean('active')->default(true);
            $table->foreignUlid('created_by')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'item_code'], 'items_item_code_unique');
            $table->index(['company_id', 'item_department_id']);
            $table->index(['company_id', 'item_type']);
            $table->index(['company_id', 'legacy_item_id']);
            $table->index(['company_id', 'active']);
        });

        Schema::create('item_fabric_attributes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('item_id')->unique()->constrained('items')->cascadeOnDelete();
            $table->string('fabric_type', 16)->nullable();
            $table->string('composition')->nullable();
            $table->decimal('gsm', 8, 2)->nullable();
            $table->decimal('width', 8, 2)->nullable();
            $table->string('construction')->nullable();
            $table->string('finish')->nullable();
            $table->string('shrinkage', 64)->nullable();
            $table->string('pantone', 32)->nullable();
            $table->string('color_fastness', 64)->nullable();
            $table->timestamps();
        });

        Schema::create('item_yarn_attributes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('item_id')->unique()->constrained('items')->cascadeOnDelete();
            $table->string('count', 32)->nullable();
            $table->string('type', 64)->nullable();
            $table->string('fiber')->nullable();
            $table->string('twist', 32)->nullable();
            $table->string('color', 64)->nullable();
            $table->timestamps();
        });

        Schema::create('item_chemical_attributes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('item_id')->unique()->constrained('items')->cascadeOnDelete();
            $table->boolean('expiry_tracking')->default(false);
            $table->text('hazard_info')->nullable();
            $table->boolean('batch_tracking')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_chemical_attributes');
        Schema::dropIfExists('item_yarn_attributes');
        Schema::dropIfExists('item_fabric_attributes');
        Schema::dropIfExists('items');
        Schema::dropIfExists('item_categories');
        Schema::dropIfExists('item_departments');
    }
};

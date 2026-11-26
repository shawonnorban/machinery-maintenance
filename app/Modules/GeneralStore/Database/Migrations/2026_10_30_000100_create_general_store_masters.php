<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * General Store → Master Data (AnnotechERP 10/01): the store's own lists —
 * categories, units, departments, suppliers, stores and items — and the
 * movement ledger an item's stock is the sum of, starting with its opening
 * stock. Machinery's spare-part store keeps its own tables apart.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['gs_categories', 'gs_departments'] as $name) {
            Schema::create($name, function (Blueprint $table) use ($name): void {
                $table->ulid('id')->primary();
                $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
                $table->string('code', 32);
                $table->string('name');
                $table->text('description')->nullable();
                $table->boolean('active')->default(true);
                $table->timestamps();
                $table->unique(['company_id', 'code'], "{$name}_code_unique");
            });
        }

        Schema::create('gs_units', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('code', 16);
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code'], 'gs_units_code_unique');
        });

        Schema::create('gs_suppliers', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('code', 32);
            $table->string('name');
            $table->string('contact_person')->nullable();
            $table->string('mobile', 32)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code'], 'gs_suppliers_code_unique');
        });

        Schema::create('gs_stores', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('code', 32);
            $table->string('name');
            $table->string('location')->nullable();
            $table->string('in_charge')->nullable();
            // The store entry forms open on; one per company.
            $table->boolean('is_default')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code'], 'gs_stores_code_unique');
        });

        Schema::create('gs_items', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('gs_category_id')->constrained('gs_categories')->restrictOnDelete();
            $table->foreignUlid('gs_unit_id')->constrained('gs_units')->restrictOnDelete();
            $table->string('item_code', 64);
            $table->string('name');
            $table->string('sub_category')->nullable();
            $table->string('brand')->nullable();
            $table->decimal('minimum_stock', 18, 4)->default(0);
            $table->decimal('reorder_level', 18, 4)->default(0);
            $table->decimal('default_price', 18, 4)->nullable();
            $table->string('rack_location', 64)->nullable();
            $table->text('specification')->nullable();
            $table->boolean('active')->default(true);
            $table->foreignUlid('created_by')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'item_code'], 'gs_items_code_unique');
        });

        // Every change to stock is a row here; an item's stock in a store is
        // the sum. Receive, issue, return, adjust and transfer (10/03 …) post
        // the same way the opening stock does.
        Schema::create('gs_stock_movements', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('gs_item_id')->constrained('gs_items')->cascadeOnDelete();
            $table->foreignUlid('gs_store_id')->constrained('gs_stores')->restrictOnDelete();
            $table->string('movement_type', 16); // OPENING | RECEIVE | ISSUE | RETURN | ADJUST | TRANSFER
            $table->decimal('quantity', 18, 4); // signed: inward positive, outward negative
            $table->decimal('unit_price', 18, 4)->nullable();
            $table->date('moved_on');
            $table->string('reference')->nullable();
            $table->foreignUlid('created_by')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'gs_item_id', 'gs_store_id'], 'gs_stock_movements_item_store_index');
        });
    }

    public function down(): void
    {
        foreach (['gs_stock_movements', 'gs_items', 'gs_stores', 'gs_suppliers', 'gs_units', 'gs_departments', 'gs_categories'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};

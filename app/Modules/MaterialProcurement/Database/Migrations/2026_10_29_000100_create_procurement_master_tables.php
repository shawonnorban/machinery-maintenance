<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Material Procurement → Master Data (AnnotechERP 08/01): the fabric and the
 * trims & accessories a requisition may ask for — one catalogue, told apart by
 * kind — and the procurement suppliers. These are the ERP's own; Machinery
 * keeps its vendor register apart.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mp_items', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('kind', 8); // FABRIC | TRIMS
            $table->string('item_code', 64);
            $table->string('name');
            $table->string('category');
            $table->string('sub_category')->nullable();
            // As Brickland keeps them: what the supplier quotes, "160" or "60\"".
            $table->string('gsm', 32)->nullable();
            $table->string('width', 32)->nullable();
            $table->string('composition')->nullable();
            $table->string('size', 64)->nullable();
            $table->string('colour', 64)->nullable();
            $table->string('uom', 16);
            $table->string('specification')->nullable();
            $table->text('description')->nullable();
            $table->string('remarks')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'kind', 'item_code'], 'mp_items_code_unique');
            $table->index(['company_id', 'kind', 'active']);
        });

        Schema::create('mp_suppliers', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('code', 48);
            $table->string('name');
            $table->string('short_name', 64)->nullable();
            $table->string('supplier_type', 16); // LOCAL_NON_EPZ | LOCAL_EPZ | FOREIGN
            $table->string('owner_name')->nullable();
            $table->string('goods_service')->nullable();
            $table->string('vat_reg_no', 32)->nullable();
            $table->string('tin_no', 32)->nullable();
            $table->text('address')->nullable();
            $table->boolean('active')->default(true);
            $table->foreignUlid('created_by')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'code'], 'mp_suppliers_code_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mp_suppliers');
        Schema::dropIfExists('mp_items');
    }
};

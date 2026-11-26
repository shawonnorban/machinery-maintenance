<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Knit Production → Production (AnnotechERP 15/04) → Supplier Yarn
 * Replacement Indent: a claim on a spinner to replace yarn this mill will not
 * knit (YRR-…), one line per lot going back. Draft → Submitted → Issued (the
 * yarn has physically left — the store's Issue Yarn Replace by Supplier
 * challan, 15/03, carries it out); Cancelled.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knit_yarn_replace_reqs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('req_no', 24);
            $table->date('req_date');
            $table->foreignUlid('store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignUlid('supplier_id')->constrained('mp_suppliers')->restrictOnDelete();
            // YarnReplacement::TYPES.
            $table->string('return_type', 24);
            $table->string('lc_no', 40)->nullable();
            $table->string('lot_ref', 40)->nullable();
            $table->text('remarks')->nullable();
            // DRAFT, SUBMITTED, ISSUED, CANCELLED.
            $table->string('status', 12)->default('DRAFT');
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'req_no']);
            $table->index(['company_id', 'status', 'req_date']);
        });

        Schema::create('knit_yarn_replace_req_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('req_id')->constrained('knit_yarn_replace_reqs')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order');
            $table->foreignUlid('composition_id')->nullable()->constrained('fiber_compositions')->nullOnDelete();
            $table->foreignUlid('yarn_count_id')->nullable()->constrained('knit_yarn_counts')->nullOnDelete();
            $table->string('colour_group', 40)->nullable();
            $table->string('brand', 80)->nullable();
            $table->string('lot_no', 40);
            $table->decimal('qty', 14, 3);
            $table->string('uom', 16);
            $table->string('remarks', 300)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knit_yarn_replace_req_lines');
        Schema::dropIfExists('knit_yarn_replace_reqs');
    }
};

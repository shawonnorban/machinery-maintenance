<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Material Procurement → Supplier Portal (AnnotechERP 08/04): a supplier's
 * quotation on one requisition line (QT-2026-00001) and every price revision
 * it sends. A revision is never edited or deleted: a new price is the next
 * revision, the earlier ones kept as they were.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mp_quotations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('qt_no', 24);
            $table->foreignUlid('requisition_line_id')->constrained('mp_requisition_lines')->restrictOnDelete();
            $table->foreignUlid('supplier_id')->constrained('mp_suppliers')->restrictOnDelete();
            $table->string('status', 16)->default('PENDING');
            $table->unsignedSmallInteger('revisions')->default(1);
            $table->timestamps();
            $table->unique(['company_id', 'qt_no']);
            // One quotation per supplier per line; a new price is a revision of it.
            $table->unique(['requisition_line_id', 'supplier_id']);
        });

        Schema::create('mp_quotation_revisions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('quotation_id')->constrained('mp_quotations')->cascadeOnDelete();
            $table->unsignedSmallInteger('revision');
            $table->decimal('quoted_qty', 14, 3);
            $table->decimal('unit_price', 14, 4);
            $table->string('currency', 3);
            $table->unsignedSmallInteger('lead_time_days');
            $table->date('expected_delivery');
            $table->text('remarks')->nullable();
            $table->foreignUlid('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at');
            $table->timestamps();
            $table->unique(['quotation_id', 'revision']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mp_quotation_revisions');
        Schema::dropIfExists('mp_quotations');
    }
};

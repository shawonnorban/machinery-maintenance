<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * General Store → Inventory (AnnotechERP 10/03): the store's documents —
 * Opening Stock, Stock Receive, Issue, Return, Adjustment and Transfer — each
 * a header and its item lines, posted at once to `gs_stock_movements` and
 * cancelled (never deleted) by posting the same movements reversed; and the
 * store's audit trail (who created, issued, cancelled or changed a price, and
 * when), which Administration → Audit Log reads.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gs_documents', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            // OPENING, RECEIVE, ISSUE, RETURN, ADJUST or TRANSFER.
            $table->string('doc_type', 12);
            $table->string('doc_no', 24);
            $table->date('doc_date');
            // The store it posts to — a transfer's From store.
            $table->foreignUlid('gs_store_id')->constrained('gs_stores')->restrictOnDelete();
            $table->foreignUlid('to_store_id')->nullable()->constrained('gs_stores')->restrictOnDelete();
            $table->foreignUlid('gs_supplier_id')->nullable()->constrained('gs_suppliers')->nullOnDelete();
            $table->string('challan_no', 64)->nullable();
            $table->string('reference_no', 64)->nullable();
            $table->foreignUlid('gs_department_id')->nullable()->constrained('gs_departments')->restrictOnDelete();
            // An issue's employee / receiver, a return's returned-by — as typed.
            $table->string('person', 120)->nullable();
            // A return's original issue; an issue's requisition (Requisition, 10/05).
            $table->ulid('against_id')->nullable();
            $table->ulid('requisition_id')->nullable();
            // INCREASE, DECREASE, DAMAGE, LOST, COUNT (physical count correction) or OTHER.
            $table->string('adjustment_type', 12)->nullable();
            $table->text('remarks')->nullable();
            // POSTED or CANCELLED.
            $table->string('status', 10)->default('POSTED');
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 2000)->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'doc_no']);
            $table->index(['company_id', 'doc_type', 'doc_date']);
        });

        // Its own table, so added once the key it points at exists.
        Schema::table('gs_documents', function (Blueprint $table): void {
            $table->foreign('against_id')->references('id')->on('gs_documents')->restrictOnDelete();
        });

        Schema::create('gs_document_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('gs_document_id')->constrained('gs_documents')->cascadeOnDelete();
            $table->foreignUlid('gs_item_id')->constrained('gs_items')->restrictOnDelete();
            // As entered: positive. A count correction keeps the physical count here and the difference below.
            $table->decimal('quantity', 18, 4);
            // What reached the books, signed: inward positive, outward negative.
            $table->decimal('movement_qty', 18, 4);
            $table->decimal('unit_price', 18, 4)->nullable();
            // An issue's last purchase price when the line was made; a price typed over it is audited.
            $table->decimal('suggested_price', 18, 4)->nullable();
            $table->string('line_remarks', 500)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(1);
            $table->timestamps();
        });

        Schema::table('gs_stock_movements', function (Blueprint $table): void {
            $table->foreignUlid('gs_document_id')->nullable()->after('gs_store_id')->constrained('gs_documents')->restrictOnDelete();
            // A cancelled document's movement posted back the other way.
            $table->boolean('is_reversal')->default(false)->after('reference');
        });

        Schema::create('gs_audit_trail', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('user_id')->nullable()->constrained('users')->nullOnDelete();
            // CREATE, UPDATE, PRICE_CHANGE, REPORT_EXPORT …
            $table->string('action', 24);
            // The screen it was done on: gs-receive, gs-issue …
            $table->string('screen', 40);
            $table->string('reference', 40)->nullable();
            $table->string('summary', 1000);
            $table->timestamp('created_at', 6)->useCurrent();
            $table->index(['company_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gs_audit_trail');
        Schema::table('gs_stock_movements', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('gs_document_id');
            $table->dropColumn('is_reversal');
        });
        Schema::dropIfExists('gs_document_lines');
        Schema::dropIfExists('gs_documents');
    }
};

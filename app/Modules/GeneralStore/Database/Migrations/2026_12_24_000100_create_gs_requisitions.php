<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * General Store → Requisition and Administration (AnnotechERP 10/04–10/07):
 * a department's requisition for items — drafted, submitted, approved line
 * by line (or rejected, or cancelled) and then issued against, in part or
 * in full — and the store's own settings: whether a requisition needs
 * approval, whether the store may issue without one, and the name its
 * vouchers carry.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gs_requisitions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('req_no', 24);
            $table->date('req_date');
            $table->foreignUlid('gs_department_id')->constrained('gs_departments')->restrictOnDelete();
            $table->foreignUlid('gs_store_id')->constrained('gs_stores')->restrictOnDelete();
            $table->text('reason')->nullable();
            // DRAFT, PENDING, APPROVED, PARTIAL (partly issued), ISSUED, REJECTED or CANCELLED.
            $table->string('status', 10)->default('DRAFT');
            $table->foreignUlid('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignUlid('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('approval_note', 2000)->nullable();
            // Why it was rejected or cancelled.
            $table->string('closing_reason', 2000)->nullable();
            $table->foreignUlid('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'req_no']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('gs_requisition_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('gs_requisition_id')->constrained('gs_requisitions')->cascadeOnDelete();
            $table->foreignUlid('gs_item_id')->constrained('gs_items')->restrictOnDelete();
            $table->decimal('requested_qty', 18, 4);
            $table->decimal('approved_qty', 18, 4)->nullable();
            $table->string('remarks', 500)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(1);
            $table->timestamps();
        });

        Schema::table('gs_documents', function (Blueprint $table): void {
            $table->foreign('requisition_id')->references('id')->on('gs_requisitions')->restrictOnDelete();
        });

        Schema::create('gs_settings', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->unique()->constrained('companies')->cascadeOnDelete();
            // A submitted requisition waits for the Store Manager; off, it is approved as requested.
            $table->boolean('requisition_approval')->default(true);
            // The store may issue without a requisition ("Direct issue").
            $table->boolean('direct_issue')->default(true);
            // An issue names who took the items.
            $table->boolean('receiver_required')->default(false);
            // The name at the top of every voucher; blank is the company's own.
            $table->string('voucher_name', 160)->nullable();
            $table->string('voucher_footer', 300)->nullable();
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gs_settings');
        Schema::table('gs_documents', function (Blueprint $table): void {
            $table->dropForeign(['requisition_id']);
        });
        Schema::dropIfExists('gs_requisition_lines');
        Schema::dropIfExists('gs_requisitions');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Material Procurement → Approved Material & Follow-Up (AnnotechERP 08/06): the
 * follow-ups the merchandiser logs on an approved line — who was spoken to,
 * the supplier's answer, the delivery step reached, the quantity received in
 * this delivery and when to follow up next — and the draft goods receipt a
 * receipt is turned into on Merchandise Desk's receiving screen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mp_followups', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('requisition_line_id')->constrained('mp_requisition_lines')->cascadeOnDelete();
            $table->date('followup_date');
            $table->string('contact_person', 255)->nullable();
            $table->string('status', 24);
            $table->decimal('received_qty', 14, 3)->default(0);
            $table->date('expected_delivery')->nullable();
            $table->date('actual_delivery')->nullable();
            $table->text('supplier_response')->nullable();
            $table->text('remarks')->nullable();
            $table->date('next_followup_date')->nullable();
            // The draft receipt raised from this delivery: a fabric (MRR-IMF) or accessories (MRR-ACC) receipt.
            $table->string('grn_type', 8)->nullable();
            $table->ulid('grn_id')->nullable();
            $table->string('grn_no', 24)->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['requisition_line_id', 'followup_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mp_followups');
    }
};

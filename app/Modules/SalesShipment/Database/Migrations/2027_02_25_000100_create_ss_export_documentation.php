<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Sales & Shipment → Export Documentation (AnnotechERP 21/06), as Brickland
 * keeps it.
 *
 * - `ss_export_docs`: the documents filed on a shipment, one table for four
 *   screens told apart by `family` — EXPORT (EXP, inspection, insurance,
 *   other), COO, GSP (GSP / REX) and BLAWB (bill of lading / air waybill);
 *   number unique per document type; pending, received, submitted, approved
 *   or cancelled; with an attachment.
 * - `ss_certificates`: certificates issued for a shipment, typed from the
 *   Certificate Master (Company Management's company documents), with
 *   expiry; active or cancelled.
 * - `ss_shipment_attachments`: scans and files kept against a shipment.
 * - `ss_doc_handovers` gains `kind`: COMMERCIAL (21/05, Commercial →
 *   Shipping) or FINAL (21/06, to the buyer or the bank).
 */
return new class extends Migration
{
    public function up(): void
    {
        $file = function (Blueprint $table): void {
            $table->foreignUlid('file_id')->nullable();
            $table->string('file_name', 255)->nullable();
        };

        Schema::create('ss_export_docs', function (Blueprint $table) use ($file): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('shipment_id')->constrained('ss_shipments')->restrictOnDelete();
            $table->string('family', 8); // EXPORT | COO | GSP | BLAWB
            $table->string('doc_type', 16); // EXP | INSPECTION | INSURANCE | OTHER | COO | GSP | REX | BILL_OF_LADING | AIR_WAYBILL
            $table->string('document_no', 60);
            $table->date('document_date');
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('issuing_authority', 160)->nullable();
            $table->string('status', 10)->default('PENDING'); // PENDING | RECEIVED | SUBMITTED | APPROVED | CANCELLED
            $file($table);
            $table->text('remarks')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'doc_type', 'document_no']);
        });

        Schema::create('ss_certificates', function (Blueprint $table) use ($file): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('shipment_id')->constrained('ss_shipments')->restrictOnDelete();
            $table->foreignUlid('certificate_type_id')->nullable()->constrained('company_documents')->nullOnDelete();
            $table->string('certificate_name', 160);
            $table->string('certificate_no', 60);
            $table->date('issue_date');
            $table->date('expiry_date')->nullable();
            $table->string('issuing_authority', 160)->nullable();
            $table->string('status', 10)->default('ACTIVE'); // ACTIVE | CANCELLED
            $file($table);
            $table->text('remarks')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['shipment_id', 'certificate_no']);
        });

        Schema::create('ss_shipment_attachments', function (Blueprint $table) use ($file): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('shipment_id')->constrained('ss_shipments')->restrictOnDelete();
            $table->string('document_type', 24);
            $table->string('title', 160); // "File Name": how the document is referred to
            $file($table);
            $table->text('remarks')->nullable();
            $table->foreignUlid('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();
        });

        Schema::table('ss_doc_handovers', function (Blueprint $table): void {
            $table->string('kind', 10)->default('COMMERCIAL')->after('handover_no'); // COMMERCIAL | FINAL
        });
    }

    public function down(): void
    {
        Schema::table('ss_doc_handovers', fn (Blueprint $table) => $table->dropColumn('kind'));
        foreach (['ss_shipment_attachments', 'ss_certificates', 'ss_export_docs'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};

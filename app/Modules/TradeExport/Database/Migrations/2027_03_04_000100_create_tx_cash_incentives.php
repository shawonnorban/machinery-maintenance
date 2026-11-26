<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trade & Export → Cash Incentive (AnnotechERP 22/05):
 *
 * - `tx_cash_incentives`: a cash incentive claim on an export L/C or sales
 *   contract, followed from application through document submission, the
 *   audit certificate and the bank's collection.
 * - `tx_export_lcs` gains its PRC (proceeds realisation certificate): the
 *   number, issue date, the day it was entered and the scanned document,
 *   managed from the L/C ("Manage PRC").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tx_cash_incentives', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('lc_id')->constrained('tx_export_lcs')->restrictOnDelete();
            $table->string('cia_type', 8); // REGULAR | SPECIAL
            $table->unsignedTinyInteger('claim_part_no');
            $table->string('claim_sl_no', 40);
            $table->date('application_date');
            $table->date('doc_sub_date')->nullable();
            $table->decimal('claim_amount', 16, 4)->default(0);
            $table->string('claim_currency', 3)->default('USD');
            $table->string('certificate_no', 60)->nullable();
            $table->string('audit_currency', 3)->default('BDT');
            $table->date('audit_cert_date')->nullable();
            $table->decimal('audit_amount', 16, 4)->default(0);
            $table->date('collection_date')->nullable();
            $table->string('remarks', 600)->nullable();
            $table->boolean('final')->default(false);
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'claim_sl_no']);
            $table->unique(['lc_id', 'cia_type', 'claim_part_no']);
        });

        Schema::table('tx_export_lcs', function (Blueprint $table): void {
            $table->string('prc_no', 60)->nullable()->after('lc_charge');
            $table->date('prc_date')->nullable()->after('prc_no');
            $table->date('prc_entry_date')->nullable()->after('prc_date');
            $table->ulid('prc_file_id')->nullable()->after('prc_entry_date');
            $table->string('prc_file_name', 200)->nullable()->after('prc_file_id');
        });
    }

    public function down(): void
    {
        Schema::table('tx_export_lcs', fn (Blueprint $table) => $table->dropColumn(['prc_no', 'prc_date', 'prc_entry_date', 'prc_file_id', 'prc_file_name']));
        Schema::dropIfExists('tx_cash_incentives');
    }
};

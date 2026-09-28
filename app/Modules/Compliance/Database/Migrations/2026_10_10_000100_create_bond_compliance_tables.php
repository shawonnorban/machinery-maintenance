<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bonded warehouse and UD/UP (Shared Systems spec §3).
 *
 * A Bangladeshi export factory imports fabric and trims duty-free against
 * a bond licence, on the promise that they leave again as garments. Three
 * records carry that promise:
 *
 *  - the **licence**, and what it entitles the factory to import;
 *  - the **utilisation declaration** — what a particular order says it
 *    will consume, which customs accepts in advance;
 *  - the **duty assessment**, written when goods leave the bond for
 *    anything other than export, because then the exemption no longer
 *    applies and the duty falls due.
 *
 * Two shapes worth explaining:
 *
 *  - Entitlement carries no `consumed_quantity` column. What has actually
 *    been imported against a licence is a sum over goods receipts, and a
 *    stored running total is a second answer to a question the ledger
 *    already answers — one that drifts the first time a receipt is
 *    cancelled. It is computed (`BondUtilisation`) rather than kept.
 *  - A duty assessment stores the **rates it used**, not just a reference
 *    to the HS code. Customs rates change; an assessment raised last year
 *    has to keep saying what was charged then, and a join to today's rate
 *    would quietly restate it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bond_licences', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('factory_id')->constrained('factories')->cascadeOnDelete();
            $table->string('licence_no', 64);
            $table->string('issuing_office', 160);
            $table->date('issued_date');
            $table->date('valid_from');
            $table->date('valid_to');
            // ACTIVE | SUSPENDED | CANCELLED. Expiry is not a status: the
            // dates already say it, and a stored one is only as true as
            // the last sweep that touched it.
            $table->string('status', 24)->default('ACTIVE');
            $table->text('notes')->nullable();
            $table->foreignUlid('created_by')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'licence_no'], 'bond_licences_no_unique');
            $table->index(['company_id', 'factory_id', 'status'], 'bond_licences_factory_idx');
        });

        Schema::create('bond_entitlements', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('bond_licence_id')->constrained('bond_licences')->cascadeOnDelete();
            $table->foreignUlid('item_id')->constrained('items')->restrictOnDelete();
            $table->string('item_code', 64);
            $table->string('item_name', 255);
            $table->foreignUlid('uom_id')->nullable()->constrained('units_of_measure')->nullOnDelete();
            // ANNUAL (a yearly ceiling) | PER_ORDER (against one export order)
            $table->string('basis', 16)->default('ANNUAL');
            $table->decimal('entitled_quantity', 18, 4);
            // An annual entitlement is measured over a stated year, which
            // is not always the licence's own period.
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->unique(['bond_licence_id', 'item_id', 'basis'], 'bond_entitlements_item_unique');
            $table->index(['company_id', 'item_id'], 'bond_entitlements_item_idx');
        });

        Schema::create('utilisation_declarations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('factory_id')->constrained('factories')->cascadeOnDelete();
            $table->foreignUlid('bond_licence_id')->constrained('bond_licences')->restrictOnDelete();
            $table->string('ud_no', 64);
            // UD (declaration) | UP (permission)
            $table->string('ud_type', 8)->default('UD');
            $table->date('issue_date');
            $table->date('valid_to')->nullable();
            // What it covers. Both optional: a UD is raised against an
            // export order, and the import order it will be consumed from
            // may not exist yet.
            $table->foreignUlid('production_order_id')->nullable();
            $table->foreignUlid('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->string('buyer_reference', 120)->nullable();
            $table->string('status', 24)->default('DRAFT');
            $table->text('notes')->nullable();
            $table->foreignUlid('created_by')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'ud_no'], 'utilisation_declarations_no_unique');
            $table->index(['company_id', 'bond_licence_id', 'status'], 'utilisation_declarations_licence_idx');
        });

        Schema::create('utilisation_declaration_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('utilisation_declaration_id')
                ->constrained('utilisation_declarations')->cascadeOnDelete();
            $table->foreignUlid('item_id')->constrained('items')->restrictOnDelete();
            $table->string('item_code', 64);
            $table->string('item_name', 255);
            $table->foreignUlid('uom_id')->nullable()->constrained('units_of_measure')->nullOnDelete();
            // What the declaration told customs would be consumed.
            $table->decimal('declared_quantity', 18, 4);
            $table->decimal('wastage_percent', 6, 2)->default(0);
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->unique(['utilisation_declaration_id', 'item_id'], 'ud_lines_item_unique');
        });

        Schema::create('duty_assessments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('factory_id')->constrained('factories')->cascadeOnDelete();
            $table->foreignUlid('bond_licence_id')->nullable()->constrained('bond_licences')->nullOnDelete();
            $table->string('assessment_no', 32);
            $table->date('assessment_date');
            $table->foreignUlid('item_id')->constrained('items')->restrictOnDelete();
            $table->string('item_code', 64);
            $table->string('item_name', 255);
            // Where it left from, and why it is being assessed.
            $table->foreignUlid('store_id')->nullable()->constrained('stores')->nullOnDelete();
            // LOCAL_SALE | TRANSFER_OUT | SHORTAGE | WRITE_OFF | OTHER
            $table->string('reason', 24);
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_value', 18, 4);
            $table->decimal('assessable_value', 18, 4);
            // The rates as they stood on the day — see the class note.
            $table->foreignUlid('hs_code_id')->nullable()->constrained('hs_codes')->nullOnDelete();
            $table->string('hs_code', 32)->nullable();
            $table->decimal('duty_rate', 6, 2)->default(0);
            $table->decimal('vat_rate', 6, 2)->default(0);
            $table->decimal('duty_amount', 18, 4)->default(0);
            $table->decimal('vat_amount', 18, 4)->default(0);
            $table->decimal('total_payable', 18, 4)->default(0);
            $table->char('currency', 3)->default('BDT');

            $table->string('reference_type', 48)->nullable();
            $table->ulid('reference_id')->nullable();
            $table->text('notes')->nullable();
            $table->foreignUlid('assessed_by')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'assessment_no'], 'duty_assessments_no_unique');
            $table->index(['company_id', 'assessment_date'], 'duty_assessments_date_idx');
            $table->index(['company_id', 'item_id'], 'duty_assessments_item_idx');
        });

        Schema::table('purchase_orders', function (Blueprint $table): void {
            // Import paperwork (spec §3.3). Nullable and additive: a local
            // order carries none of it.
            $table->boolean('bond_applicable')->default(false)->after('purchase_type');
            $table->foreignUlid('bond_licence_id')->nullable()->after('bond_applicable');
            $table->string('duty_exemption_reference', 64)->nullable()->after('bond_licence_id');
        });

        Schema::table('purchase_order_lines', function (Blueprint $table): void {
            // Per line, because one order can carry several tariff headings.
            $table->foreignUlid('hs_code_id')->nullable()->after('rate_contract_id');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_lines', function (Blueprint $table): void {
            $table->dropColumn('hs_code_id');
        });

        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->dropColumn(['bond_applicable', 'bond_licence_id', 'duty_exemption_reference']);
        });

        Schema::dropIfExists('duty_assessments');
        Schema::dropIfExists('utilisation_declaration_lines');
        Schema::dropIfExists('utilisation_declarations');
        Schema::dropIfExists('bond_entitlements');
        Schema::dropIfExists('bond_licences');
    }
};

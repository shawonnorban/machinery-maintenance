<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vendors, warranties and service contracts: ERD Section 15, SRS 26.
 *
 * Three records that answer one question a factory asks constantly and usually
 * cannot: is this repair already paid for? A machine under warranty that gets
 * repaired at the factory's own cost is money thrown away, and it happens
 * because the warranty lives in a drawer rather than beside the machine.
 *
 * The asset already carries warranty_start and warranty_end for the simple
 * case. This table is the fuller record — who provides the cover, what it
 * covers, and what has been claimed against it — and an asset may have more
 * than one over its life: the original manufacturer warranty, then an extended
 * one bought later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendors', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();

            $table->string('name');
            $table->string('code', 48);
            $table->string('vendor_type', 24)->default('SUPPLIER'); // SUPPLIER | SERVICE | BOTH
            $table->string('contact_name')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('tax_reference', 64)->nullable();
            $table->string('status', 16)->default('ACTIVE');
            $table->text('notes')->nullable();

            $table->foreignUlid('created_by')->nullable();
            $table->timestamps();
            // Archived rather than deleted: a vendor named on a five-year-old
            // cost entry has to stay resolvable (ADR-057).
            $table->softDeletes();

            $table->unique(['company_id', 'code', 'deleted_at'], 'vendors_code_unique');
            $table->index(['company_id', 'status'], 'vendors_status_index');
        });

        Schema::create('warranties', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->foreignUlid('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();

            $table->string('warranty_type', 24)->default('MANUFACTURER'); // MANUFACTURER | EXTENDED | SERVICE
            $table->string('reference', 64)->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->text('coverage')->nullable();
            // What the cover does not include. Written down because "covered"
            // and "covered except consumables" are different answers to the
            // question a technician is actually asking.
            $table->text('exclusions')->nullable();
            $table->string('status', 16)->default('ACTIVE'); // ACTIVE | EXPIRED | VOID
            $table->timestamps();

            $table->index(['company_id', 'asset_id'], 'warranties_asset_index');
            // Drives the expiry sweep, which reads by date across the tenant.
            $table->index(['company_id', 'end_date'], 'warranties_expiry_index');
        });

        Schema::create('warranty_claims', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('warranty_id')->constrained('warranties')->cascadeOnDelete();
            $table->foreignUlid('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->foreignUlid('breakdown_id')->nullable()->constrained('breakdowns')->nullOnDelete();
            $table->foreignUlid('work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();

            $table->string('claim_number', 32);
            $table->date('claim_date');
            $table->text('description');
            // SUBMITTED -> ACKNOWLEDGED -> APPROVED|REJECTED -> SETTLED
            $table->string('status', 16)->default('SUBMITTED');
            $table->decimal('claimed_amount', 18, 4)->nullable();
            $table->decimal('settled_amount', 18, 4)->nullable();
            $table->string('currency', 3)->nullable();
            $table->text('resolution')->nullable();
            $table->date('resolved_at')->nullable();

            $table->foreignUlid('created_by')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'claim_number'], 'warranty_claims_number_unique');
            $table->index(['company_id', 'status'], 'warranty_claims_status_index');
        });

        Schema::create('service_contracts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('vendor_id')->constrained('vendors')->cascadeOnDelete();
            // Nullable: an AMC often covers a fleet or a whole factory rather
            // than one machine, and forcing a row per machine would make the
            // contract value meaningless.
            $table->foreignUlid('asset_id')->nullable()->constrained('assets')->nullOnDelete();
            $table->foreignUlid('factory_id')->nullable()->constrained('factories')->nullOnDelete();

            $table->string('contract_number', 32);
            $table->string('contract_type', 24)->default('AMC'); // AMC | CALIBRATION | INSPECTION | SUPPORT
            $table->date('start_date');
            $table->date('end_date');
            $table->date('renewal_date')->nullable();
            $table->decimal('value', 18, 4)->nullable();
            $table->string('currency', 3)->default('BDT');
            $table->text('coverage')->nullable();
            $table->unsignedSmallInteger('visits_per_year')->nullable();
            $table->unsignedSmallInteger('response_time_hours')->nullable();
            $table->string('status', 16)->default('ACTIVE'); // ACTIVE | EXPIRED | CANCELLED | RENEWED
            $table->foreignUlid('renewed_from_contract_id')->nullable()
                ->constrained('service_contracts')->nullOnDelete();
            $table->text('notes')->nullable();

            $table->foreignUlid('created_by')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'contract_number'], 'service_contracts_number_unique');
            $table->index(['company_id', 'end_date'], 'service_contracts_expiry_index');
            $table->index(['company_id', 'vendor_id'], 'service_contracts_vendor_index');
        });

        // Which assets a fleet-level contract covers. Without it, a contract
        // over "all sewing machines in Dhaka" cannot answer the only question
        // that matters at the machine: is this one covered?
        Schema::create('service_contract_assets', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('service_contract_id')->constrained('service_contracts')->cascadeOnDelete();
            $table->foreignUlid('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['service_contract_id', 'asset_id'], 'service_contract_assets_unique');
            $table->index(['company_id', 'asset_id'], 'service_contract_assets_asset_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_contract_assets');
        Schema::dropIfExists('service_contracts');
        Schema::dropIfExists('warranty_claims');
        Schema::dropIfExists('warranties');
        Schema::dropIfExists('vendors');
    }
};

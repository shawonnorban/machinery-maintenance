<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dye House → Production Board (AnnotechERP 17/07).
 *
 * - `dye_productions` gains Bulk Batch Production's two ticks (by-pass check
 *   roll, scouring) and `dye_production_loads` its check-roll time.
 * - `dye_wash_productions` + lines: Machine Wash Output — what was dosed on a
 *   machine wash requisition (17/05), in kilos, grams and milligrams as
 *   written, one production per requisition.
 * - `dye_fabric_bookings` + lines: Direct (DFB-) and General (GFB-) Fabric
 *   Booking — fabric booked to the dye house by fabric and shade, against one
 *   order or none.
 * - `dye_trim_programmes` + lines: Trims Dyeing Programme (TDP-) — dyeable
 *   trims in yards and kilos for one order.
 * - `dye_check_roll_params`: Check Roll Parameter Setup — the GSM, width,
 *   shade and shrinkage tolerances a check roll is measured against, per
 *   buyer and fabric type.
 * - `dye_order_closings`: Dyeing & Finishing Closing — an order closed out of
 *   dyeing and finishing for a scope; its cards take no more production.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dye_productions', function (Blueprint $table): void {
            $table->boolean('bypass_check_roll')->default(false)->after('liquor_ratio');
            $table->boolean('scouring')->default(false)->after('bypass_check_roll');
        });
        Schema::table('dye_production_loads', function (Blueprint $table): void {
            $table->timestamp('check_roll_at')->nullable()->after('unload_time');
        });
        Schema::create('dye_wash_productions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('requisition_id')->constrained('dye_chem_requisitions')->restrictOnDelete();
            $table->date('production_date');
            $table->foreignUlid('party_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignUlid('department_id')->constrained('departments')->restrictOnDelete();
            $table->foreignUlid('dye_machine_id')->constrained('dye_machines')->restrictOnDelete();
            $table->foreignUlid('requisition_to')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique('requisition_id');
            $table->index(['company_id', 'production_date']);
        });
        Schema::create('dye_wash_production_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('production_id')->constrained('dye_wash_productions')->cascadeOnDelete();
            $table->foreignUlid('item_id')->constrained('items')->restrictOnDelete();
            $table->decimal('kg', 14, 4)->default(0);
            $table->decimal('gm', 14, 4)->default(0);
            $table->decimal('mg', 14, 4)->default(0);
            $table->decimal('total_kg', 16, 6)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
        Schema::create('dye_fabric_bookings', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            // DIRECT (DFB-YYYYMM####) or GENERAL (GFB-YYYYMM####).
            $table->string('kind', 8);
            $table->string('booking_no', 24);
            $table->string('prod_type', 8);
            $table->date('prog_date');
            $table->foreignUlid('buyer_id')->nullable()->constrained('buyers')->nullOnDelete();
            $table->foreignUlid('order_id')->nullable()->constrained('merch_orders')->nullOnDelete();
            $table->string('req_type', 60)->default('Dyeing');
            // DRAFT, SUBMITTED, APPROVED, CLOSED, CANCELLED.
            $table->string('status', 10);
            $table->text('remarks')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'booking_no']);
            $table->index(['company_id', 'kind', 'prog_date']);
        });
        Schema::create('dye_fabric_booking_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('booking_id')->constrained('dye_fabric_bookings')->cascadeOnDelete();
            $table->string('fabric', 160);
            $table->foreignUlid('colour_id')->nullable()->constrained('colours')->nullOnDelete();
            $table->decimal('qty_kg', 14, 3);
            $table->string('remarks', 255)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
        Schema::create('dye_trim_programmes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('programme_no', 24);
            $table->string('prod_type', 8);
            $table->foreignUlid('buyer_id')->constrained('buyers')->restrictOnDelete();
            $table->foreignUlid('order_id')->constrained('merch_orders')->restrictOnDelete();
            $table->date('prog_date');
            $table->string('status', 10);
            $table->text('remarks')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'programme_no']);
            $table->index(['company_id', 'prog_date']);
        });
        Schema::create('dye_trim_programme_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('programme_id')->constrained('dye_trim_programmes')->cascadeOnDelete();
            $table->string('item', 160);
            $table->foreignUlid('colour_id')->nullable()->constrained('colours')->nullOnDelete();
            $table->decimal('yds', 14, 2)->nullable();
            $table->decimal('qty_kg', 14, 3)->nullable();
            $table->string('remarks', 255)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
        Schema::create('dye_check_roll_params', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            // Blank buyer or fabric type: the parameter for every buyer, every fabric.
            $table->foreignUlid('buyer_id')->nullable()->constrained('buyers')->nullOnDelete();
            $table->foreignUlid('fabric_type_id')->nullable()->constrained('fabric_types')->nullOnDelete();
            $table->decimal('gsm_tol_pct', 6, 2);
            $table->decimal('width_tol_pct', 6, 2);
            $table->decimal('shade_delta_e', 6, 2);
            $table->decimal('shrink_length_pct', 6, 2)->nullable();
            $table->decimal('shrink_width_pct', 6, 2)->nullable();
            $table->text('remarks')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
        Schema::create('dye_order_closings', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('order_id')->constrained('merch_orders')->restrictOnDelete();
            $table->string('scope', 8);
            $table->text('remarks')->nullable();
            $table->foreignUlid('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'order_id', 'scope']);
        });
    }

    public function down(): void
    {
        foreach (['dye_order_closings', 'dye_check_roll_params', 'dye_trim_programme_lines', 'dye_trim_programmes', 'dye_fabric_booking_lines', 'dye_fabric_bookings', 'dye_wash_production_lines', 'dye_wash_productions'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('dye_production_loads', fn (Blueprint $table) => $table->dropColumn('check_roll_at'));
        Schema::table('dye_productions', fn (Blueprint $table) => $table->dropColumn(['bypass_check_roll', 'scouring']));
    }
};

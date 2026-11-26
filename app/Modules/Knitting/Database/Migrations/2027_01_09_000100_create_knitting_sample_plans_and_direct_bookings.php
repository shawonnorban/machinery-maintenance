<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Knit Production → Production (AnnotechERP 15/04):
 *
 * - `knit_sample_plans` + lines: Solid and Yarn-Dyed Sample Knit Plan — the
 *   sample room's knit plan (SYR-S-… / SYR-Y-…), one line per order or KP
 *   with the yarn the store has to issue. Draft → Requisition Submitted (the
 *   plan becomes the Yarn Store's own requisition, `issue_req_id`, and its
 *   lines are fixed; Issued and Closed are read off that requisition);
 *   Cancelled.
 * - `knit_direct_bookings` + lines: Direct Knit Fabric Booking — fabric
 *   booked straight against an order with no knitting run (DFB-…). Draft →
 *   Submitted → Approved (released to the fabric store); Cancelled.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knit_sample_plans', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('plan_no', 24);
            // SOLID, YD.
            $table->string('kind', 8);
            $table->date('plan_date');
            $table->foreignUlid('buyer_id')->nullable()->constrained('buyers')->nullOnDelete();
            $table->foreignUlid('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->string('req_type', 32)->nullable();
            $table->text('remarks')->nullable();
            // DRAFT, REQ_SUBMITTED, CANCELLED.
            $table->string('status', 16)->default('DRAFT');
            $table->foreignUlid('issue_req_id')->nullable()->constrained('knit_yarn_issue_reqs')->nullOnDelete();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'plan_no']);
            $table->index(['company_id', 'kind', 'status']);
        });

        Schema::create('knit_sample_plan_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('plan_id')->constrained('knit_sample_plans')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order');
            $table->foreignUlid('buyer_id')->nullable()->constrained('buyers')->nullOnDelete();
            $table->foreignUlid('style_id')->nullable()->constrained('merch_styles')->nullOnDelete();
            $table->foreignUlid('order_id')->nullable()->constrained('merch_orders')->nullOnDelete();
            $table->string('style_text', 80)->nullable();
            $table->foreignUlid('fabric_type_id')->nullable()->constrained('fabric_types')->nullOnDelete();
            $table->foreignUlid('colour_id')->nullable()->constrained('colours')->nullOnDelete();
            $table->string('colour_name', 80)->nullable();
            $table->string('yarn_details', 200)->nullable();
            // The registered yarn the store issues against.
            $table->foreignUlid('composition_id')->nullable()->constrained('fiber_compositions')->nullOnDelete();
            $table->foreignUlid('yarn_count_id')->nullable()->constrained('knit_yarn_counts')->nullOnDelete();
            $table->string('colour_group', 40)->nullable();
            $table->string('brand', 80)->nullable();
            $table->decimal('qty', 14, 3);
            $table->string('uom', 16);
            $table->decimal('gsm', 8, 2)->nullable();
            $table->string('finish_dia', 24)->nullable();
            $table->string('machine_dia', 24)->nullable();
            $table->string('gauge', 24)->nullable();
            $table->string('remarks', 300)->nullable();
            $table->timestamps();
        });

        Schema::create('knit_direct_bookings', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('booking_no', 24);
            $table->string('category_code', 16);
            $table->foreignUlid('buyer_id')->constrained('buyers')->restrictOnDelete();
            $table->foreignUlid('style_id')->nullable()->constrained('merch_styles')->nullOnDelete();
            $table->foreignUlid('order_id')->constrained('merch_orders')->restrictOnDelete();
            $table->date('prog_date');
            $table->text('remarks')->nullable();
            // DRAFT, SUBMITTED, APPROVED, CANCELLED.
            $table->string('status', 12)->default('DRAFT');
            $table->timestamp('decided_at')->nullable();
            $table->foreignUlid('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'booking_no']);
            $table->index(['company_id', 'status', 'prog_date']);
        });

        Schema::create('knit_direct_booking_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('booking_id')->constrained('knit_direct_bookings')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order');
            $table->foreignUlid('fabric_type_id')->nullable()->constrained('fabric_types')->nullOnDelete();
            $table->string('fabric', 160)->nullable();
            $table->foreignUlid('colour_id')->nullable()->constrained('colours')->nullOnDelete();
            $table->string('colour_name', 80)->nullable();
            $table->decimal('gsm', 8, 2)->nullable();
            $table->decimal('finish_dia', 8, 2)->nullable();
            $table->string('dia_unit', 4)->nullable();
            $table->string('width_type', 4)->nullable();
            $table->decimal('qty', 14, 3);
            $table->string('uom', 16);
            $table->date('required_on')->nullable();
            $table->string('remarks', 300)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['knit_direct_booking_lines', 'knit_direct_bookings', 'knit_sample_plan_lines', 'knit_sample_plans'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Work Study → Costing (AnnotechERP 14/05), as Brickland keeps it:
 *
 * - `ws_cm_rates`: Spec-Wise CM Rate — one CM per item specification per
 *   booking month, with the order quantity, budget expense and dates it was
 *   quoted on.
 * - `ws_oh_sections` / `ws_oh_heads`: Overhead Cost Allocation → Section Cost
 *   Heads — the sections (Garments, Cutting … Head Office) and the particulars
 *   each is costed against.
 * - `ws_oh_costs`: a cost head's month — what it cost, the working days, and
 *   how the money is re-arranged across the six production columns the
 *   printed sheet carries.
 * - `ws_line_costings`: Daily Line Cost Sheet → Order Config Data — the SMV,
 *   FOB and per-piece rates an order on a floor is costed at for one day.
 * - `ws_line_productions`: Line Wise Prod — what each line produced that day,
 *   split by overtime tier, with the man-minutes of overtime behind it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ws_cm_rates', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('spec_id')->constrained('ws_specs')->restrictOnDelete();
            $table->foreignUlid('buyer_id')->nullable()->constrained('buyers')->nullOnDelete();
            $table->char('booking_month', 7);
            $table->unsignedInteger('order_qty');
            $table->decimal('cm_per_dozen', 12, 4);
            $table->decimal('budget_exp_pct', 8, 2)->nullable();
            $table->unsignedSmallInteger('lead_time_days')->nullable();
            $table->date('confirm_date')->nullable();
            $table->date('ship_date')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'spec_id', 'booking_month']);
        });

        Schema::create('ws_oh_sections', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('name_bn', 120)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            // A production section the sheet's columns write into ("Allocation target").
            $table->boolean('is_target')->default(false);
            $table->timestamps();
            $table->unique(['company_id', 'name']);
        });

        Schema::create('ws_oh_heads', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('section_id')->constrained('ws_oh_sections')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('name_bn', 160)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('ws_oh_costs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->char('period', 7);
            $table->foreignUlid('head_id')->constrained('ws_oh_heads')->restrictOnDelete();
            $table->decimal('monthly_expense', 14, 2);
            // Left blank, the sheet reads the working days of the line above.
            $table->unsignedSmallInteger('work_days')->nullable();
            foreach (['cutting', 'sewing', 'knitting', 'dyeing', 'dyeing_finishing', 'printing'] as $column) {
                $table->decimal($column, 14, 2)->nullable();
            }
            $table->timestamps();
            $table->unique(['company_id', 'period', 'head_id']);
        });

        Schema::create('ws_line_costings', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('floor_id')->constrained('floors')->restrictOnDelete();
            $table->date('cost_date');
            $table->foreignUlid('buyer_id')->nullable()->constrained('buyers')->nullOnDelete();
            $table->string('style_no', 64);
            $table->string('order_no', 64);
            $table->string('gmt_item', 120)->nullable();
            $table->decimal('bgt_as_cm_pct', 8, 2)->nullable();
            $table->decimal('exc_rate', 10, 4)->nullable();
            $table->decimal('smv', 8, 2);
            $table->decimal('fob', 12, 4)->nullable();
            $table->decimal('rate_cut', 12, 4)->nullable();
            $table->decimal('rate_sew', 12, 4)->nullable();
            $table->decimal('rate_fin', 12, 4)->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'floor_id', 'cost_date', 'order_no']);
        });

        Schema::create('ws_line_productions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('floor_id')->constrained('floors')->restrictOnDelete();
            $table->date('prod_date');
            $table->foreignUlid('planning_line_id')->constrained('planning_lines')->restrictOnDelete();
            $table->foreignUlid('buyer_id')->nullable()->constrained('buyers')->nullOnDelete();
            $table->string('order_no', 64)->nullable();
            $table->string('item_name', 120)->nullable();
            $table->unsignedSmallInteger('op')->nullable();
            $table->unsignedSmallInteger('aso')->nullable();
            $table->decimal('wh', 5, 2)->nullable();
            $table->unsignedInteger('target')->nullable();
            // Pieces made in general hours and in each overtime tier, and the man-minutes of overtime behind each tier.
            foreach (['prod_g', 'prod_aot', 'prod_eot', 'prod_2ot', 'prod_5ot'] as $column) {
                $table->unsignedInteger($column)->default(0);
            }
            foreach (['manot_aot', 'manot_eot', 'manot_2ot', 'manot_5ot'] as $column) {
                $table->decimal($column, 10, 2)->default(0);
            }
            $table->timestamps();
            $table->unique(['company_id', 'floor_id', 'prod_date', 'planning_line_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ws_line_productions');
        Schema::dropIfExists('ws_line_costings');
        Schema::dropIfExists('ws_oh_costs');
        Schema::dropIfExists('ws_oh_heads');
        Schema::dropIfExists('ws_oh_sections');
        Schema::dropIfExists('ws_cm_rates');
    }
};

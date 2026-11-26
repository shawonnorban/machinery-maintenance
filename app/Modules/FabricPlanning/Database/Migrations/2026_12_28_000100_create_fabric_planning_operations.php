<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Fabric Requirement → Operations (AnnotechERP 13/02), as Brickland keeps them:
 *
 * - `fp_status_groups`: the six constraint families (Critical Date, Capacity,
 *   Lead Time, Critical Path, BP Load, BP Performance), fixed.
 * - `fp_status_areas`: what each family measures and its weighting; the code
 *   is fixed once made — the scoring engine reads an area by it.
 * - `fp_status_rules`: the score bands of a family, their name and colour.
 * - `fp_holidays`: the days a section of a unit does not run.
 * - `fp_machine_capacities`: what a dye machine can take in a week.
 * - `fp_batch_plans` / `fp_batch_plan_lines`: an order's fabric gathered into
 *   batches, placed in a week and on a machine.
 * - `fp_schedule_plans` / `fp_schedule_batches`: scheduling boards and the
 *   batches placed on them, in running order.
 *
 * A week is the Saturday it starts on; Brickland keeps no week master.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fp_status_groups', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->unsignedSmallInteger('sl');
            $table->string('code', 16);
            $table->string('name', 80);
            $table->timestamps();
            $table->unique(['company_id', 'sl']);
        });

        Schema::create('fp_status_areas', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('group_id')->constrained('fp_status_groups')->cascadeOnDelete();
            $table->unsignedSmallInteger('sl');
            $table->string('code', 24);
            $table->string('name', 160);
            $table->decimal('wt_factor', 8, 4);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code']);
        });
        Schema::table('fp_status_areas', function (Blueprint $table): void {
            $table->foreignUlid('parent_id')->nullable()->after('group_id')->constrained('fp_status_areas')->restrictOnDelete();
        });

        Schema::create('fp_status_rules', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('group_id')->constrained('fp_status_groups')->cascadeOnDelete();
            $table->decimal('score_from', 10, 4);
            $table->decimal('score_to', 10, 4);
            $table->string('status_code', 24);
            $table->string('status_desc', 160);
            $table->string('color_code', 7);
            $table->decimal('green_rank', 5, 4);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['group_id', 'status_code']);
        });

        Schema::create('fp_holidays', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            // Blank: every section of the unit.
            $table->foreignUlid('section_id')->nullable()->constrained('departments')->cascadeOnDelete();
            $table->foreignUlid('factory_id')->constrained('factories')->restrictOnDelete();
            $table->date('day');
            // WEEKLY_OFF | GOVT_HOLIDAY | COMPANY_HOLIDAY
            $table->string('day_type', 16);
            $table->string('name', 120)->nullable();
            $table->timestamps();
            $table->index(['company_id', 'day']);
        });

        Schema::create('fp_machine_capacities', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('dye_machine_id')->constrained('dye_machines')->restrictOnDelete();
            $table->date('week_start');
            $table->decimal('capacity_kg', 12, 2);
            $table->unsignedSmallInteger('working_days');
            $table->unsignedSmallInteger('holidays');
            $table->decimal('max_load_pct', 6, 2);
            $table->decimal('capacity_hours', 8, 2);
            $table->decimal('avg_batch_qty', 12, 2)->nullable();
            $table->timestamps();
            $table->unique(['dye_machine_id', 'week_start']);
        });

        Schema::create('fp_batch_plans', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('bp_no', 16);
            $table->date('week_start');
            $table->foreignUlid('buyer_id')->nullable()->constrained('buyers')->nullOnDelete();
            $table->string('style_job', 120)->nullable();
            $table->string('order_no', 64)->nullable();
            $table->string('order_cat', 64)->nullable();
            // SOLID | MELANGE | YARN_DYED | AOP
            $table->string('colour_type', 12)->nullable();
            $table->foreignUlid('colour_id')->nullable()->constrained('colours')->nullOnDelete();
            $table->foreignUlid('colour_group_id')->nullable()->constrained('dye_colour_groups')->nullOnDelete();
            // EXHAUST | COLD_PAD_BATCH | CONTINUOUS | PIGMENT
            $table->string('dyeing_method', 16);
            $table->foreignUlid('dye_machine_id')->nullable()->constrained('dye_machines')->nullOnDelete();
            $table->date('shipment_date')->nullable();
            $table->date('booking_confirm_date')->nullable();
            $table->decimal('production_hours', 8, 2)->nullable();
            $table->decimal('pending_qty', 14, 2)->nullable();
            $table->unsignedSmallInteger('batches');
            $table->decimal('bp_qty', 14, 2);
            // DRAFT | PLANNED | SCHEDULED | RELEASED | CANCELLED
            $table->string('status', 10);
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'bp_no']);
            $table->index(['company_id', 'week_start']);
        });

        Schema::create('fp_batch_plan_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('batch_plan_id')->constrained('fp_batch_plans')->cascadeOnDelete();
            $table->string('fabric', 240);
            $table->decimal('grey_req', 14, 4)->default(0);
            $table->decimal('bp_plus', 14, 4)->default(0);
            $table->decimal('bp_minus', 14, 4)->default(0);
            $table->decimal('bp_qty', 14, 4);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('fp_schedule_plans', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('plan_no', 16);
            $table->string('name', 120);
            // Blank: all parties.
            $table->foreignUlid('party_id')->nullable()->constrained('companies')->nullOnDelete();
            // SHIPMENT | BOOKING
            $table->string('basis', 10);
            // DAY | WEEK | MONTH
            $table->string('scale', 8);
            $table->decimal('cell_width', 6, 2);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'plan_no']);
        });

        Schema::create('fp_schedule_batches', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('schedule_plan_id')->constrained('fp_schedule_plans')->cascadeOnDelete();
            $table->foreignUlid('batch_plan_id')->constrained('fp_batch_plans')->cascadeOnDelete();
            $table->foreignUlid('dye_machine_id')->nullable()->constrained('dye_machines')->nullOnDelete();
            $table->date('planned_start')->nullable();
            $table->date('planned_end')->nullable();
            $table->unsignedSmallInteger('sequence');
            $table->timestamps();
            $table->unique(['schedule_plan_id', 'batch_plan_id']);
        });
    }

    public function down(): void
    {
        foreach (['fp_schedule_batches', 'fp_schedule_plans', 'fp_batch_plan_lines', 'fp_batch_plans', 'fp_machine_capacities', 'fp_holidays', 'fp_status_rules', 'fp_status_areas', 'fp_status_groups'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};

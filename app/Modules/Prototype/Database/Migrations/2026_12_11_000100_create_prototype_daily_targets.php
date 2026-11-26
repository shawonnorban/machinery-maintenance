<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prototype Desk → Sample Room Daily Target (AnnotechERP 07/03): one day
 * sheet per date with its capacity and a target per sample line, and the
 * daily, weekly or monthly target plans the day sheets are read against.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proto_day_sheets', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->date('target_date');
            $table->string('sample_room', 120)->nullable();
            $table->string('shift', 10)->nullable();
            $table->foreignUlid('unit_company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignUlid('supervisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('state', 12)->default('DRAFT');
            $table->unsignedInteger('total_manpower')->default(0);
            $table->unsignedInteger('available_manpower')->default(0);
            $table->decimal('shift_hours', 5, 2)->default(8);
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'target_date']);
        });

        Schema::create('proto_day_sheet_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('day_sheet_id')->constrained('proto_day_sheets')->cascadeOnDelete();
            $table->foreignUlid('planning_line_id')->nullable()->constrained('planning_lines')->nullOnDelete();
            $table->foreignUlid('sample_id')->nullable()->constrained('proto_samples')->nullOnDelete();
            $table->foreignUlid('sample_type_id')->nullable()->constrained('sample_types')->nullOnDelete();
            $table->foreignUlid('order_id')->nullable()->constrained('merch_orders')->nullOnDelete();
            $table->unsignedInteger('target')->default(0);
            $table->decimal('wh', 5, 2)->nullable();
            $table->unsignedInteger('manpower')->default(0);
            $table->decimal('target_hrs', 7, 2)->nullable();
            $table->unsignedInteger('actual')->nullable();
            $table->decimal('actual_hrs', 7, 2)->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('team', 120)->nullable();
            $table->string('priority', 10)->nullable();
            $table->string('remarks', 500)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(1);
            $table->timestamps();
        });

        Schema::create('proto_target_plans', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('plan_no', 24);
            $table->string('period', 10);
            $table->date('period_start');
            $table->date('period_end');
            $table->string('sample_room', 120)->nullable();
            $table->string('team', 120)->nullable();
            $table->foreignUlid('supervisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('sample_type_id')->nullable()->constrained('sample_types')->nullOnDelete();
            $table->unsignedInteger('target_qty')->default(0);
            $table->unsignedInteger('available_manpower')->default(0);
            $table->decimal('available_hours', 8, 2)->default(0);
            $table->decimal('planned_hours', 8, 2)->default(0);
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'plan_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proto_target_plans');
        Schema::dropIfExists('proto_day_sheet_lines');
        Schema::dropIfExists('proto_day_sheets');
    }
};

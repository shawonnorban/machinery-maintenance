<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Work Study → Section Profitability (AnnotechERP 14/08), as Brickland keeps it:
 *
 * - `ws_section_targets`: Section Profit & Loss Setup — what each section is
 *   set to produce a day in a month (in-house and sub-contracted beside it),
 *   the rate it is worth and the working days; Target Income is daily target
 *   × rate × days, worked out on save.
 * - `ws_section_results`: Section Profit & Loss Entry — what each section
 *   earned and produced on a day against its target, and what it cost
 *   (overhead, material, expense). Profit/Loss is derived on read.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ws_section_targets', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->char('period', 7);
            $table->foreignUlid('section_id')->constrained('sections')->restrictOnDelete();
            $table->string('sub_section', 120)->nullable();
            $table->decimal('daily_target', 14, 4);
            $table->decimal('daily_target_inhouse', 14, 4)->nullable();
            $table->decimal('daily_target_subcon', 14, 4)->nullable();
            $table->decimal('avg_rate', 14, 4);
            $table->unsignedSmallInteger('work_days');
            $table->decimal('target_income', 16, 2);
            $table->decimal('oh_calculated', 16, 2)->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'period', 'section_id']);
        });

        Schema::create('ws_section_results', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->date('result_date');
            $table->foreignUlid('section_id')->constrained('sections')->restrictOnDelete();
            $table->decimal('income_target', 16, 2)->nullable();
            $table->decimal('income', 16, 2)->default(0);
            $table->decimal('prod_target', 14, 2)->nullable();
            $table->decimal('prod_qty', 14, 2)->default(0);
            $table->decimal('oh_cost', 16, 2)->default(0);
            $table->decimal('mat_cost', 16, 2)->default(0);
            $table->decimal('expense', 16, 2)->default(0);
            $table->text('comments')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'result_date', 'section_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ws_section_results');
        Schema::dropIfExists('ws_section_targets');
    }
};

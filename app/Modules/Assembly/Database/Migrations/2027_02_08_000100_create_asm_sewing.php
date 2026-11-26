<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Assembly Floor → Sewing (AnnotechERP 20/01): a cut bundle card taken into
 * sewing by scan, onto the line it is going on — once, with the pieces short
 * in the bag and the line's plan (style, SMV, manpower, target) as it stood —
 * and scanned off the line again, what came off read against what went on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asm_bundle_ins', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('bundle_id')->constrained('cut_bundles')->restrictOnDelete();
            $table->date('receive_date');
            $table->string('section', 40)->default('Sewing');
            $table->foreignUlid('floor_id')->constrained('floors')->restrictOnDelete();
            $table->foreignUlid('line_id')->constrained('planning_lines')->restrictOnDelete();
            $table->unsignedInteger('bundle_qty');
            $table->unsignedInteger('short_qty')->default(0);
            $table->unsignedInteger('qty');
            $table->decimal('smv', 8, 2)->nullable();
            $table->unsignedInteger('manpower')->nullable();
            $table->unsignedInteger('daily_target')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique('bundle_id');
            $table->index(['company_id', 'receive_date']);
            $table->index(['company_id', 'line_id']);
        });

        Schema::create('asm_bundle_outs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('bundle_in_id')->constrained('asm_bundle_ins')->restrictOnDelete();
            $table->date('out_date');
            $table->string('mode', 8); // FULL | SHORT
            $table->unsignedInteger('output_qty');
            $table->unsignedInteger('balance_qty')->default(0);
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique('bundle_in_id');
            $table->index(['company_id', 'out_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asm_bundle_outs');
        Schema::dropIfExists('asm_bundle_ins');
    }
};

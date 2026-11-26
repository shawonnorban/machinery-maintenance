<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Work Study → Reports (AnnotechERP 14/02): `ws_report_runs` — Brickland's
 * Preview → New report run: a report run for a unit, buyer, floor, style and
 * production date, with how many rows it found and whether it went to Excel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ws_report_runs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('report_key', 48);
            $table->foreignUlid('factory_id')->nullable()->constrained('factories')->nullOnDelete();
            $table->foreignUlid('buyer_id')->nullable()->constrained('buyers')->nullOnDelete();
            $table->foreignUlid('floor_id')->nullable()->constrained('floors')->nullOnDelete();
            $table->string('order_no', 64)->nullable();
            $table->date('production_date');
            $table->boolean('export_excel')->default(false);
            $table->unsignedInteger('rows_count')->default(0);
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'production_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ws_report_runs');
    }
};

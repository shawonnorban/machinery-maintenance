<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Finance & Ledger → Reports (AnnotechERP 23/07): `acc_report_runs` — each
 * report run on the five report screens (Financial Statement Reports, the two
 * cost centre summaries, Receive Payment, Accounting Misc. Report): the
 * report, the period it covered, the boxes it was narrowed by, how many rows
 * it gave and whether it went to a workbook. The figures themselves are not
 * kept — a report is read from the posted ledger each time it is run.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acc_report_runs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('screen', 40);
            $table->string('report_code', 32);
            $table->date('from_date')->nullable();
            $table->date('to_date')->nullable();
            $table->json('filters');
            $table->foreignUlid('account_id')->nullable()->constrained('acc_accounts')->nullOnDelete();
            $table->foreignUlid('cost_center_id')->nullable()->constrained('acc_cost_centers')->nullOnDelete();
            $table->unsignedInteger('rows')->default(0);
            $table->boolean('export_excel')->default(false);
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'screen', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acc_report_runs');
    }
};

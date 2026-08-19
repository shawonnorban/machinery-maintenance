<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Precomputed KPI snapshots: ERD Section 28, ADR-058, SRS 31 and 45.
 *
 * A dashboard that scans every downtime row on each load cannot hold the
 * latency target, and caching the result does not help the first request or the
 * cold cache after every write. A scheduled job writes one row per scope and
 * period instead.
 *
 * The stored columns are counts, not ratios. Availability for a month is not
 * the average of thirty daily availabilities — a day the factory did not run
 * carries no weight, and averaging percentages silently gives it the same
 * weight as a full production day. Summing minutes and failures and dividing
 * once at the end is the only way a week, a month and a quarter agree with each
 * other.
 *
 * Beyond the ERD column list this table also stores the counts behind each mean
 * (repair, response, arrival, PM, work order). Without them a stored mean
 * cannot be re-aggregated into a longer period, which is the whole purpose of
 * the table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kpi_snapshots', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('factory_id')->nullable()->constrained('factories')->cascadeOnDelete();
            $table->foreignUlid('asset_id')->nullable()->constrained('assets')->cascadeOnDelete();

            $table->string('scope_type', 16);  // COMPANY | FACTORY | LINE | ASSET
            $table->string('period_type', 8);  // DAY | WEEK | MONTH
            $table->date('period_start');
            $table->date('period_end');

            // Additive components. Every ratio below is derived from these, and
            // a longer period is their sum.
            $table->unsignedBigInteger('scheduled_operating_minutes')->default(0);
            $table->unsignedBigInteger('downtime_minutes')->default(0);
            $table->unsignedBigInteger('unplanned_downtime_minutes')->default(0);
            $table->unsignedBigInteger('counted_downtime_minutes')->default(0);
            $table->unsignedInteger('failure_count')->default(0);
            $table->unsignedInteger('repair_count')->default(0);
            $table->unsignedBigInteger('repair_minutes_total')->default(0);
            $table->unsignedInteger('response_count')->default(0);
            $table->unsignedBigInteger('response_minutes_total')->default(0);
            $table->unsignedInteger('arrival_count')->default(0);
            $table->unsignedBigInteger('arrival_minutes_total')->default(0);
            $table->unsignedInteger('pm_due_count')->default(0);
            $table->unsignedInteger('pm_on_time_count')->default(0);
            $table->unsignedInteger('work_order_scheduled_count')->default(0);
            $table->unsignedInteger('work_order_closed_count')->default(0);

            // Derived and stored for reads that want one row and no arithmetic.
            // Nullable because a zero denominator has no answer (SRS 31.2
            // rule 2), and 0 would read as "fails constantly".
            $table->decimal('availability_percent', 6, 2)->nullable();
            $table->decimal('mtbf_minutes', 12, 1)->nullable();
            $table->decimal('mttr_minutes', 12, 1)->nullable();
            $table->decimal('pm_compliance_percent', 6, 2)->nullable();

            $table->unsignedSmallInteger('calculation_version');
            $table->timestamp('computed_at');
            $table->timestamps();

            // The version is part of the key: a definition change backfills a
            // new version beside the old one so a figure already reported to a
            // buyer does not change under them (ADR-058).
            $table->unique(
                ['company_id', 'scope_type', 'factory_id', 'asset_id', 'period_type', 'period_start', 'calculation_version'],
                'kpi_snapshots_scope_period_unique',
            );

            $table->index(['company_id', 'period_type', 'period_start'], 'kpi_snapshots_period_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_snapshots');
    }
};

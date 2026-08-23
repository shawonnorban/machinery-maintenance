<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Maintenance labour has no cost of its own.
 *
 * Technicians and mechanics are company employees on a monthly salary. Their
 * time is already paid for whether they spend it on a dyeing machine or
 * standing by, so charging an hourly rate against a work order invents a
 * number that no ledger anywhere in the business agrees with, and makes cost
 * per machine look higher on the lines that happen to break more.
 *
 * What is kept is the time itself — who worked on what, and for how long —
 * because that answers workload and technician performance. What goes is every
 * trace of money attached to it: the rate grades, the rates, and the derived
 * cost rows they produced.
 *
 * Parts, vendor invoices and external service charges are unaffected. Those are
 * real money leaving the business and are recorded as cost entries in their own
 * right.
 *
 * Every step is guarded by an existence check. Not defensiveness for its own
 * sake: these columns were declared inconsistently — some with a foreign key,
 * some without — so a blind drop succeeds on one database and fails halfway
 * through on another, leaving the schema in a state neither version expects.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->dropForeignKeyIfPresent('work_order_labor_entries', 'labor_grade_id');
        $this->dropForeignKeyIfPresent('work_order_labor_entries', 'vendor_id');

        $this->dropColumnsIfPresent('work_order_labor_entries', [
            'labor_grade_id', 'vendor_id', 'labor_category', 'hourly_rate',
            'currency', 'exchange_rate', 'amount', 'base_amount',
        ]);

        $this->dropForeignKeyIfPresent('technicians', 'labor_grade_id');
        $this->dropColumnsIfPresent('technicians', ['labor_grade_id']);

        if (! Schema::hasColumn('technicians', 'production_line_id')) {
            Schema::table('technicians', function (Blueprint $table): void {
                // What they look after, rather than what they cost: a dyeing
                // technician covers the dyeing department, and where a factory
                // assigns people line by line, the line is named too.
                $table->foreignUlid('production_line_id')->nullable()->after('department_id')
                    ->constrained('production_lines')->nullOnDelete();
            });
        }

        $this->dropColumnsIfPresent('work_orders', ['estimated_labor_cost', 'actual_labor_cost']);

        Schema::dropIfExists('labor_rate_grades');
    }

    /**
     * Rebuilds the shape, not the figures.
     *
     * The rates and the amounts derived from them are gone for good, which is
     * the point: they were never anybody's real cost.
     */
    public function down(): void
    {
        Schema::create('labor_rate_grades', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('factory_id')->nullable()->constrained('factories')->nullOnDelete();
            $table->string('name');
            $table->string('code', 32);
            $table->decimal('standard_hourly_rate', 18, 4)->default(0);
            $table->decimal('overtime_multiplier', 9, 4)->default(2);
            $table->char('currency', 3)->default('BDT');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code', 'effective_from'], 'labor_grades_unique');
            $table->index(['company_id', 'active'], 'labor_grades_active_idx');
        });

        Schema::table('technicians', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('production_line_id');

            $table->foreignUlid('labor_grade_id')->nullable()->constrained('labor_rate_grades')->nullOnDelete();
        });

        Schema::table('work_order_labor_entries', function (Blueprint $table): void {
            $table->string('labor_category', 24)->default('REGULAR');
            $table->ulid('labor_grade_id')->nullable();
            $table->ulid('vendor_id')->nullable();
            $table->decimal('hourly_rate', 18, 4)->default(0);
            $table->char('currency', 3)->default('BDT');
            $table->decimal('exchange_rate', 18, 8)->default(1);
            $table->decimal('amount', 18, 4)->default(0);
            $table->decimal('base_amount', 18, 4)->default(0);
        });

        Schema::table('work_orders', function (Blueprint $table): void {
            $table->decimal('estimated_labor_cost', 18, 4)->default(0);
            $table->decimal('actual_labor_cost', 18, 4)->default(0);
        });
    }

    private function dropForeignKeyIfPresent(string $table, string $column): void
    {
        $constraint = DB::selectOne(
            'SELECT CONSTRAINT_NAME AS name
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
               AND REFERENCED_TABLE_NAME IS NOT NULL',
            [$table, $column],
        );

        if ($constraint !== null) {
            Schema::table($table, fn (Blueprint $t) => $t->dropForeign($constraint->name));
        }
    }

    /**
     * @param  list<string>  $columns
     */
    private function dropColumnsIfPresent(string $table, array $columns): void
    {
        $present = array_values(array_filter(
            $columns,
            fn (string $column) => Schema::hasColumn($table, $column),
        ));

        if ($present !== []) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn($present));
        }
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Costing & Budget → Budget vs Actual Costing (AnnotechERP 06/09): what
 * production actually spent, per cost head and per piece, recorded against
 * the order's budget.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merch_budgets', function (Blueprint $table): void {
            $table->json('actual_costs')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('merch_budgets', function (Blueprint $table): void {
            $table->dropColumn('actual_costs');
        });
    }
};

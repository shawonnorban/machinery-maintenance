<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Costing & Budget → Budget Sheet Workflow (AnnotechERP 06/09): the approval
 * a budget sheet waits for before it is locked for costing, and each step of
 * that route — approved, or unlocked again — with who took it and why.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merch_budgets', function (Blueprint $table): void {
            $table->timestamp('approved_at')->nullable();
            $table->ulid('approved_by')->nullable();
        });

        Schema::create('merch_budget_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('budget_id')->constrained('merch_budgets')->cascadeOnDelete();
            $table->string('action', 16);
            $table->text('note')->nullable();
            $table->ulid('user_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merch_budget_events');
        Schema::table('merch_budgets', function (Blueprint $table): void {
            $table->dropColumn(['approved_at', 'approved_by']);
        });
    }
};

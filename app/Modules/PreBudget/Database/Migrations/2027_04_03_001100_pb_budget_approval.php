<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pre-Budget step 6 (AnnotechERP 33/34): Finalize sends a budget through the
 * Approval Center (Merchandising Head → CFO → MD, user 2026-11-19). The
 * budget keeps the engine's approval status and request, when it was sent
 * and by whom, when it was approved and by whom, and — once a revision
 * (R1, R2 …) replaces it — when it was revised.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pb_budgets', function (Blueprint $table): void {
            $table->string('approval_status', 16)->nullable()->after('status');
            $table->ulid('approval_request_id')->nullable()->after('approval_status');
            $table->timestamp('submitted_at')->nullable();
            $table->foreignUlid('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignUlid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revised_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('pb_budgets', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('submitted_by');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['approval_status', 'approval_request_id', 'submitted_at', 'approved_at', 'revised_at']);
        });
    }
};

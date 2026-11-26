<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A budget cancelled by its order (user, 2026-11-24): when an order's
 * Quantity, PAC or FOB changes after its budget was sent for approval or
 * approved, or the order is dropped or cancelled, that budget is CANCELLED —
 * kept as it was, with why and by whom — and budgeted again as its next
 * revision.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pb_budgets', function (Blueprint $table): void {
            $table->timestamp('cancelled_at')->nullable()->after('revised_at');
            $table->foreignUlid('cancelled_by')->nullable()->after('cancelled_at')->constrained('users')->nullOnDelete();
            $table->text('cancel_reason')->nullable()->after('cancelled_by');
        });
    }

    public function down(): void
    {
        Schema::table('pb_budgets', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn(['cancelled_at', 'cancel_reason']);
        });
    }
};

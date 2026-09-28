<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Why a company was suspended, and by whom (SRS 5.4, 40).
 *
 * The status column already existed and nothing read it, so suspending a
 * company deleted its sessions and stopped nothing — the owner signed straight
 * back in. Enforcement needs somewhere to put the answer to the only question
 * the customer will ask, which is "why", so the reason is stored rather than
 * left in the platform operator's memory.
 *
 * Required in practice, not by the column: the screen refuses to suspend
 * without one. Nullable here because every existing row predates the field and
 * backfilling a reason nobody gave would be inventing one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->string('suspension_reason', 500)->nullable()->after('status');
            $table->timestamp('suspended_at')->nullable()->after('suspension_reason');
            $table->foreignUlid('suspended_by')->nullable()->after('suspended_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('suspended_by');
            $table->dropColumn(['suspension_reason', 'suspended_at']);
        });
    }
};

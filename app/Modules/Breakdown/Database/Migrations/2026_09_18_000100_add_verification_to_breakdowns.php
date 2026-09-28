<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Line Chief's own sign-off, ahead of the Engineer/Manager's final approval
 * (SRS 15's extended lifecycle: REPAIRED/PRODUCTION_RESUMED -> VERIFIED ->
 * PENDING_APPROVAL -> CLOSED). Recorded the same way `closed_by`/`closed_at`
 * already are — no FK constraint on the actor column, matching every other
 * "who did this" column on this table (`acknowledged_by`, `closed_by`,
 * `cancelled_by`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('breakdowns', function (Blueprint $table): void {
            $table->foreignUlid('verified_by')->nullable()->after('production_resumed_at');
            $table->dateTime('verified_at', 3)->nullable()->after('verified_by');
        });
    }

    public function down(): void
    {
        Schema::table('breakdowns', function (Blueprint $table): void {
            $table->dropColumn(['verified_by', 'verified_at']);
        });
    }
};

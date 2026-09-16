<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A machine breaks in a way the failure/reason catalogs don't have a code
 * for yet often enough that forcing the closest wrong match was worse than
 * leaving the field free text. Both columns are the exception path, used
 * only when the reporter picked "Other" instead of a catalog entry — the
 * matching *_id column stays null in that case rather than pointing at a
 * code that doesn't actually describe what happened.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('breakdowns', function (Blueprint $table): void {
            $table->string('failure_code_other')->nullable()->after('failure_code_id');
            $table->string('downtime_reason_other')->nullable()->after('downtime_reason_code_id');
        });
    }

    public function down(): void
    {
        Schema::table('breakdowns', function (Blueprint $table): void {
            $table->dropColumn(['failure_code_other', 'downtime_reason_other']);
        });
    }
};

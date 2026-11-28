<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The manager's Review (AnnotechERP 37, phase 3): a tap kept with a Check
 * mark, and a tap the server refused, are looked at once and marked seen —
 * when and by whom.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qt_entries', function (Blueprint $table): void {
            $table->timestamp('review_seen_at')->nullable();
            $table->ulid('review_seen_by')->nullable();
        });
        Schema::table('qt_refused_taps', function (Blueprint $table): void {
            $table->ulid('seen_by')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('qt_entries', function (Blueprint $table): void {
            $table->dropColumn(['review_seen_at', 'review_seen_by']);
        });
        Schema::table('qt_refused_taps', function (Blueprint $table): void {
            $table->dropColumn('seen_by');
        });
    }
};

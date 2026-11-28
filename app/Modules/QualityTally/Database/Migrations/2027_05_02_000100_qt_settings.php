<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quality Tally's own switches, one row per company (user, 2026-11-28):
 *
 *  - phone_approval: on, a new phone waits in Phones until it is approved
 *    before a QC signs in on it; off, a phone is let in on its first sign-in
 *    (blocking still stops it). On by default, as it always was.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qt_settings', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->unique()->constrained('companies')->cascadeOnDelete();
            $table->boolean('phone_approval')->default(true);
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qt_settings');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The QC's phone, phase 2 (AnnotechERP 37 §৫.৬, §৩ 3a):
 *
 *  - qt_entries.review: a tap made with no network that arrived after the
 *    line's target or the section before's pieces were missing is kept, not
 *    refused — the work was done on the floor — and marked for the manager:
 *    NO_TARGET or NONE_READY.
 *  - qt_refused_taps: a tap the server refused for good (closed job, another
 *    line, too old…) is kept here with the reason, so nobody's work vanishes
 *    unseen. One row per tap (the phone may send it again).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qt_entries', function (Blueprint $table): void {
            $table->string('review', 20)->nullable();
        });

        Schema::create('qt_refused_taps', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->uuid('client_uuid');
            $table->foreignUlid('phone_id')->nullable()->constrained('qt_phones')->nullOnDelete();
            $table->foreignUlid('session_id')->nullable()->constrained('qt_sessions')->nullOnDelete();
            $table->foreignUlid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('section', 12)->nullable();
            $table->ulid('line_id')->nullable();
            $table->ulid('job_id')->nullable();
            $table->string('action', 12)->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->text('message');
            $table->json('payload');
            $table->timestamp('seen_at')->nullable(); // a manager has looked at it
            $table->timestamps();
            $table->unique(['company_id', 'client_uuid']);
            $table->index(['company_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qt_refused_taps');
        Schema::table('qt_entries', function (Blueprint $table): void {
            $table->dropColumn('review');
        });
    }
};

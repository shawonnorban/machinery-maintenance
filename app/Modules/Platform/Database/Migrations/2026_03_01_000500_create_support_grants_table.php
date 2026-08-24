<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platform support access to a customer's data (SRS 5.4).
 *
 * "Silent platform access to tenant data is prohibited." Everything in this
 * table exists to make that sentence true rather than aspirational.
 *
 * A grant is the only way a platform administrator sees inside a company, and
 * it is deliberately awkward: it needs a written reason, it expires by itself,
 * it is announced to the customer, and both its beginning and its end are in
 * the audit log. The awkwardness is the feature — support access that is easy
 * and quiet is support access nobody can account for afterwards.
 *
 * The row is kept after it ends. "Who looked at our data, when, and why" is a
 * question a customer is entitled to ask a year later, and a deleted grant
 * makes it unanswerable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_grants', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('granted_to')->constrained('users')->cascadeOnDelete();

            // Free text and required. A dropdown of reasons becomes a habit;
            // a sentence somebody has to write is one they have to mean.
            $table->string('reason', 500);

            $table->timestamp('starts_at');

            // Time-boxed by construction. There is no "until revoked" option,
            // because that is how a support grant becomes a standing account.
            $table->timestamp('expires_at');

            // Set when it is handed back early, which is the normal case: the
            // support call ends before the clock does.
            $table->timestamp('ended_at')->nullable();
            $table->foreignUlid('ended_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['company_id', 'expires_at'], 'support_grants_company_index');
            $table->index(['granted_to', 'ended_at'], 'support_grants_holder_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_grants');
    }
};

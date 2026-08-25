<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The addresses a customer reaches their system on.
 *
 * A table rather than a column on companies, because a customer commonly has
 * two at once: the subdomain we give them on day one, which keeps working, and
 * the address on their own domain that they add later and point their staff at.
 * Cutting one over to the other is then a change of which row is primary
 * instead of an edit that breaks every bookmark in the building.
 *
 * Nothing here is trusted until verified_at is set. A row saying
 * maintenance.some-other-company.com is a claim, and honouring an unverified
 * claim would let one customer put their name on an address they do not own
 * and collect another company's sign-ins.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_domains', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();

            // Unique across every customer, and lowercased on the way in.
            // Hostnames are case-insensitive, so without that two rows could
            // differ only in case and both claim the same address.
            $table->string('host', 255)->unique();

            // SUBDOMAIN is on a host we control and needs no proof. CUSTOM is
            // the customer's own domain and needs the TXT record.
            $table->string('kind', 16);

            $table->string('verification_token', 64);
            $table->timestamp('verified_at')->nullable();

            // Which address the customer is told to use. Links sent out by the
            // system use this one.
            $table->boolean('is_primary')->default(false);

            $table->timestamps();

            $table->index(['company_id', 'is_primary']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_domains');
    }
};

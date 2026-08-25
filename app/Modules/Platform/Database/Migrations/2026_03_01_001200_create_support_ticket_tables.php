<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A customer asking the platform something, in writing, with a reply thread.
 *
 * Distinct from support_grants on purpose. A grant is platform staff entering
 * a customer's account to fix something — access to their data. A ticket is
 * the opposite direction: a customer reaching the platform, and nobody's data
 * is touched by the asking. Merging the two into one table would have made
 * "who can see this" a single, harder question instead of two easy ones.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('opened_by')->constrained('users')->cascadeOnDelete();

            // Nullable: a ticket exists the moment it is opened, and assigning
            // it to somebody is a separate act platform staff take afterwards.
            $table->foreignUlid('assigned_to')->nullable()->constrained('users')->nullOnDelete();

            $table->string('subject', 255);
            $table->string('status', 16)->default('OPEN');

            // For the inbox to sort by without a join to the messages table.
            $table->timestamp('last_message_at');

            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();

            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index('status');
        });

        // Append-only, matching every other thread in the product (ADR):
        // corrections are a new message, not an edit to an old one — a
        // support conversation edited after the fact is not a record of what
        // was actually said.
        Schema::create('support_ticket_messages', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            $table->foreignUlid('ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            // Present directly rather than reached through ticket_id, because
            // BelongsToTenant filters on the column being on the row itself —
            // reaching it through a join is not what the tenant scope does.
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('author_id')->constrained('users')->cascadeOnDelete();

            // Kept as a plain column rather than derived from is_platform_admin
            // at read time: a platform administrator's account status can
            // change after the message was sent, and the thread should still
            // say who was speaking as what, at the time.
            $table->boolean('author_is_platform');

            $table->text('body');

            $table->timestamp('created_at');

            $table->index('ticket_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_ticket_messages');
        Schema::dropIfExists('support_tickets');
    }
};

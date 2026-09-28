<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Priority/category, SLA timestamps, and internal (platform-only) notes —
 * the four commercial-desk additions to the bare open/reply/status thread.
 *
 * Every existing ticket gets `MEDIUM`/`GENERAL` for free from the column
 * defaults; the SLA due-date columns stay null for them (the breach checker
 * skips a null due-date rather than needing a backfill).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table): void {
            $table->string('priority', 16)->default('MEDIUM')->after('status');
            $table->string('category', 32)->default('GENERAL')->after('priority');

            $table->timestamp('first_response_due_at')->nullable()->after('last_message_at');
            $table->timestamp('resolution_due_at')->nullable()->after('first_response_due_at');
            $table->timestamp('first_responded_at')->nullable()->after('resolution_due_at');

            // Set the first (and only) time each breach is notified, so the
            // fifteen-minute check never re-alerts platform staff about the
            // same missed deadline on every run.
            $table->timestamp('first_response_breach_notified_at')->nullable()->after('first_responded_at');
            $table->timestamp('resolution_breach_notified_at')->nullable()->after('first_response_breach_notified_at');
        });

        Schema::table('support_ticket_messages', function (Blueprint $table): void {
            // Platform staff talking to each other on the ticket, never sent
            // to the customer and never counted toward first response.
            $table->boolean('is_internal')->default(false)->after('author_is_platform');
        });
    }

    public function down(): void
    {
        Schema::table('support_ticket_messages', function (Blueprint $table): void {
            $table->dropColumn('is_internal');
        });

        Schema::table('support_tickets', function (Blueprint $table): void {
            $table->dropColumn([
                'priority', 'category', 'first_response_due_at', 'resolution_due_at',
                'first_responded_at', 'first_response_breach_notified_at', 'resolution_breach_notified_at',
            ]);
        });
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fabric Booking → Approved Fabric Booking List (AnnotechERP 06/11): a bulk
 * booking is sent through the Approval Center, so it carries the engine's
 * `approval_status` / `approval_request_id`; Brickland's list also shows the
 * booking's SP# and whether, and when, it was mailed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merch_fabric_bookings', function (Blueprint $table): void {
            $table->string('approval_status', 16)->nullable()->after('status');
            $table->ulid('approval_request_id')->nullable()->after('approval_status');
            $table->string('sp_no', 32)->nullable()->after('item_model');
            $table->boolean('send_mail')->default(false)->after('approved_by');
            $table->timestamp('mail_sent_at')->nullable()->after('send_mail');
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('merch_fabric_bookings', function (Blueprint $table): void {
            $table->dropIndex(['company_id', 'status']);
            $table->dropColumn(['approval_status', 'approval_request_id', 'sp_no', 'send_mail', 'mail_sent_at']);
        });
    }
};

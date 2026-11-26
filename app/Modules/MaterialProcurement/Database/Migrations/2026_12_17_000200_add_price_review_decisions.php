<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Material Procurement → Price Review & Approval (AnnotechERP 08/05): what the
 * review decides. A quotation keeps the negotiation message last sent to its
 * supplier and the reason it was rejected; a line keeps the quotation
 * approved on it, the follow-up merchandiser it was handed to, and the
 * reason it was rejected outright.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mp_quotations', function (Blueprint $table): void {
            $table->text('negotiation_message')->nullable();
            $table->timestamp('negotiated_at')->nullable();
            $table->text('reject_reason')->nullable();
            $table->foreignUlid('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
        });

        Schema::table('mp_requisition_lines', function (Blueprint $table): void {
            $table->foreignUlid('approved_quotation_id')->nullable()->constrained('mp_quotations')->nullOnDelete();
            $table->foreignUlid('followup_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('reject_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('mp_requisition_lines', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('approved_quotation_id');
            $table->dropConstrainedForeignId('followup_user_id');
            $table->dropConstrainedForeignId('decided_by');
            $table->dropColumn(['decided_at', 'reject_reason']);
        });
        Schema::table('mp_quotations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('decided_by');
            $table->dropColumn(['negotiation_message', 'negotiated_at', 'reject_reason', 'decided_at']);
        });
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * AI File & PO Import → Intake: the Approval Center writes the request an
 * imported file waits on next to its verdict (approval_status).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_intake_files', function (Blueprint $table): void {
            $table->ulid('approval_request_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ai_intake_files', function (Blueprint $table): void {
            $table->dropColumn('approval_request_id');
        });
    }
};

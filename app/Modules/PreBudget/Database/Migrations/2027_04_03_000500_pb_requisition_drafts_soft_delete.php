<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pre-Budget → Draft Requisition: a completed or deleted draft is kept out of
 * sight, not removed, so its number is never given again — a page still open
 * on draft D-1 must not start saving into someone's new D-1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pb_requisition_drafts', function (Blueprint $table): void {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('pb_requisition_drafts', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });
    }
};

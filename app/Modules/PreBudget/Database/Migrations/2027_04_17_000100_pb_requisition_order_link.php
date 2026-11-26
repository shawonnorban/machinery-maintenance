<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Order Summary ↔ Requisition (user, 2026-11-24): a requisition row made from
 * an order (Add Requisition's order picker) keeps which Order Summary row it
 * came from. One order — one row of Order Summary, one style and one JOB No —
 * gets one requisition (its rows: one per part); an order whose requisition
 * is deleted can be picked again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pb_requisitions', function (Blueprint $table): void {
            $table->foreignUlid('os_order_id')->nullable()->after('buyer_id')->constrained('os_orders')->nullOnDelete();
            $table->index(['company_id', 'os_order_id']);
        });
    }

    public function down(): void
    {
        Schema::table('pb_requisitions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('os_order_id');
        });
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Order Summary's instant mail (user, 2026-11-22): when an order's JOB No,
 * PO Number or Shipment ID Number is added or changed, the buyer's team is
 * mailed at once — not only in the edit mail at the set times. The admin may
 * turn it off or choose other columns (null = those three).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('os_mail_settings', function (Blueprint $table): void {
            $table->boolean('instant_enabled')->default(true)->after('mail_service_id');
            $table->json('instant_columns')->nullable()->after('instant_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('os_mail_settings', fn (Blueprint $table) => $table->dropColumn(['instant_enabled', 'instant_columns']));
    }
};

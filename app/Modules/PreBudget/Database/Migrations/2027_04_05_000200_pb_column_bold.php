<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pre-Budget's requisition and Acc Budget (BOM) Column Setup: a column's data may show bold, the same switch as
 * Order Summary's (user, 2026-11-21). Every column starts as it was, not bold.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pb_columns', function (Blueprint $table): void {
            $table->boolean('bold')->default(false)->after('text_tone');
        });
    }

    public function down(): void
    {
        Schema::table('pb_columns', function (Blueprint $table): void {
            $table->dropColumn('bold');
        });
    }
};

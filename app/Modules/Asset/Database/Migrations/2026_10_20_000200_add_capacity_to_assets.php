<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Company Management's Machine Master (AnnotechERP 01/04) shows a machine's
 * rated capacity — kilograms a knitting machine turns out a day, pieces an
 * hour on a sewing machine. Nullable: machinery maintenance never needed it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table): void {
            $table->decimal('capacity', 14, 2)->nullable();
            $table->string('capacity_uom', 16)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('assets', fn (Blueprint $table) => $table->dropColumn(['capacity', 'capacity_uom']));
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which country the vendor's own machinery/brand actually comes from —
 * separate from the vendor's own business address (`address`, already on
 * the table): a Bangladesh-registered distributor for a German dyeing-
 * machine brand has both, and only one of them tells a buyer where spare
 * parts and service expertise are really sourced from.
 *
 * A plain free-text country name, not a 2-letter ISO code — this app has
 * no country-code-to-name lookup anywhere in the UI (Asset's own
 * `country_of_origin` is exactly that and was never wired to a screen for
 * this reason), and Company's own "Country" field on the platform side is
 * already free text for the same reason.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table): void {
            $table->string('country_of_origin', 100)->nullable()->after('address');
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table): void {
            $table->dropColumn('country_of_origin');
        });
    }
};

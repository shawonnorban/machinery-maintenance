<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who to actually contact at a customer, and what their letterhead looks like.
 *
 * None of this was on Company before: the platform could name a customer and
 * bill them, but had nowhere to write down a phone number to call when an
 * invoice bounced, or the country a support call's time zone assumption
 * depends on. All five are nullable — a customer mid-onboarding has none of
 * this yet, and a blank field is not the same fact as a wrong one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->string('email', 255)->nullable()->after('legal_name');
            $table->string('phone', 32)->nullable()->after('email');
            $table->string('country', 100)->nullable()->after('phone');
            $table->string('address', 500)->nullable()->after('country');

            // The path on the public disk, not the file itself: a logo is
            // shown, never downloaded as evidence, so it does not belong in
            // file_attachments alongside scanned photos and vendor documents
            // (SRS 37) — it belongs wherever an <img> tag can reach it.
            $table->string('logo_path', 255)->nullable()->after('address');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn(['email', 'phone', 'country', 'address', 'logo_path']);
        });
    }
};

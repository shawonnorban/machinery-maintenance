<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every module of the company app comes under the platform (user,
 * 2026-11-24): a row now says on OR off. No row = the module's default —
 * on for the modules companies have always had (so existing companies keep
 * everything they see), off for the separately sold ones (Quality Tally). A
 * company onboarded from the platform gets "off" rows for every non-core
 * module, so it starts with the core ones only. `enabled_by` is whoever
 * last switched it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_modules', function (Blueprint $table): void {
            $table->boolean('enabled')->default(true)->after('module_code');
        });
    }

    public function down(): void
    {
        Schema::table('company_modules', fn (Blueprint $table) => $table->dropColumn('enabled'));
    }
};

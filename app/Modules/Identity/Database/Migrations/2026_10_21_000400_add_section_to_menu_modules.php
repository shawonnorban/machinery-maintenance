<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Brickland's sidebar sections (OVERVIEW, SYSTEM ADMINISTRATION, HUMAN
 * RESOURCE …) above the modules (AnnotechERP UI parity). Written by
 * SyncMenus from MenuRegistry; a fact of code, like a menu's url.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_modules', function (Blueprint $table): void {
            $table->string('section', 32)->default('OVERVIEW');
        });
    }

    public function down(): void
    {
        Schema::table('menu_modules', fn (Blueprint $table) => $table->dropColumn('section'));
    }
};

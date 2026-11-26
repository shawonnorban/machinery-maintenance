<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Each company's own copy of the seeded roles (user, 2026-11-22: every role
 * editable). `template` names the seeded role a copy came from;
 * `template_permissions` is that role's permission set as last carried over,
 * so a later change to the template reaches the copy without undoing the
 * company's own edits.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table): void {
            $table->string('template', 64)->nullable()->after('is_system');
            $table->jsonb('template_permissions')->nullable()->after('template');
        });
    }

    public function down(): void
    {
        Schema::table('roles', fn (Blueprint $table) => $table->dropColumn(['template', 'template_permissions']));
    }
};

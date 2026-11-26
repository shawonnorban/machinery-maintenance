<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Department groups (user, 2026-11-23): one group chat per HR department,
 * its people kept in step with HR & Payroll. `system_key` names such a
 * group ("dept:merchandising department"); `synced_out` marks a member the
 * sync took out (moved to another department) — they come back if they
 * return, while someone who left on their own is not pulled back in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chats', function (Blueprint $table): void {
            $table->string('system_key', 160)->nullable()->after('direct_key');
            $table->unique(['company_id', 'system_key']);
        });
        Schema::table('chat_members', function (Blueprint $table): void {
            $table->boolean('synced_out')->default(false)->after('left_at');
        });
    }

    public function down(): void
    {
        Schema::table('chat_members', fn (Blueprint $table) => $table->dropColumn('synced_out'));
        Schema::table('chats', function (Blueprint $table): void {
            $table->dropUnique(['company_id', 'system_key']);
            $table->dropColumn('system_key');
        });
    }
};

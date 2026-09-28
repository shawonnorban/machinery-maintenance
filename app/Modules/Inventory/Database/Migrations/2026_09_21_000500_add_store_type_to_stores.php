<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether a store is an ordinary one, a bonded warehouse, or a sub-store
 * (Master Data spec §5, Shared Systems §3.4).
 *
 * Bonded stock is what customs cares about: moving it to a local store
 * triggers duty. An attribute on the existing store rather than a new
 * entity — the hierarchy above it (factory → warehouse → store → bin) is
 * already how every balance is located.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table): void {
            $table->string('store_type', 16)->default('GENERAL')->after('code');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table): void {
            $table->dropColumn('store_type');
        });
    }
};

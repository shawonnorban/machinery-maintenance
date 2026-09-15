<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One login, one technician.
 *
 * Without this, two `technicians` rows could point at the same `user_id`
 * and whichever one a screen happened to look up would silently own that
 * person's work history. A unique index on a nullable column still allows
 * any number of technicians with no login at all (`user_id` null) — only a
 * login already claimed by another technician is refused.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('technicians', function (Blueprint $table): void {
            $table->unique('user_id', 'technicians_user_unique');
        });
    }

    public function down(): void
    {
        Schema::table('technicians', function (Blueprint $table): void {
            $table->dropUnique('technicians_user_unique');
        });
    }
};

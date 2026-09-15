<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where a non-technician covers the floor from (Line Chief / Section Chief).
 *
 * `technicians` already carries this pair to sort who gets offered a job
 * first (ADR-065). A Line Chief reports breakdowns rather than repairing
 * them and has no technician row at all, so the same two columns are added
 * here rather than inventing a second table for one pair of nullable FKs.
 *
 * Both null means "whole factory" — unrestricted, the same convention
 * `technicians.department_id`/`production_line_id` already use.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignUlid('department_id')->nullable()->after('is_platform_admin')
                ->constrained('departments')->nullOnDelete();
            $table->foreignUlid('production_line_id')->nullable()->after('department_id')
                ->constrained('production_lines')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('production_line_id');
            $table->dropConstrainedForeignId('department_id');
        });
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The modules the platform has switched on for a company (user, 2026-11-24:
 * "a module given from the platform is the one a company sees"). Only the
 * modules LicensedModules names are gated this way; everything else stays
 * open to every company as before. A row present = switched on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_modules', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('module_code', 40);
            $table->foreignUlid('enabled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'module_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_modules');
    }
};

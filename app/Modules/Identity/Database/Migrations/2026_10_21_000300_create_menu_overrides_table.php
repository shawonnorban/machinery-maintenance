<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Access Control → Module Registration and Menu Definition, per company
 * (AnnotechERP 02/01, step C). The menu tree is platform-wide — the screens
 * behind it are the same code for everyone — so a company's renames, order
 * and hidden entries are kept here, over the shared rows, instead of on them:
 * one company hiding Procurement must not hide it for every other company.
 * A null column means "as the code has it".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_overrides', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            // Exactly one of the two is set.
            $table->foreignUlid('menu_module_id')->nullable()->constrained('menu_modules')->cascadeOnDelete();
            $table->foreignUlid('menu_id')->nullable()->constrained('menus')->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->string('name_bn')->nullable();
            $table->text('description')->nullable();
            $table->string('icon', 48)->nullable();
            $table->unsignedInteger('sort_order')->nullable();
            $table->string('security_level', 16)->nullable();
            $table->boolean('open_new_window')->nullable();
            $table->boolean('active')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'menu_module_id']);
            $table->unique(['company_id', 'menu_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_overrides');
    }
};

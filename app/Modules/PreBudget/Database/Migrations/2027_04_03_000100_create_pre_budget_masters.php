<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pre-Budget → Master Data (AnnotechERP 33/34, from Norban; plan 2026-11-19):
 * the module's own short lists — Yarn Type, Wash Type, Additional Process,
 * GSM, Sustainability, Order Type — one table told apart by `list`, and the
 * Accessories Item list (Norban's Item and its look-alike Name merged), with
 * a category and the unit it is usually bought in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pb_list_values', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('list', 32);
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'list', 'name']);
            $table->index(['company_id', 'list', 'sort_order']);
        });

        Schema::create('pb_accessory_items', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('code', 48);
            $table->string('name', 255);
            $table->string('category', 120)->nullable();
            $table->string('uom', 16)->nullable();
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'code']);
            $table->unique(['company_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pb_accessory_items');
        Schema::dropIfExists('pb_list_values');
    }
};

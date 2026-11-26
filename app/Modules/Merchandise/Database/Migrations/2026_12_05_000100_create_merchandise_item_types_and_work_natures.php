<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Merchandise Desk → Master Data (AnnotechERP 06/01): the Item Type Master
 * (T-Shirt, Polo Shirt, Trouser … under each item group) and the Nature of
 * Work Master (Cutting, Printing, Sewing …) the Garment Items step offers.
 * A style item now carries its natures of work as text, several at a time,
 * so the old codes become the names they stood for.
 */
return new class extends Migration
{
    /** The codes a style item carried before the master, and the names they stood for. */
    private const LEGACY = ['SEWING' => 'Sewing', 'SEWING_PRINT' => 'Sewing + Print', 'SEWING_EMBROIDERY' => 'Sewing + Embroidery', 'CUT_MAKE' => 'Cut & Make'];

    public function up(): void
    {
        Schema::create('item_types', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('item_group', 16);
            $table->string('code', 32);
            $table->string('name', 120);
            $table->string('remarks', 255)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code']);
            $table->unique(['company_id', 'item_group', 'name']);
        });

        Schema::create('work_natures', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('code', 32);
            $table->string('name', 120);
            $table->string('remarks', 255)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code']);
            $table->unique(['company_id', 'name']);
        });

        Schema::table('merch_style_items', function (Blueprint $table): void {
            $table->string('nature_of_work', 500)->change();
        });
        foreach (self::LEGACY as $code => $name) {
            DB::table('merch_style_items')->where('nature_of_work', $code)->update(['nature_of_work' => $name]);
        }
    }

    public function down(): void
    {
        foreach (self::LEGACY as $code => $name) {
            DB::table('merch_style_items')->where('nature_of_work', $name)->update(['nature_of_work' => $code]);
        }
        Schema::dropIfExists('work_natures');
        Schema::dropIfExists('item_types');
    }
};

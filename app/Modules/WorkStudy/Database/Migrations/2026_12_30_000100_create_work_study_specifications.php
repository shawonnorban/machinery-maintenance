<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Work Study → Item Specification (AnnotechERP 14/04), as Brickland keeps it:
 *
 * - `ws_item_groups`: the garment item groups (Knit Tops GRP-KNIT …). The
 *   categories (`ws_garment_items`, from 14/01) name their group, and now carry
 *   a code and a line number of their own.
 * - `ws_tech_variations`: a category's technical variations, with the SMV each
 *   order-quantity band attracts.
 * - `ws_specs`: a style's engineered variation — manpower, SMV, efficiency,
 *   the operation bulletin — with its process groups (`ws_spec_processes`)
 *   and the machines it needs (`ws_spec_machines`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ws_item_groups', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('code', 24);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['company_id', 'name']);
            $table->unique(['company_id', 'code']);
        });

        Schema::table('ws_garment_items', function (Blueprint $table): void {
            $table->string('code', 24)->nullable()->after('name');
            $table->unsignedSmallInteger('sl')->default(0)->after('code');
        });

        Schema::create('ws_tech_variations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('garment_item_id')->constrained('ws_garment_items')->restrictOnDelete();
            $table->string('tech_spec', 200);
            $table->decimal('op', 8, 2)->nullable();
            $table->decimal('aso', 8, 2)->nullable();
            $table->decimal('smv', 8, 2)->nullable();
            // The SMV each printed order-quantity band attracts: 1–2000, 2001–5000, 5001–7000, 7001–10000, 10001–15000, 15001+.
            foreach (range(1, 6) as $n) {
                $table->decimal("band{$n}", 8, 2)->nullable();
            }
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('ws_specs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('style_no', 64);
            $table->unsignedSmallInteger('variation_no');
            $table->string('description', 200);
            // FLEECE | PIQUE | SINGLE_JERSEY | TWILL
            $table->string('fabric_class', 16)->nullable();
            // BASIC | SEMI_CRITICAL | CRITICAL | HIGHLY_CRITICAL
            $table->string('criticality', 16)->nullable();
            $table->unsignedSmallInteger('op_manpower');
            $table->unsignedSmallInteger('aso_manpower');
            $table->decimal('sewing_smv', 8, 2);
            $table->decimal('iron_smv', 8, 2)->nullable();
            $table->decimal('poly_smv', 8, 2)->nullable();
            $table->decimal('avg_efficiency', 5, 2)->nullable();
            $table->decimal('pick_efficiency', 5, 2)->nullable();
            $table->decimal('balancing_efficiency', 5, 2)->nullable();
            $table->string('ob_file_path', 500)->nullable();
            $table->boolean('is_smv_artwork')->default(false);
            $table->boolean('is_fusing')->default(false);
            $table->foreignUlid('sample_type_id')->nullable()->constrained('sample_types')->nullOnDelete();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'style_no', 'variation_no']);
        });

        Schema::create('ws_spec_processes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('spec_id')->constrained('ws_specs')->cascadeOnDelete();
            $table->foreignUlid('garment_part_id')->constrained('garment_parts')->restrictOnDelete();
            $table->string('specification', 300)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('ws_spec_machines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('spec_id')->constrained('ws_specs')->cascadeOnDelete();
            // SNLS | OVERLOCK | FLATLOCK | BUTTON_ATTACH | BUTTON_HOLE | BARTACK
            $table->string('machine_type', 16);
            $table->unsignedSmallInteger('requirement');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['ws_spec_machines', 'ws_spec_processes', 'ws_specs', 'ws_tech_variations', 'ws_item_groups'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('ws_garment_items', function (Blueprint $table): void {
            $table->dropColumn(['code', 'sl']);
        });
    }
};

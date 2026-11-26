<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Style Development Sheet → Item/Combo/Model Registration (AnnotechERP 06/05):
 * the garment items a style is made of — a set's pieces, a combo's models —
 * each with its group and type, division, neck and sleeve, names, gender and
 * age, the services on it, its nature of work and IE figures, its pictures,
 * certificate instructions and care label lines. An item may serve more than
 * one style ("For Style(s)#").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merch_style_items', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('style_id')->constrained('merch_styles')->cascadeOnDelete();
            $table->foreignUlid('set_style_id')->nullable()->constrained('merch_styles')->nullOnDelete();
            $table->string('model_no', 64)->nullable();
            $table->string('item_group', 16);
            $table->string('item_type', 32)->nullable();
            $table->string('division', 16);
            $table->string('neck_type', 16)->nullable();
            $table->string('sleeve_type', 16)->nullable();
            $table->string('short_name', 32);
            $table->string('full_name', 500);
            $table->text('description')->nullable();
            $table->string('gender', 16)->nullable();
            $table->string('age_group', 16)->nullable();
            $table->string('status', 16)->default('ACTIVE');
            foreach (['is_solid', 'is_yd', 'is_aop', 'is_gmt_wash', 'is_heat_seal', 'is_print', 'is_embroidery'] as $flag) {
                $table->boolean($flag)->default(false);
            }
            $table->string('nature_of_work', 24);
            $table->string('no_of_mc', 16)->nullable();
            $table->string('exp_prod_hr', 16)->nullable();
            $table->decimal('smv_marketing', 8, 2)->nullable();
            $table->text('tech_spec')->nullable();
            $table->ulid('key_image_id')->nullable();
            $table->ulid('second_image_id')->nullable();
            $table->ulid('item_image_id')->nullable();
            // Four certificate instructions, each a tick and an optional picture.
            $table->json('certificates')->nullable();
            // Care label lines: the garment part and the service on it.
            $table->json('care_labels')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'style_id']);
        });

        Schema::create('merch_style_item_styles', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('style_item_id')->constrained('merch_style_items')->cascadeOnDelete();
            $table->foreignUlid('style_id')->constrained('merch_styles')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['style_item_id', 'style_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merch_style_item_styles');
        Schema::dropIfExists('merch_style_items');
    }
};

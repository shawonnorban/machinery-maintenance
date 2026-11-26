<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Dye House → Lab-Dip (AnnotechERP 17/02): dosing templates — the reusable
 * curves a lab dip and a bulk batch are programmed from, one line per dosed
 * agent in step order — and lab dip recipes, one row per option tried at a
 * shade. Methods and colour groups are Dye House's masters; agents are the
 * item master's dyes and chemicals; buyers, styles, colours and fabric types
 * are Merchandise Desk's; units come from the units-of-measure master by code.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dye_dosing_templates', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('code', 16); // TPL-0001, issued on save
            $table->string('name');
            $table->string('short_name', 64)->nullable();
            $table->foreignUlid('dye_method_id')->constrained('dye_methods');
            $table->foreignUlid('colour_group_id')->constrained('dye_colour_groups');
            $table->string('template_type', 16); // PRE_TREATMENT | DYEING | POST_TREATMENT | FINISHING | WASH
            $table->boolean('is_finalized')->default(false);
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'code'], 'dye_dosing_templates_code_unique');
            $table->unique(['company_id', 'name'], 'dye_dosing_templates_name_unique');
        });

        Schema::create('dye_dosing_template_agents', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('template_id')->constrained('dye_dosing_templates')->cascadeOnDelete();
            $table->unsignedSmallInteger('step_no');
            $table->foreignUlid('dye_method_id')->nullable()->constrained('dye_methods');
            $table->string('phase', 16); // PRE_TREATMENT | DYEING | AFTER_TREATMENT | FINISHING
            $table->string('process', 32)->nullable(); // one of the phase's processes
            $table->foreignUlid('item_id')->constrained('items');
            $table->boolean('is_labdip')->default(true);
            $table->boolean('is_line_break')->default(false);
            $table->decimal('dose', 14, 4)->nullable();
            $table->string('uom', 16)->nullable();
            $table->timestamps();
            $table->unique(['template_id', 'step_no'], 'dye_dosing_agents_step_unique');
        });

        Schema::create('dye_lab_dip_recipes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('recipe_no', 40);
            $table->unsignedSmallInteger('option_no')->default(1);
            $table->foreignUlid('buyer_id')->constrained('buyers');
            $table->foreignUlid('style_id')->nullable()->constrained('merch_styles')->nullOnDelete();
            $table->foreignUlid('colour_id')->constrained('colours');
            $table->foreignUlid('fabric_type_id')->nullable()->constrained('fabric_types')->nullOnDelete();
            $table->foreignUlid('dye_method_id')->nullable()->constrained('dye_methods');
            $table->string('fiber_composition')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'recipe_no', 'option_no'], 'dye_lab_dip_recipes_option_unique');
            $table->index(['company_id', 'buyer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dye_lab_dip_recipes');
        Schema::dropIfExists('dye_dosing_template_agents');
        Schema::dropIfExists('dye_dosing_templates');
    }
};

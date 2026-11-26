<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Merchandise Desk → Master Data, step C (AnnotechERP 06/01): process loss
 * norms, the inquiry rate chart with its revisions per buyer office, trims
 * specification templates, and the T&A building blocks — task groups and
 * tasks, templates with their milestone calendars, and who may work each task.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Dyeing Process Loss Norms: bands read lower-inclusive, higher-exclusive.
        Schema::create('dyeing_loss_bands', function (Blueprint $table): void {
            $this->base($table);
            $table->foreignUlid('fabric_type_id')->nullable()->constrained('fabric_types')->nullOnDelete();
            $table->foreignUlid('fiber_composition_id')->constrained('fiber_compositions')->restrictOnDelete();
            $table->string('color_group', 16);
            $table->decimal('weight_low', 12, 3);
            $table->decimal('weight_high', 12, 3);
            $table->decimal('loss_percent', 5, 2);
            $table->boolean('active')->default(true);
        });

        // Process Loss Quantity Setup (BOOKING) and Cutting Process Loss Setup (CUTTING).
        Schema::create('process_loss_allowances', function (Blueprint $table): void {
            $this->base($table);
            $table->string('stage', 8);
            $table->decimal('qty_low', 14, 2);
            $table->decimal('qty_high', 14, 2);
            $table->decimal('loss_qty', 14, 2)->nullable();
            $table->boolean('add_to_booking')->default(true);
            $table->string('fabric_group', 16)->nullable();
            $table->string('colour_type', 16)->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->index(['company_id', 'stage']);
        });

        Schema::create('rate_lines', function (Blueprint $table): void {
            $this->base($table);
            $table->string('rate_group', 24);
            $table->string('description');
            $table->string('uom', 16)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->unique(['company_id', 'rate_group', 'description']);
        });

        // A rate is never edited: a new effective date adds a revision.
        Schema::create('rate_line_rates', function (Blueprint $table): void {
            $this->base($table);
            $table->foreignUlid('rate_line_id')->constrained('rate_lines')->cascadeOnDelete();
            $table->foreignUlid('buyer_office_id')->constrained('buyer_offices')->cascadeOnDelete();
            $table->decimal('rate', 14, 4);
            $table->string('currency', 3);
            $table->date('effective_from');
            $table->foreignUlid('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unique(['rate_line_id', 'buyer_office_id', 'effective_from']);
        });

        Schema::create('trim_spec_templates', function (Blueprint $table): void {
            $this->base($table);
            $table->string('code', 32);
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->unique(['company_id', 'code']);
        });

        // Where a template applies: an item category, an item, a buyer — any blank means "any".
        Schema::create('trim_spec_item_rules', function (Blueprint $table): void {
            $this->base($table);
            $table->foreignUlid('trim_spec_template_id')->constrained('trim_spec_templates')->cascadeOnDelete();
            $table->string('item_category')->nullable();
            $table->string('item_name')->nullable();
            $table->foreignUlid('buyer_id')->nullable()->constrained('buyers')->cascadeOnDelete();
        });

        // The columns a specification sheet carries.
        Schema::create('trim_spec_controls', function (Blueprint $table): void {
            $this->base($table);
            $table->foreignUlid('trim_spec_template_id')->constrained('trim_spec_templates')->cascadeOnDelete();
            $table->string('name');
            $table->string('control_type', 8);
            $table->string('options')->nullable();
            $table->boolean('required')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
        });

        Schema::create('tna_task_groups', function (Blueprint $table): void {
            $this->base($table);
            $table->string('code', 16);
            $table->string('name');
            $table->string('name_bn')->nullable();
            $table->boolean('active')->default(true);
            $table->unique(['company_id', 'code']);
        });

        Schema::create('tna_tasks', function (Blueprint $table): void {
            $this->base($table);
            $table->foreignUlid('tna_task_group_id')->nullable()->constrained('tna_task_groups')->nullOnDelete();
            $table->string('code', 16);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('name');
            $table->string('name_bn')->nullable();
            $table->string('source_module', 32)->nullable();
            $table->smallInteger('offset_days')->nullable();
            $table->boolean('is_sample')->default(false);
            $table->boolean('is_start')->default(false);
            $table->boolean('is_end')->default(false);
            $table->boolean('is_production')->default(false);
            $table->boolean('active')->default(true);
            $table->unique(['company_id', 'code']);
        });

        Schema::create('tna_templates', function (Blueprint $table): void {
            $this->base($table);
            $table->string('code', 16);
            $table->string('scope', 8)->default('GENERAL');
            $table->foreignUlid('buyer_id')->nullable()->constrained('buyers')->cascadeOnDelete();
            $table->string('order_type', 12);
            $table->string('gmt_type', 16);
            $table->unsignedSmallInteger('lead_from');
            $table->unsignedSmallInteger('lead_to');
            $table->unsignedSmallInteger('std_days');
            $table->unsignedSmallInteger('before_swing');
            $table->unsignedSmallInteger('after_swing');
            $table->text('description')->nullable();
            $table->unique(['company_id', 'code']);
        });

        // A template's calendar: offsets count back from the shipment date (0 = ex-factory).
        Schema::create('tna_template_tasks', function (Blueprint $table): void {
            $this->base($table);
            $table->foreignUlid('tna_template_id')->constrained('tna_templates')->cascadeOnDelete();
            $table->foreignUlid('tna_task_id')->constrained('tna_tasks')->restrictOnDelete();
            $table->smallInteger('offset_days');
            $table->unsignedSmallInteger('duration_days')->default(1);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->unique(['tna_template_id', 'tna_task_id']);
        });

        Schema::create('tna_task_rights', function (Blueprint $table): void {
            $this->base($table);
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('tna_task_id')->constrained('tna_tasks')->cascadeOnDelete();
            $table->boolean('can_create')->default(false);
            $table->boolean('can_update')->default(false);
            $table->boolean('can_delete')->default(false);
            $table->unique(['user_id', 'tna_task_id']);
        });
    }

    public function down(): void
    {
        foreach (['tna_task_rights', 'tna_template_tasks', 'tna_templates', 'tna_tasks', 'tna_task_groups', 'trim_spec_controls', 'trim_spec_item_rules', 'trim_spec_templates', 'rate_line_rates', 'rate_lines', 'process_loss_allowances', 'dyeing_loss_bands'] as $table) {
            Schema::dropIfExists($table);
        }
    }

    private function base(Blueprint $table): void
    {
        $table->ulid('id')->primary();
        $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
        $table->timestamps();
    }
};

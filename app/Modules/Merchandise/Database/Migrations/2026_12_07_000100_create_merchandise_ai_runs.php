<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Merchandise Desk → the AI groups (AnnotechERP 06/02–06/04): each "Run
 * analysis" keeps a snapshot of the live book — every order's composite risk,
 * health, delay forecast and four sub-scores with the reasons behind them and
 * the next move; every open T&A task ranked — and raises alerts that outlive
 * the run, so an acknowledged, resolved or dismissed alert stays so.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merch_ai_runs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->timestamp('run_at');
            $table->string('engine', 12)->default('RULE');
            $table->unsignedInteger('orders')->default(0);
            $table->unsignedInteger('tasks')->default(0);
            $table->unsignedInteger('alerts')->default(0);
            $table->text('narrative')->nullable();
            $table->ulid('run_by')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'run_at']);
        });

        Schema::create('merch_ai_order_scores', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('run_id')->constrained('merch_ai_runs')->cascadeOnDelete();
            $table->ulid('order_id');
            $table->decimal('risk', 5, 1);
            $table->decimal('health', 5, 1);
            $table->string('band', 10);
            $table->decimal('delay_probability', 5, 1);
            $table->unsignedSmallInteger('expected_delay');
            $table->unsignedTinyInteger('confidence');
            $table->decimal('material_risk', 5, 1);
            $table->decimal('production_risk', 5, 1);
            $table->decimal('approval_risk', 5, 1);
            $table->decimal('tna_risk', 5, 1)->default(0);
            $table->decimal('shipment_risk', 5, 1);
            $table->decimal('production_progress', 5, 1);
            $table->decimal('task_completion', 5, 1);
            $table->unsignedSmallInteger('tasks_total')->default(0);
            $table->unsignedSmallInteger('tasks_done')->default(0);
            $table->string('next_milestone', 160)->nullable();
            $table->date('next_milestone_date')->nullable();
            $table->json('factors');
            $table->text('action');
            $table->string('action_source', 8)->default('RULE');
            $table->string('summary', 500);
            $table->timestamps();
            $table->index(['company_id', 'run_id']);
        });

        Schema::create('merch_ai_task_scores', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('run_id')->constrained('merch_ai_runs')->cascadeOnDelete();
            $table->ulid('order_tna_id');
            $table->ulid('order_id');
            $table->unsignedInteger('rank');
            $table->integer('days_late');
            $table->unsignedSmallInteger('slip_days');
            $table->decimal('slip_risk', 5, 1);
            $table->decimal('priority', 5, 1);
            $table->text('recommendation');
            $table->timestamps();
            $table->index(['company_id', 'run_id']);
        });

        Schema::create('merch_ai_alerts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->ulid('order_id');
            $table->string('kind', 16);
            $table->string('severity', 10);
            $table->string('title', 255);
            $table->text('detail');
            $table->text('advice');
            $table->string('status', 14)->default('OPEN');
            $table->ulid('first_run_id');
            $table->ulid('last_run_id');
            $table->timestamp('status_at')->nullable();
            $table->ulid('status_by')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'order_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merch_ai_alerts');
        Schema::dropIfExists('merch_ai_task_scores');
        Schema::dropIfExists('merch_ai_order_scores');
        Schema::dropIfExists('merch_ai_runs');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Approval workflow: ERD Section 20, SRS 14.
 *
 * The rule that shapes the schema is context_json. A request freezes the cost,
 * criticality and factory the rules were evaluated against, so a later cost
 * change never retroactively alters what an approver was looking at when they
 * signed. Without it, "who approved a 200,000 taka repair" becomes unanswerable
 * the moment the estimate is edited.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_workflows', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name');
            // WORK_ORDER | INVENTORY_TRANSFER | COST_ENTRY
            $table->string('entity_type', 48);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['company_id', 'entity_type', 'active'], 'approval_workflows_entity_idx');
        });

        Schema::create('approval_rules', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('workflow_id')->constrained('approval_workflows')->cascadeOnDelete();
            // The conditions this step applies under: cost threshold, asset
            // criticality, maintenance type, factory, department (SRS 14).
            $table->json('condition_json')->nullable();
            // Steps run in order. A finance sign-off that can happen before the
            // engineer has looked at the job is not a workflow.
            $table->unsignedInteger('sequence')->default(1);
            // A step names a role, a specific user, or a team. Role is the
            // normal case; a named user is for the one person who must see it.
            $table->foreignUlid('role_id')->nullable()->constrained('roles')->cascadeOnDelete();
            $table->foreignUlid('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('team_id')->nullable()->constrained('teams')->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'workflow_id', 'sequence'], 'approval_rules_sequence_idx');
        });

        Schema::create('approval_requests', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('workflow_id')->constrained('approval_workflows')->restrictOnDelete();

            $table->string('entity_type', 48);
            $table->ulid('entity_id');

            $table->string('status', 24)->default('PENDING');
            $table->unsignedInteger('current_step')->default(1);
            $table->unsignedInteger('total_steps')->default(1);

            $table->foreignUlid('requested_by')->nullable();
            $table->dateTime('requested_at', 3);
            $table->dateTime('completed_at', 3)->nullable();
            // A request nobody acts on should not hold a machine hostage
            // forever; expiry is visible rather than silent.
            $table->dateTime('expires_at', 3)->nullable();

            // Frozen at request time: the cost, criticality and factory the
            // rules were evaluated against.
            $table->json('context_json')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'entity_type', 'entity_id'], 'approval_requests_entity_idx');
            $table->index(['company_id', 'status', 'requested_at'], 'approval_requests_status_idx');
        });

        Schema::create('approval_actions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('approval_request_id')->constrained('approval_requests')->cascadeOnDelete();
            $table->foreignUlid('approver_id')->nullable();
            $table->unsignedInteger('step')->default(1);
            // APPROVED | REJECTED | DELEGATED | CANCELLED | EXPIRED
            $table->string('action', 24);
            $table->text('comment')->nullable();
            $table->dateTime('acted_at', 3);
            $table->timestamps();

            $table->index(['company_id', 'approval_request_id', 'acted_at'], 'approval_actions_request_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_actions');
        Schema::dropIfExists('approval_requests');
        Schema::dropIfExists('approval_rules');
        Schema::dropIfExists('approval_workflows');
    }
};

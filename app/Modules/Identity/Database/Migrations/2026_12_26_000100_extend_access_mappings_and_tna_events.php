<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Access Control → the buyer mappings (AnnotechERP 02/02) and TNA Task Status
 * Setup (02/04).
 *
 * - `access_mappings` gains a line of text (a buyer's nomination code) and an
 *   effective window (a user's fabric rights for a buyer, from — to).
 * - `tna_tasks`, the calendar events Merchandise's templates are made of,
 *   gains what Brickland's event setup keeps: the task message, a parent
 *   event, the owning section, who it is assigned to and executed by, the
 *   status a fresh instance starts in, and whether it repeats per colour or
 *   delivery and needs buyer feedback.
 * - `tna_event_users`: the people notified when an event is raised, beyond
 *   the owning desk.
 * - `tna_event_instances`: an event raised against a style — its owner, plan
 *   and actual dates and where it stands.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('access_mappings', function (Blueprint $table): void {
            $table->string('text', 120)->nullable()->after('amount');
            $table->date('date_from')->nullable()->after('text');
            $table->date('date_to')->nullable()->after('date_from');
        });

        Schema::table('tna_tasks', function (Blueprint $table): void {
            $table->string('task_message', 500)->nullable();
            $table->ulid('parent_id')->nullable();
            $table->foreignUlid('section_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignUlid('assign_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('execution_by')->nullable()->constrained('users')->nullOnDelete();
            // PENDING, IN_PROGRESS, COMPLETED, DELAYED or CANCELLED.
            $table->string('default_status', 12)->nullable();
            $table->boolean('is_repeat')->default(false);
            $table->boolean('is_buyer_feedback')->default(false);
        });
        // Added after the columns exist, as Postgres wants for a self-reference.
        Schema::table('tna_tasks', function (Blueprint $table): void {
            $table->foreign('parent_id')->references('id')->on('tna_tasks')->nullOnDelete();
        });

        Schema::create('tna_event_users', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('tna_task_id')->constrained('tna_tasks')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['tna_task_id', 'user_id']);
        });

        Schema::create('tna_event_instances', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('tna_task_id')->constrained('tna_tasks')->cascadeOnDelete();
            // The style (or order) it is raised against, as the user typed it.
            $table->string('reference', 120);
            $table->foreignUlid('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('plan_date')->nullable();
            $table->date('actual_date')->nullable();
            $table->string('status', 12)->default('PENDING');
            $table->timestamps();
            $table->index(['company_id', 'tna_task_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tna_event_instances');
        Schema::dropIfExists('tna_event_users');
        Schema::table('tna_tasks', function (Blueprint $table): void {
            $table->dropForeign(['parent_id']);
            $table->dropConstrainedForeignId('section_id');
            $table->dropConstrainedForeignId('assign_to');
            $table->dropConstrainedForeignId('execution_by');
            $table->dropColumn(['task_message', 'parent_id', 'default_status', 'is_repeat', 'is_buyer_feedback']);
        });
        Schema::table('access_mappings', function (Blueprint $table): void {
            $table->dropColumn(['text', 'date_from', 'date_to']);
        });
    }
};

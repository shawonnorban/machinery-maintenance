<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notifications and escalation: ERD Section 17, SRS 27-28.
 *
 * Two decisions shape this schema.
 *
 * The notification row is written before anything is broadcast or emailed, so
 * a failed delivery loses a delivery attempt rather than the notification
 * itself. A technician who never hears about a critical breakdown because a
 * websocket dropped is the failure this prevents.
 *
 * An escalation is a new row linked to the one it escalates, never a mutation
 * of the original. That keeps the chain auditable: "who was told, when, and who
 * was told next" stays answerable, and the first recipient's copy does not
 * silently change owner.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('factory_id')->nullable()->constrained('factories')->nullOnDelete();

            $table->string('event_type', 64);
            // Rendered in the recipient's locale when the row is written, not
            // when it is read. A notification that changes language because
            // somebody switched the interface later is a different message
            // (SRS 48).
            $table->string('title');
            $table->text('body')->nullable();
            $table->char('locale', 5)->default('en');
            $table->json('data_json')->nullable();

            $table->string('entity_type', 48)->nullable();
            $table->ulid('entity_id')->nullable();
            $table->string('action_url')->nullable();
            // INFO | WARNING | CRITICAL
            $table->string('severity', 16)->default('INFO');

            $table->dateTime('read_at', 3)->nullable();
            // Acknowledged is not the same as read. Opening a list marks things
            // read; saying "I have this" is a separate act, and escalation
            // stops on the second, never the first.
            $table->dateTime('acknowledged_at', 3)->nullable();

            // 0 is the original recipient.
            $table->unsignedInteger('escalation_level')->default(0);
            $table->foreignUlid('source_notification_id')->nullable();
            $table->dateTime('expires_at', 3)->nullable();
            $table->timestamps();

            $table->index(['company_id', 'user_id', 'read_at'], 'notifications_inbox_idx');
            $table->index(['company_id', 'event_type', 'created_at'], 'notifications_event_idx');
            $table->index(['company_id', 'entity_type', 'entity_id'], 'notifications_entity_idx');
            $table->index(['company_id', 'acknowledged_at'], 'notifications_ack_idx');
        });

        Schema::create('notification_preferences', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('event_type', 64);

            // In-app is not switchable off. A record of what happened is part
            // of the audit trail, not a preference; the other channels decide
            // how loudly somebody is told.
            $table->boolean('in_app')->default(true);
            $table->boolean('email')->default(false);
            $table->boolean('sms')->default(false);
            $table->boolean('whatsapp')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'event_type'], 'notification_preferences_unique');
        });

        Schema::create('escalation_rules', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('factory_id')->nullable()->constrained('factories')->cascadeOnDelete();

            $table->string('event_type', 64);
            $table->string('severity', 16)->nullable();
            // Measured from the original event, never from the previous
            // escalation. Chaining delays lets a stalled chain drift: two
            // levels at "thirty minutes later" can silently become ninety.
            $table->unsignedInteger('delay_minutes');
            $table->unsignedInteger('escalation_level')->default(1);

            $table->foreignUlid('escalation_role_id')->nullable()->constrained('roles')->cascadeOnDelete();
            $table->foreignUlid('escalation_team_id')->nullable()->constrained('teams')->cascadeOnDelete();
            $table->foreignUlid('escalation_user_id')->nullable()->constrained('users')->cascadeOnDelete();

            $table->json('channel_overrides_json')->nullable();
            $table->unsignedInteger('max_escalations')->default(3);
            // Escalating something the recipient has already picked up wastes
            // everyone's attention and teaches people to ignore the channel.
            $table->boolean('stop_on_acknowledge')->default(true);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['company_id', 'event_type', 'active'], 'escalation_rules_event_idx');
            $table->index(['company_id', 'escalation_level'], 'escalation_rules_level_idx');
        });

        Schema::create('notification_deliveries', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('notification_id')->constrained('notifications')->cascadeOnDelete();
            // IN_APP | BROADCAST | EMAIL | SMS | WHATSAPP
            $table->string('channel', 24);
            // PENDING | SENT | FAILED | SKIPPED
            $table->string('status', 16)->default('PENDING');
            $table->dateTime('sent_at', 3)->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'notification_id'], 'notification_deliveries_idx');
            $table->index(['company_id', 'channel', 'status'], 'notification_deliveries_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('escalation_rules');
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('notifications');
    }
};

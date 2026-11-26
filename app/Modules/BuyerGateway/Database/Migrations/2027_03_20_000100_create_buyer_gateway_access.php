<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Buyer Gateway → Access & Onboarding (AnnotechERP 25/01): the buyer-side
 * people who hold a portal login, what each role may open per buyer, a new
 * buyer's onboarding steps, and which portal events reach a buyer by mail.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bg_portal_users', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('buyer_id')->constrained('buyers')->restrictOnDelete();
            $table->string('name', 120);
            $table->string('email', 190);
            $table->string('role', 24)->nullable(); // MERCHANDISER | QUALITY_MANAGER | SOURCING_HEAD | LOGISTICS | FINANCE | READ_ONLY
            $table->foreignUlid('country_id')->nullable()->constrained('country_configs')->nullOnDelete();
            $table->date('last_seen')->nullable();
            $table->string('state', 12)->default('INVITED'); // ACTIVE | INVITED | SUSPENDED | LOCKED
            $table->boolean('login_enabled')->default(true);
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'email'], 'bg_portal_users_email_unique');
        });

        Schema::create('bg_permission_sets', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('buyer_id')->constrained('buyers')->restrictOnDelete();
            $table->string('role', 24);
            $table->string('orders', 12)->default('READ_ONLY'); // FULL | READ_ONLY | HIDDEN
            $table->string('samples', 12)->default('READ_ONLY');
            $table->string('documents', 12)->default('VIEW_ONLY'); // DOWNLOAD | VIEW_ONLY | HIDDEN
            $table->string('commercial', 12)->default('HIDDEN');
            $table->boolean('may_approve')->default(false);
            $table->date('set_on')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'buyer_id', 'role'], 'bg_permission_sets_role_unique');
        });

        Schema::create('bg_onboardings', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('buyer_name', 120);
            $table->string('step', 24)->default('CONTRACT_SIGNED'); // the eight steps, CONTRACT_SIGNED … LIVE
            $table->foreignUlid('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('started_on')->nullable();
            $table->date('target_live')->nullable();
            $table->unsignedTinyInteger('progress')->default(0);
            $table->unsignedInteger('users_invited')->default(0);
            $table->string('state', 12)->default('ON_TRACK'); // ON_TRACK | AT_RISK | BLOCKED | COMPLETE
            $table->timestamps();
            $table->unique(['company_id', 'buyer_name'], 'bg_onboardings_buyer_unique');
        });

        Schema::create('bg_notification_prefs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('buyer_id')->constrained('buyers')->restrictOnDelete();
            $table->string('event', 32);
            $table->string('channel', 12)->default('EMAIL'); // EMAIL | IN_PORTAL | BOTH | MUTED
            $table->string('digest', 12)->default('IMMEDIATE'); // IMMEDIATE | DAILY | WEEKLY
            $table->string('recipients', 16)->default('ALL'); // ALL | MERCHANDISERS | NAMED | OWNER
            $table->boolean('enabled')->default(true);
            $table->date('set_on')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'buyer_id', 'event'], 'bg_notification_prefs_event_unique');
        });
    }

    public function down(): void
    {
        foreach (['bg_notification_prefs', 'bg_onboardings', 'bg_permission_sets', 'bg_portal_users'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};

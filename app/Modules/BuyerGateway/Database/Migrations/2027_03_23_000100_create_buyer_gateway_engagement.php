<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Buyer Gateway → Buyer Engagement (AnnotechERP 25/04): what buyers ask for
 * and who answers it, complaints and claims, notices published to the
 * portal, feedback surveys and the portal's support tickets.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bg_requests', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('subject', 200);
            $table->foreignUlid('buyer_id')->constrained('buyers')->restrictOnDelete();
            $table->string('kind', 16)->nullable(); // ORDER_CHANGE | SAMPLE | DOCUMENT | PRICE | SHIPMENT | OTHER
            $table->string('routed_to', 16)->default('UNROUTED'); // MERCHANDISING | SAMPLE_ROOM | COMMERCIAL | PLANNING | QUALITY | UNROUTED
            $table->date('raised_on')->nullable();
            $table->string('priority', 8)->default('NORMAL'); // URGENT | NORMAL | LOW
            $table->string('state', 12)->default('NEW'); // NEW | IN_PROGRESS | ANSWERED | CLOSED
            $table->date('closed_on')->nullable();
            $table->timestamps();
        });

        Schema::create('bg_complaints', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('reference', 40);
            $table->foreignUlid('buyer_id')->constrained('buyers')->restrictOnDelete();
            $table->string('shipment', 60)->nullable();
            $table->string('cause', 16)->nullable(); // SHADE | MEASUREMENT | STITCHING | PACKING | SHORT_SHIPMENT | LATE_DELIVERY | NOT_ESTABLISHED
            $table->decimal('claim_value', 14, 2)->nullable();
            $table->date('raised_on')->nullable();
            $table->string('state', 14)->default('OPEN'); // OPEN | INVESTIGATING | OFFER_MADE | SETTLED | REJECTED
            $table->text('resolution')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'reference'], 'bg_complaints_reference_unique');
        });

        Schema::create('bg_announcements', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('title', 160);
            $table->string('audience', 12)->default('ALL'); // ALL | STRATEGIC | ONE | LOGISTICS
            $table->foreignUlid('buyer_id')->nullable()->constrained('buyers')->nullOnDelete();
            $table->string('kind', 10)->nullable(); // HOLIDAY | CAPACITY | POLICY | SYSTEM | GENERAL
            $table->date('published_on')->nullable();
            $table->date('expires_on')->nullable();
            $table->unsignedInteger('reads')->default(0);
            $table->boolean('draft')->default(false);
            $table->text('body')->nullable();
            $table->timestamps();
        });

        Schema::create('bg_surveys', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('title', 160);
            $table->foreignUlid('buyer_id')->constrained('buyers')->restrictOnDelete();
            $table->string('season', 16)->nullable();
            $table->date('sent_on')->nullable();
            $table->unsignedInteger('recipients')->default(0);
            $table->unsignedInteger('responses')->default(0);
            $table->decimal('score', 3, 1)->nullable();
            $table->string('state', 8)->default('DRAFT'); // OPEN | CLOSED | DRAFT
            $table->timestamps();
        });

        Schema::create('bg_tickets', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('reference', 40);
            $table->string('subject', 200);
            $table->foreignUlid('buyer_id')->nullable()->constrained('buyers')->nullOnDelete();
            $table->string('priority', 8)->default('NORMAL'); // URGENT | HIGH | NORMAL | LOW
            $table->foreignUlid('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('raised_on')->nullable();
            $table->string('state', 12)->default('NEW'); // NEW | IN_PROGRESS | WAITING | RESOLVED | CLOSED
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'reference'], 'bg_tickets_reference_unique');
        });
    }

    public function down(): void
    {
        foreach (['bg_tickets', 'bg_surveys', 'bg_announcements', 'bg_complaints', 'bg_requests'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};

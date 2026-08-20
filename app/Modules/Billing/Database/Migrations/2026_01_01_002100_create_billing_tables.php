<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Subscriptions, invoices and usage: ERD Section 19, SRS 40, ADR-028/029.
 *
 * There are no fixed packages. A garment group negotiates on factories, assets
 * or users and signs a contract, so the contract is the product and the
 * pricing model lives on it (ADR-028).
 *
 * The lifecycle is the part that has to be got right. A factory that misses a
 * payment does not lose its maintenance history: it goes ACTIVE → PAST_DUE →
 * GRACE → READ_ONLY → ARCHIVED, and read-only still serves every screen and
 * every export, because the data belongs to the customer and they must always
 * be able to get it out (ADR-029, ADR-030, SRS 49.3).
 *
 * Money is DECIMAL(18,4) throughout and never a float. An invoice total that
 * is out by a hundredth is an invoice somebody disputes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_contracts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();

            $table->string('contract_number', 32);
            // DRAFT|TRIAL|ACTIVE|PAST_DUE|GRACE|READ_ONLY|ARCHIVED|CANCELLED
            $table->string('status', 16)->default('DRAFT');

            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('billing_cycle', 16)->default('MONTHLY'); // MONTHLY|QUARTERLY|YEARLY
            $table->decimal('amount', 18, 4)->default(0);
            $table->string('currency', 3)->default('BDT');

            $table->date('trial_end')->nullable();
            // Days after a missed payment before access narrows. Per contract,
            // because it is negotiated per contract.
            $table->unsignedSmallInteger('grace_period_days')->default(14);
            $table->boolean('auto_renew')->default(true);

            $table->timestamp('read_only_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();

            // Per-factory, per-asset, per-user or flat terms.
            $table->json('pricing_model_json')->nullable();
            $table->unsignedInteger('included_factories')->nullable();
            $table->unsignedInteger('included_assets')->nullable();
            $table->unsignedInteger('included_users')->nullable();
            // The link between measured usage and what happens when it is
            // exceeded, which v1.0 described but never modelled.
            $table->string('overage_policy', 16)->default('WARN_ONLY'); // BLOCK|ALLOW_AND_BILL|WARN_ONLY

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'contract_number'], 'subscription_contracts_number_unique');
            $table->index(['status', 'end_date'], 'subscription_contracts_lifecycle_index');
        });

        Schema::create('subscription_invoices', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('subscription_contract_id')->constrained('subscription_contracts')->cascadeOnDelete();

            $table->string('invoice_number', 32);
            $table->date('issue_date');
            $table->date('due_date');

            $table->decimal('subtotal', 18, 4)->default(0);
            $table->decimal('tax', 18, 4)->default(0);
            $table->decimal('total', 18, 4)->default(0);
            $table->decimal('tax_rate', 8, 4)->default(0);
            $table->string('tax_reference', 64)->nullable();
            $table->string('currency', 3)->default('BDT');

            // DRAFT|ISSUED|PARTIALLY_PAID|PAID|OVERDUE|VOID|WRITTEN_OFF
            $table->string('status', 16)->default('DRAFT');
            $table->decimal('paid_amount', 18, 4)->default(0);
            $table->decimal('balance_due', 18, 4)->default(0);

            $table->timestamp('paid_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->foreignUlid('pdf_file_id')->nullable()->constrained('file_attachments')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'invoice_number'], 'subscription_invoices_number_unique');
            $table->index(['company_id', 'status', 'due_date'], 'subscription_invoices_status_index');
        });

        // Without lines, a contract priced per factory or per asset cannot be
        // itemized and the customer cannot see what they were billed for.
        Schema::create('subscription_invoice_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('subscription_invoice_id')->constrained('subscription_invoices')->cascadeOnDelete();

            $table->string('description');
            $table->string('metric', 16)->nullable(); // FACTORIES|ASSETS|USERS|FLAT
            $table->decimal('quantity', 18, 4)->default(1);
            $table->decimal('unit_price', 18, 4)->default(0);
            $table->decimal('amount', 18, 4)->default(0);
            $table->decimal('tax_rate', 8, 4)->default(0);
            $table->decimal('tax_amount', 18, 4)->default(0);
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->index(['subscription_invoice_id', 'sort_order'], 'subscription_invoice_lines_order_index');
        });

        Schema::create('subscription_payments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('invoice_id')->constrained('subscription_invoices')->cascadeOnDelete();

            $table->string('payment_reference', 64);
            $table->string('method', 24); // BANK_TRANSFER|CASH|CHEQUE|CARD|MOBILE|GATEWAY
            $table->decimal('amount', 18, 4);
            $table->string('currency', 3)->default('BDT');
            $table->timestamp('paid_at');
            $table->string('status', 16)->default('RECEIVED'); // RECEIVED|REVERSED
            $table->text('notes')->nullable();
            $table->foreignUlid('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'payment_reference'], 'subscription_payments_reference_unique');
            $table->index(['company_id', 'invoice_id'], 'subscription_payments_invoice_index');
        });

        Schema::create('refunds', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('payment_id')->constrained('subscription_payments')->cascadeOnDelete();

            $table->decimal('amount', 18, 4);
            $table->string('currency', 3)->default('BDT');
            $table->text('reason');
            $table->string('status', 16)->default('ISSUED'); // ISSUED|SETTLED|CANCELLED
            $table->timestamp('issued_at');
            $table->foreignUlid('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'payment_id'], 'refunds_payment_index');
        });

        // An issued invoice is immutable: it is corrected by one of these, or
        // by voiding and reissuing, never by editing.
        Schema::create('credit_notes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('invoice_id')->constrained('subscription_invoices')->cascadeOnDelete();

            $table->string('credit_note_number', 32);
            $table->decimal('amount', 18, 4);
            $table->string('currency', 3)->default('BDT');
            $table->text('reason');
            $table->string('status', 16)->default('ISSUED'); // ISSUED|APPLIED|CANCELLED
            $table->timestamp('issued_at');
            $table->foreignUlid('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'credit_note_number'], 'credit_notes_number_unique');
        });

        // Measured whether or not it is billed, so a renewal can be priced from
        // evidence rather than from an argument.
        Schema::create('usage_metrics', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('factory_id')->nullable()->constrained('factories')->cascadeOnDelete();

            $table->string('metric', 32);
            $table->decimal('value', 18, 4)->default(0);
            $table->decimal('limit_value', 18, 4)->nullable();
            $table->boolean('exceeded')->default(false);

            $table->timestamp('measured_at');
            $table->date('period_start');
            $table->date('period_end');
            $table->timestamps();

            $table->unique(
                ['company_id', 'metric', 'factory_id', 'period_start'],
                'usage_metrics_period_unique',
            );
            $table->index(['company_id', 'measured_at'], 'usage_metrics_time_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_metrics');
        Schema::dropIfExists('credit_notes');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('subscription_payments');
        Schema::dropIfExists('subscription_invoice_lines');
        Schema::dropIfExists('subscription_invoices');
        Schema::dropIfExists('subscription_contracts');
    }
};

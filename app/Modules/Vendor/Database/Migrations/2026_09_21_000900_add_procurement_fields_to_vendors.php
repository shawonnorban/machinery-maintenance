<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What procurement needs to know about a supplier that maintenance never
 * did (Master Data spec §3): registration numbers, where to pay them, what
 * they supply, whether they have been vetted, and how they have performed.
 *
 * On the existing vendors table rather than a parallel suppliers table —
 * the same company sells a knitting machine and its spare needles, and
 * entering it twice is how two records drift apart. `approval_status` is a
 * separate concern from `status`: one says whether the vendor has been
 * vetted, the other whether it is currently in use.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table): void {
            $table->string('bin', 32)->nullable()->after('tax_reference');
            $table->string('trade_license_no', 64)->nullable()->after('bin');
            $table->foreignUlid('bank_id')->nullable()->after('trade_license_no')
                ->constrained('banks')->nullOnDelete();
            $table->string('bank_branch')->nullable()->after('bank_id');
            $table->string('bank_account_no', 64)->nullable()->after('bank_branch');
            $table->string('bank_swift', 16)->nullable()->after('bank_account_no');
            $table->json('supply_categories_json')->nullable()->after('bank_swift');
            $table->string('approval_status', 16)->default('PENDING')->after('status');
            $table->char('default_currency', 3)->nullable()->after('approval_status');
            $table->foreignUlid('default_payment_type_id')->nullable()->after('default_currency')
                ->constrained('payment_types')->nullOnDelete();
            $table->string('default_payment_terms', 64)->nullable()->after('default_payment_type_id');
            // 0.00–5.00, written by the supplier scorecard, never by hand.
            $table->decimal('rating', 4, 2)->nullable()->after('default_payment_terms');
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('default_payment_type_id');
            $table->dropConstrainedForeignId('bank_id');
            $table->dropColumn([
                'bin', 'trade_license_no', 'bank_branch', 'bank_account_no', 'bank_swift',
                'supply_categories_json', 'approval_status', 'default_currency', 'default_payment_terms', 'rating',
            ]);
        });
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Finance & Ledger → Voucher Entry (AnnotechERP 23/03): every change to a
 * voucher, kept with the voucher as it then stood (header and lines) — raised,
 * amended, approved, posted, unposted, removed. A removed voucher's log
 * outlives it. Voucher Audit (23/06: Deleted Voucher History, Voucher
 * Amendment Log, Voucher Activity Trail) reads from here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acc_voucher_logs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->ulid('voucher_id'); // no foreign key: the log outlives a removed voucher
            $table->string('voucher_no', 24)->nullable();
            $table->string('voucher_ref_no', 24);
            $table->string('action', 10); // CREATE | UPDATE | APPROVE | POST | UNPOST | DELETE
            $table->string('posting_status', 10);
            $table->json('snapshot');
            $table->foreignUlid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['company_id', 'voucher_id']);
            $table->index(['company_id', 'action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acc_voucher_logs');
    }
};

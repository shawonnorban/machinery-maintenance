<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * HR & Payroll → Employee → ID Card (AnnotechERP 24/03): `hr_id_card_queue` —
 * the cards waiting to be printed, one per employee, with the Print Option
 * (Bangla, English or Mtex English) and whether it goes one to a sheet. The
 * card itself is drawn from the employee profile at print time, so a queued
 * card is never out of date.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_id_card_queue', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->string('font', 12); // BANGLA | ENGLISH | MTEX_ENGLISH
            $table->boolean('single_per_page')->default(false);
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_id_card_queue');
    }
};

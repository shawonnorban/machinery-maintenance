<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Buyer Gateway → Portal Administration (AnnotechERP 25/05): how much each
 * buyer uses the portal, month by month; the logo, theme and wording each
 * buyer sees; and the trail of what was done from the buyer's side.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bg_usage_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('buyer_id')->constrained('buyers')->restrictOnDelete();
            $table->string('period', 7); // YYYY-MM
            $table->unsignedInteger('logins')->default(0);
            $table->unsignedInteger('active_users')->default(0);
            $table->string('most_opened', 10)->nullable(); // ORDERS | SHIPMENTS | SAMPLES | DOCUMENTS | INVOICES
            $table->unsignedInteger('downloads')->default(0);
            $table->decimal('avg_session', 5, 1)->default(0); // minutes
            $table->decimal('adoption', 5, 1)->default(0); // percent
            $table->timestamps();
            $table->unique(['company_id', 'buyer_id', 'period'], 'bg_usage_lines_buyer_period_unique');
        });

        Schema::create('bg_branding_profiles', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('buyer_id')->constrained('buyers')->restrictOnDelete();
            $table->string('theme', 14)->default('LIGHT'); // LIGHT | DARK | SYSTEM | HIGH_CONTRAST
            $table->unsignedTinyInteger('accent_slot')->default(1); // 1–8
            $table->string('logo_file', 255)->nullable(); // a file name, or an uploaded logo's download path
            $table->string('welcome_line', 160)->nullable();
            $table->string('support_email', 160)->nullable();
            $table->date('updated_on')->nullable();
            $table->boolean('live')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'buyer_id'], 'bg_branding_profiles_buyer_unique');
        });

        Schema::create('bg_activity_entries', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('actor', 120);
            $table->foreignUlid('buyer_id')->nullable()->constrained('buyers')->nullOnDelete();
            $table->string('action', 16)->nullable(); // SIGNED_IN | DOWNLOADED | APPROVED | COMMENTED | RAISED_REQUEST | SIGNED_OUT
            $table->string('target', 200)->nullable();
            $table->date('occurred_on');
            $table->string('occurred_time', 5); // HH:MM
            $table->string('device', 8)->nullable(); // DESKTOP | MOBILE | TABLET
            $table->timestamps();
            $table->index(['company_id', 'occurred_on'], 'bg_activity_entries_day_index');
        });
    }

    public function down(): void
    {
        foreach (['bg_activity_entries', 'bg_branding_profiles', 'bg_usage_lines'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Settings → Device Integration (AnnotechERP 03/01) and HR & Payroll →
 * Employee → Biometric / Fingerprint (24/03): the attendance terminals and
 * what they report.
 *
 * - `dev_devices`: a terminal — code, name, vendor, model, how the ERP reaches
 *   it (ADMS push: the terminal calls /iclock/* with its serial number; TCP
 *   pull: the ERP calls it on port 4370), the address, comm key, allowed
 *   source addresses, time zone, state (Active, Disabled, Pending approval),
 *   how a User ID is matched to an employee, whether punches go to HR; and
 *   what it last said — contact, punch, firmware, user / finger / log counts.
 * - `dev_punches`: every punch as the terminal reported it, never edited; a
 *   punch is kept once per terminal, User ID and time, matched to an employee
 *   when it can be.
 * - `dev_users`: the users each terminal holds — User ID (PIN), name, card,
 *   role — matched to an employee.
 * - `dev_fingerprints`: the finger templates the terminals reported, kept so
 *   an employee can be sent to every terminal.
 * - `dev_employee_maps`: an employee's PIN on a terminal, Pending until the
 *   terminal reports their fingers, then Registered.
 * - `dev_commands`: commands queued for push terminals — numbered, because the
 *   terminal answers by number — with when they were delivered and answered.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dev_devices', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('code', 24);
            $table->string('name', 120);
            $table->string('vendor', 12)->default('ZKTECO');
            $table->string('model', 60)->nullable();
            $table->string('protocol', 10)->default('ADMS_PUSH'); // ADMS_PUSH | TCP_PULL
            $table->string('serial_no', 40)->nullable()->unique();
            $table->string('ip_address', 45)->nullable();
            $table->unsignedInteger('port')->default(4370);
            $table->unsignedInteger('comm_key')->default(0);
            $table->unsignedSmallInteger('poll_minutes')->default(5);
            $table->string('allowed_ips', 500)->nullable();
            $table->decimal('time_zone', 4, 2)->default(6);
            $table->string('state', 10)->default('ACTIVE'); // ACTIVE | DISABLED | PENDING
            $table->string('location', 120)->nullable();
            $table->string('user_match', 14)->default('AUTO'); // AUTO | PROXY_NO | EMPLOYEE_CODE
            $table->boolean('post_to_hr')->default(true);
            $table->boolean('post_daily')->default(true);
            $table->text('remarks')->nullable();
            $table->timestamp('last_contact_at')->nullable();
            $table->string('last_contact_ip', 45)->nullable();
            $table->timestamp('last_punch_at')->nullable();
            $table->string('firmware', 60)->nullable();
            $table->unsignedInteger('user_count')->nullable();
            $table->unsignedInteger('fp_count')->nullable();
            $table->unsignedInteger('log_count')->nullable();
            $table->string('last_result', 300)->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'code']);
        });

        Schema::create('dev_punches', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('device_id')->constrained('dev_devices')->cascadeOnDelete();
            $table->string('pin', 24);
            $table->timestamp('punched_at');
            $table->unsignedSmallInteger('state')->default(0); // 0 in, 1 out, 2 break out, 3 break in, 4 OT in, 5 OT out
            $table->unsignedSmallInteger('verify')->default(1); // 0 password, 1 finger, 2 card, 15 face …
            $table->string('work_code', 12)->nullable();
            $table->string('source', 6)->default('PUSH'); // PUSH | PULL
            $table->foreignUlid('employee_id')->nullable()->constrained('hr_employees')->nullOnDelete();
            $table->boolean('posted_to_hr')->default(false);
            $table->timestamp('received_at');
            $table->unique(['device_id', 'pin', 'punched_at']);
            $table->index(['company_id', 'punched_at']);
            $table->index(['company_id', 'employee_id', 'punched_at']);
        });

        Schema::create('dev_users', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('device_id')->constrained('dev_devices')->cascadeOnDelete();
            $table->string('pin', 24);
            $table->string('name', 120)->nullable();
            $table->string('card_no', 40)->nullable();
            $table->unsignedSmallInteger('privilege')->default(0); // 0 user, 2 enroller, 6 manager, 14 admin
            $table->foreignUlid('employee_id')->nullable()->constrained('hr_employees')->nullOnDelete();
            $table->timestamp('reported_at');
            $table->unique(['device_id', 'pin']);
        });

        Schema::create('dev_fingerprints', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('device_id')->constrained('dev_devices')->cascadeOnDelete();
            $table->string('pin', 24);
            $table->unsignedSmallInteger('finger');
            $table->unsignedInteger('size')->default(0);
            $table->unsignedSmallInteger('valid')->default(1);
            $table->text('template');
            $table->foreignUlid('employee_id')->nullable()->constrained('hr_employees')->nullOnDelete();
            $table->timestamp('reported_at');
            $table->unique(['device_id', 'pin', 'finger']);
            $table->index(['company_id', 'employee_id']);
        });

        Schema::create('dev_employee_maps', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('device_id')->constrained('dev_devices')->cascadeOnDelete();
            $table->foreignUlid('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->string('pin', 24);
            $table->string('status', 10)->default('PENDING'); // PENDING | REGISTERED
            $table->timestamp('registered_at')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['device_id', 'employee_id']);
            $table->unique(['device_id', 'pin']);
        });

        Schema::create('dev_commands', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('device_id')->constrained('dev_devices')->cascadeOnDelete();
            $table->string('kind', 16); // INFO | CHECK | REBOOT | USER | FINGER | DELETE | QUERY_LOGS | QUERY_USERS
            $table->text('command');
            $table->string('label', 200);
            $table->string('status', 10)->default('QUEUED'); // QUEUED | SENT | DONE | FAILED | CANCELLED
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->integer('return_code')->nullable();
            $table->text('result')->nullable();
            $table->foreignUlid('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['device_id', 'status', 'id']);
        });
    }

    public function down(): void
    {
        foreach (['dev_commands', 'dev_employee_maps', 'dev_fingerprints', 'dev_users', 'dev_punches', 'dev_devices'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};

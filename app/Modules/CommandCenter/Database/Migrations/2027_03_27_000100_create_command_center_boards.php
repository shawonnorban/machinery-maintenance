<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Command Center → Command Center (AnnotechERP 27/01): the departmental
 * performance boards, the paths a headline metric drills down along, and the
 * dashboards composed from the KPI library.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cc_boards', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name', 160);
            $table->foreignUlid('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignUlid('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('tiles')->default(0);
            $table->string('refresh', 8)->default('DAILY'); // LIVE | HOURLY | DAILY | WEEKLY
            $table->string('audience', 14)->default('DEPT_HEADS'); // BOARD | DEPT_HEADS | ALL_MANAGERS | EVERYONE
            $table->decimal('data_health', 5, 1)->default(100);
            $table->boolean('published')->default(false);
            $table->timestamps();
        });

        Schema::create('cc_drill_paths', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('metric', 160);
            $table->string('domain', 12)->nullable(); // COMMERCIAL | PRODUCTION | QUALITY | PEOPLE | FINANCE
            $table->string('level_1', 12)->nullable(); // DIVISION | BUYER | SEASON | DEPARTMENT | COST_CENTRE
            $table->string('level_2', 12)->nullable(); // STYLE | LINE | ORDER | MACHINE | ACCOUNT
            $table->string('level_3', 12)->nullable(); // SHIFT | OPERATOR | COLOUR | SIZE | DAY
            $table->string('source', 16)->nullable(); // MERCHANDISING | KNIT | ASSEMBLY | CUTTING | ACCOUNTING | HR
            $table->string('grain', 8)->nullable(); // HOURLY | DAILY | WEEKLY | MONTHLY
            $table->unsignedInteger('rows_k')->default(0); // rows behind it, in thousands
            $table->timestamps();
        });

        Schema::create('cc_compositions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('title', 160);
            $table->string('layout', 10)->nullable(); // TWO | THREE | SINGLE | GRID | FULL
            $table->foreignUlid('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('tiles')->default(0);
            $table->unsignedSmallInteger('charts')->default(0);
            $table->string('shared_with', 14)->default('BOARD'); // BOARD | DIVISION_HEADS | ALL_MANAGERS | EVERYONE
            $table->string('state', 10)->default('DRAFT'); // DRAFT | RELEASED | ARCHIVED
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['cc_compositions', 'cc_drill_paths', 'cc_boards'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};

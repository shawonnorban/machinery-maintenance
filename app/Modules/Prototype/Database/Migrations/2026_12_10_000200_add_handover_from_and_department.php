<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Sample Handover: who hands the sample over, and the receiving department. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proto_handovers', function (Blueprint $table): void {
            $table->foreignUlid('handover_from')->nullable()->after('handover_date')->constrained('users')->nullOnDelete();
            $table->foreignUlid('department_id')->nullable()->after('handover_to')->constrained('departments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('proto_handovers', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('department_id');
            $table->dropConstrainedForeignId('handover_from');
        });
    }
};

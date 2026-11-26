<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Merchandise Desk → Trims & Accessories (AnnotechERP 06/06): a dummy-order
 * accessories indent is the same requisition, raised against any order — one
 * not yet confirmed too — and kept apart from the regular indents.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merch_acc_requisitions', function (Blueprint $table): void {
            $table->boolean('dummy')->default(false)->after('order_id');
            $table->index(['company_id', 'dummy']);
        });
    }

    public function down(): void
    {
        Schema::table('merch_acc_requisitions', function (Blueprint $table): void {
            $table->dropIndex(['company_id', 'dummy']);
            $table->dropColumn('dummy');
        });
    }
};

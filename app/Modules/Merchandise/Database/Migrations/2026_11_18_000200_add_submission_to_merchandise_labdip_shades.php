<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lab Dip & Sample → Lab Dip Submission (AnnotechERP 06/10): each shade's
 * way to the buyer — when it was submitted, the buyer's verdict (approved,
 * rejected, or resubmit), the day it came and what the buyer said.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merch_labdip_shades', function (Blueprint $table): void {
            $table->string('status', 16)->default('PENDING');
            $table->date('submitted_on')->nullable();
            $table->date('decided_on')->nullable();
            $table->text('feedback')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('merch_labdip_shades', function (Blueprint $table): void {
            $table->dropColumn(['status', 'submitted_on', 'decided_on', 'feedback']);
        });
    }
};

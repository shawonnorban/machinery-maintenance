<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A browser that agreed to chat notifications (Web Push): where its push
 * service takes the message (`endpoint`) and the keys it is encrypted with.
 * One row per browser — signing in as someone else on it moves the row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_push_subscriptions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->char('endpoint_hash', 64)->unique();
            $table->text('endpoint');
            $table->string('p256dh', 200);
            $table->string('auth', 100);
            $table->string('user_agent', 300)->nullable();
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_push_subscriptions');
    }
};

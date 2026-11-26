<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chat (user 2026-11-22: Norban's chat module, WhatsApp style) — a company's
 * people talking one to one or in groups:
 *  - chats: a conversation; a one-to-one chat carries `direct_key` (its two
 *    user ids, sorted) so the same two people always land in the same chat;
 *  - chat_members: who is in it, as owner / admin / member, with how far each
 *    has read and received (the newest message id — ULIDs sort by time, which
 *    a second-precision timestamp does not), the chat pinned or muted for them;
 *  - chat_messages: text, an image or a file (one file a message, with a
 *    caption), or a system line (group made, someone added); a reply points
 *    at another message, a forward is marked; edited / deleted for everyone;
 *    pinned for the chat;
 *  - chat_message_reactions: one emoji per person per message;
 *  - chat_message_hides: a message deleted "for me" only;
 *  - chat_presence: when each person was last seen in the chat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chats', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->boolean('is_group')->default(false);
            $table->string('name', 120)->nullable();
            $table->string('description', 500)->nullable();
            $table->ulid('image_file_id')->nullable();
            $table->string('direct_key', 60)->nullable();
            $table->ulid('last_message_id')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'direct_key']);
            $table->index(['company_id', 'last_message_at']);
        });

        Schema::create('chat_members', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('chat_id')->constrained('chats')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 10)->default('MEMBER'); // OWNER | ADMIN | MEMBER
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->ulid('read_message_id')->nullable();
            $table->ulid('delivered_message_id')->nullable();
            $table->ulid('cleared_message_id')->nullable();
            $table->timestamp('pinned_at')->nullable();
            $table->boolean('muted')->default(false);
            $table->boolean('archived')->default(false);
            $table->timestamps();
            $table->unique(['chat_id', 'user_id']);
            $table->index(['user_id', 'left_at']);
        });

        Schema::create('chat_messages', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('chat_id')->constrained('chats')->cascadeOnDelete();
            $table->foreignUlid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 10)->default('TEXT'); // TEXT | IMAGE | FILE | SYSTEM
            $table->text('body')->nullable();
            $table->ulid('file_id')->nullable();
            $table->ulid('reply_to_id')->nullable();
            $table->boolean('forwarded')->default(false);
            $table->json('meta')->nullable();
            $table->timestamp('edited_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamp('pinned_at')->nullable();
            $table->foreignUlid('pinned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['chat_id', 'id']);
        });

        Schema::create('chat_message_reactions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('message_id')->constrained('chat_messages')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('emoji', 16);
            $table->timestamps();
            $table->unique(['message_id', 'user_id']);
        });

        Schema::create('chat_message_hides', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('message_id')->constrained('chat_messages')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['message_id', 'user_id']);
        });

        Schema::create('chat_presence', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_presence');
        Schema::dropIfExists('chat_message_hides');
        Schema::dropIfExists('chat_message_reactions');
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_members');
        Schema::dropIfExists('chats');
    }
};

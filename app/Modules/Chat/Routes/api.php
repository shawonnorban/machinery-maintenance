<?php

declare(strict_types=1);

use App\Modules\Chat\Http\Controllers\Api\ChatApiController;
use Illuminate\Support\Facades\Route;

/*
 * Chat (Norban's chat module, WhatsApp style): one's chats, the company's
 * people, a chat's messages and every change to them, a group's people, and
 * the files and photos — reached by being in the chat, not by a permission.
 */

Route::middleware(['api.auth', 'throttle:api'])->prefix('chat')->name('chat.')->group(function (): void {
    Route::get('/chats', [ChatApiController::class, 'index'])->name('chats.index');
    Route::get('/contacts', [ChatApiController::class, 'contacts'])->name('contacts');
    Route::get('/search', [ChatApiController::class, 'search'])->name('search');
    Route::get('/records', [ChatApiController::class, 'records'])->name('records');
    Route::get('/records/cards', [ChatApiController::class, 'recordCards'])->name('records.cards');
    Route::get('/push/key', [ChatApiController::class, 'pushKey'])->name('push.key');
    Route::post('/push/subscribe', [ChatApiController::class, 'pushSubscribe'])->name('push.subscribe');
    Route::post('/push/unsubscribe', [ChatApiController::class, 'pushUnsubscribe'])->name('push.unsubscribe');
    Route::post('/ping', [ChatApiController::class, 'ping'])->name('ping');
    Route::post('/chats/direct', [ChatApiController::class, 'direct'])->name('chats.direct');
    Route::post('/chats/group', [ChatApiController::class, 'group'])->name('chats.group');
    Route::post('/messages/forward', [ChatApiController::class, 'forward'])->name('messages.forward');

    Route::get('/chats/{chat}', [ChatApiController::class, 'show'])->name('chats.show');
    Route::get('/chats/{chat}/row', [ChatApiController::class, 'row'])->name('chats.row');
    Route::patch('/chats/{chat}', [ChatApiController::class, 'update'])->name('chats.update');
    Route::post('/chats/{chat}/image', [ChatApiController::class, 'image'])->name('chats.image');
    Route::get('/chats/{chat}/image', [ChatApiController::class, 'chatImage'])->name('chats.image.show');
    Route::get('/chats/{chat}/messages', [ChatApiController::class, 'messages'])->name('messages.index');
    Route::post('/chats/{chat}/messages', [ChatApiController::class, 'send'])->name('messages.store');
    Route::post('/chats/{chat}/read', [ChatApiController::class, 'read'])->name('chats.read');
    Route::post('/chats/{chat}/typing', [ChatApiController::class, 'typing'])->name('chats.typing');
    Route::patch('/chats/{chat}/settings', [ChatApiController::class, 'settings'])->name('chats.settings');
    Route::post('/chats/{chat}/clear', [ChatApiController::class, 'clear'])->name('chats.clear');
    Route::post('/chats/{chat}/members', [ChatApiController::class, 'addMembers'])->name('members.store');
    Route::patch('/chats/{chat}/members/{user}', [ChatApiController::class, 'role'])->name('members.role');
    Route::delete('/chats/{chat}/members/{user}', [ChatApiController::class, 'removeMember'])->name('members.destroy');

    Route::patch('/messages/{message}', [ChatApiController::class, 'edit'])->name('messages.update');
    Route::delete('/messages/{message}', [ChatApiController::class, 'destroy'])->name('messages.destroy');
    Route::post('/messages/{message}/react', [ChatApiController::class, 'react'])->name('messages.react');
    Route::post('/messages/{message}/pin', [ChatApiController::class, 'pin'])->name('messages.pin');
    Route::get('/messages/{message}/info', [ChatApiController::class, 'info'])->name('messages.info');
    Route::get('/messages/{message}/file', [ChatApiController::class, 'file'])->name('messages.file');
    Route::get('/avatars/{user}', [ChatApiController::class, 'avatar'])->name('avatars.show');
});

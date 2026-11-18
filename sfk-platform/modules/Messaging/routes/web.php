<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Middleware\EnsurePasswordIsChanged;
use Modules\Messaging\Http\Controllers\MessengerAssetController;
use Modules\Messaging\Http\Controllers\MessengerController;
use Modules\Messaging\Http\Controllers\MessengerFileController;
use Modules\Messaging\Http\Controllers\MessengerPulseController;

/*
| 社内メッセージ (docs/messaging.md). The page itself is the Filament page /erp/messages (MessengerPage); these are its
| JSON API, its files and its real-time pulse.
*/

// The messenger's stylesheet and script (versioned, kept by the browser).
Route::get('messaging/assets/messenger.css', [MessengerAssetController::class, 'css'])->withoutMiddleware('web')->name('messaging.assets.css');
Route::get('messaging/assets/messenger.js', [MessengerAssetController::class, 'js'])->withoutMiddleware('web')->name('messaging.assets.js');

// Real time: the open messenger's pulse and 入力中 — without the web middleware (no session, no database), signed per
// user and conversation (MessengerPulse).
$pulse = ['user' => '[0-9]+', 'conversation' => '[0-9]+', 'peer' => '[0-9]+', 'key' => '[0-9a-f]{40}'];
Route::get('messaging/pulse/{user}/{conversation}/{peer}/{key}', [MessengerPulseController::class, 'show'])
    ->withoutMiddleware('web')->where($pulse)->name('messaging.pulse');
Route::post('messaging/pulse/{user}/{conversation}/{peer}/{key}', [MessengerPulseController::class, 'typing'])
    ->withoutMiddleware('web')->where($pulse)->name('messaging.pulse.typing');

Route::middleware(['auth', EnsurePasswordIsChanged::class])->prefix('messaging')->name('messaging.')->group(function (): void {
    // files: only after the permission check (MessengerFileController)
    Route::get('attachments/{attachment}', [MessengerFileController::class, 'attachment'])->whereNumber('attachment')->name('attachments.show');
    Route::get('conversations/{conversation}/avatar', [MessengerFileController::class, 'avatar'])->whereNumber('conversation')->name('conversations.avatar');
    Route::get('announcements/files/{file}', [MessengerFileController::class, 'announcementFile'])->whereNumber('file')->name('announcements.file');
    Route::get('stickers/{sticker}', [MessengerFileController::class, 'sticker'])->whereNumber('sticker')->name('stickers.show');

    Route::prefix('api')->group(function (): void {
        Route::get('people', [MessengerController::class, 'people'])->name('people');
        Route::get('inbox', [MessengerController::class, 'inbox'])->name('inbox');
        Route::get('unread', [MessengerController::class, 'unread'])->name('unread');
        Route::get('search', [MessengerController::class, 'search'])->middleware('throttle:60,1')->name('search');
        Route::match(['get', 'post'], 'preferences', [MessengerController::class, 'preferences'])->name('preferences');

        Route::middleware('throttle:messaging-write')->group(function (): void {
            Route::post('conversations/direct', [MessengerController::class, 'direct'])->name('direct');
            Route::post('conversations', [MessengerController::class, 'createGroup'])->name('groups.store');
            Route::post('channels', [MessengerController::class, 'createChannel'])->name('channels.store');
            Route::post('channels/{channel}/join', [MessengerController::class, 'join'])->whereNumber('channel')->name('channels.join');
            Route::post('channels/{channel}/archive', [MessengerController::class, 'archive'])->whereNumber('channel')->name('channels.archive');
            Route::post('read-all', [MessengerController::class, 'readAll'])->name('read-all');
            Route::post('pins/order', [MessengerController::class, 'pinOrder'])->name('pins.order');
        });
        Route::get('channels', [MessengerController::class, 'channels'])->name('channels.index');
        Route::get('records/apps', [MessengerController::class, 'recordApps'])->name('records.apps');
        Route::get('records/search', [MessengerController::class, 'recordSearch'])->middleware('throttle:60,1')->name('records.search');

        Route::prefix('conversations/{conversation}')->whereNumber('conversation')->group(function (): void {
            Route::get('/', [MessengerController::class, 'show'])->name('conversations.show');
            Route::get('messages', [MessengerController::class, 'history'])->name('conversations.history');
            Route::get('sync', [MessengerController::class, 'sync'])->name('conversations.sync');
            Route::get('reads', [MessengerController::class, 'reads'])->name('conversations.reads');
            Route::get('details', [MessengerController::class, 'details'])->name('conversations.details');
            Route::get('shared', [MessengerController::class, 'shared'])->name('conversations.shared');
            Route::get('links', [MessengerController::class, 'links'])->name('conversations.links');
            Route::get('date', [MessengerController::class, 'date'])->name('conversations.date');
            Route::post('unread', [MessengerController::class, 'unreadOne'])->name('conversations.unread');
            Route::post('read', [MessengerController::class, 'read'])->name('conversations.read');
            Route::post('messages', [MessengerController::class, 'send'])->middleware('throttle:messaging-send')->name('conversations.send');
            Route::post('attachments', [MessengerController::class, 'upload'])->middleware('throttle:messaging-upload')->name('conversations.upload');
            Route::middleware('throttle:messaging-write')->group(function (): void {
                Route::post('state', [MessengerController::class, 'state'])->name('conversations.state');
                Route::post('info', [MessengerController::class, 'info'])->name('conversations.info');
                Route::post('avatar', [MessengerController::class, 'avatar'])->name('conversations.avatar.update');
                Route::post('settings', [MessengerController::class, 'settings'])->name('conversations.settings');
                Route::post('members', [MessengerController::class, 'addMembers'])->name('conversations.members.add');
                Route::delete('members/{user}', [MessengerController::class, 'removeMember'])->whereNumber('user')->name('conversations.members.remove');
                Route::post('members/{user}/role', [MessengerController::class, 'role'])->whereNumber('user')->name('conversations.members.role');
                Route::post('leave', [MessengerController::class, 'leave'])->name('conversations.leave');
            });
        });

        Route::delete('attachments/{attachment}', [MessengerController::class, 'discard'])->whereNumber('attachment')->name('attachments.discard');

        Route::get('messages/{message}/info', [MessengerController::class, 'messageInfo'])->whereNumber('message')->name('messages.info');
        Route::prefix('messages/{message}')->whereNumber('message')->middleware('throttle:messaging-write')->group(function (): void {
            Route::patch('/', [MessengerController::class, 'edit'])->name('messages.edit');
            Route::post('unsend', [MessengerController::class, 'unsend'])->name('messages.unsend');
            Route::post('hide', [MessengerController::class, 'hide'])->name('messages.hide');
            Route::post('react', [MessengerController::class, 'react'])->name('messages.react');
            Route::post('pin', [MessengerController::class, 'pin'])->name('messages.pin');
            Route::post('forward', [MessengerController::class, 'forward'])->name('messages.forward');
            Route::post('moderate', [MessengerController::class, 'moderate'])->name('messages.moderate');
        });

        Route::get('announcements', [MessengerController::class, 'announcements'])->name('announcements.index');
        Route::post('announcements', [MessengerController::class, 'publish'])->middleware('throttle:messaging-write')->name('announcements.store');
        Route::get('announcements/{announcement}', [MessengerController::class, 'announcement'])->whereNumber('announcement')->name('announcements.show');
        Route::post('announcements/{announcement}/confirm', [MessengerController::class, 'confirm'])->whereNumber('announcement')->name('announcements.confirm');

        Route::post('stickers', [MessengerController::class, 'addSticker'])->middleware('throttle:messaging-write')->name('stickers.store');
        Route::delete('stickers/{sticker}', [MessengerController::class, 'removeSticker'])->whereNumber('sticker')->name('stickers.destroy');
    });
});

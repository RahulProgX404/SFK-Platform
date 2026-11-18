<?php

/*
| Messaging module configuration — config('messaging.*'). Company policy lives here (docs/messaging.md).
*/

return [
    // メッセージの編集 / 送信取消 (unsend for everyone): allowed on one's own messages for this many minutes (null = always)
    'edit_window_minutes' => (int) env('MESSAGING_EDIT_WINDOW_MINUTES', 24 * 60),
    'unsend_window_minutes' => (int) env('MESSAGING_UNSEND_WINDOW_MINUTES', 24 * 60),

    // who may create groups / channels (部署・プロジェクト): 'everyone' | 'administrators' (メッセージ管理 can change it)
    'group_creators' => env('MESSAGING_GROUP_CREATORS', 'everyone'),
    'channel_creators' => env('MESSAGING_CHANNEL_CREATORS', 'administrators'),

    // company policy: members see who read their messages (既読); employees see each other's オンライン状態
    'read_receipts' => true,
    'presence' => true,

    // the most members a group may have
    'max_group_members' => 500,

    // longest text of one message (characters)
    'max_message_length' => 5000,

    // attachments (Uploads::disk(), private; served only through the authorized download route)
    'attachments' => [
        'max_kb' => (int) env('MESSAGING_MAX_ATTACHMENT_KB', 20 * 1024),
        'max_files' => 10,
        // extension => kind; the content is checked against the extension (finfo) on upload
        'types' => [
            'jpg' => 'image', 'jpeg' => 'image', 'png' => 'image', 'webp' => 'image', 'gif' => 'image',
            'pdf' => 'file', 'docx' => 'file', 'xlsx' => 'file', 'csv' => 'file', 'txt' => 'file',
            'webm' => 'voice', 'ogg' => 'voice', 'm4a' => 'voice', 'mp4' => 'voice',
        ],
        'voice_max_seconds' => 300,
        // uploads that were never sent are removed after this many hours (messaging:prune)
        'pending_hours' => 24,
    ],

    // the profile fields shown in a direct conversation's details (company policy): position, categories, email, phone
    'profile_fields' => ['position', 'categories'],

    // retention: messages older than this many days are deleted with their files by messaging:prune (null = keep)
    'retention_days' => env('MESSAGING_RETENTION_DAYS') !== null ? (int) env('MESSAGING_RETENTION_DAYS') : null,

    // real time without a WebSocket server (docs/messaging.md): how often an open messenger asks its pulse
    'pulse' => [
        'active_ms' => 1500,
        'background_ms' => 8000,
        // a user is 「オンライン」 while one of their pages asked a pulse within this many seconds
        'online_seconds' => 60,
        'typing_seconds' => 6,
    ],

    // rate limits per user and minute
    'limits' => [
        'send' => 60,
        'upload' => 30,
        'typing' => 40,
    ],
];

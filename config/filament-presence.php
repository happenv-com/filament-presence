<?php

declare(strict_types=1);

return [
    // Durable recorder driver: 'database' | 'activitylog' | 'null'.
    'recorder' => env('FILAMENT_PRESENCE_RECORDER', 'database'),

    // Table used by the database recorder.
    'table' => 'presence_sessions',

    // When true, every page collects presence unless it opts out; when false,
    // only pages whose shouldCollectPresence() returns true collect it.
    'default_opt_in' => true,

    // Show the current user's own avatar in the strip.
    'show_self' => false,

    // Max avatars before collapsing into a "+N" chip.
    'max_visible_avatars' => 5,

    // Avatar diameter in pixels.
    'avatar_size' => 22,

    // Client heartbeat cadence (seconds) and the server-side staleness cutoff.
    'heartbeat_interval' => 30,
    'stale_after_seconds' => 90,

    // Auth guard used by the HTTP log routes (null = application default guard).
    'guard' => null,

    // Default middleware group applied to the enter/heartbeat/leave routes.
    'middleware' => ['web', 'auth'],

    // URL prefix for the log routes.
    'route_prefix' => 'filament-presence',

    // Register the package's default (non-namespaced) presence channel. Hosts
    // that register their own channel (e.g. a tenant-namespaced one) should set
    // this to false so the bundled default does not authorize subscriptions.
    'register_default_channel' => true,
];

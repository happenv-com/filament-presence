<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Happenv\FilamentPresence\Models\PresenceSession;

it('presence:close-stale closes stale rows', function (): void {
    config()->set('filament-presence.stale_after_seconds', 90);
    app(PresenceSession::class)->newQuery()->create([
        'user_id' => 'u1', 'room_key' => 'r', 'url' => 'u', 'session_token' => 'stale',
        'entered_at' => CarbonImmutable::now()->subMinutes(10),
        'last_seen_at' => CarbonImmutable::now()->subMinutes(5),
    ]);

    $this->artisan('presence:close-stale')->assertExitCode(0);

    expect(app(PresenceSession::class)->newQuery()->where('session_token', 'stale')->sole()->left_at)->not->toBeNull();
});

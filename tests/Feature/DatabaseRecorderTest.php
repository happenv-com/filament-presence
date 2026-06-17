<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Happenv\FilamentPresence\Data\PresenceVisit;
use Happenv\FilamentPresence\Events\UserEnteredPage;
use Happenv\FilamentPresence\Events\UserLeftPage;
use Happenv\FilamentPresence\Models\PresenceSession;
use Happenv\FilamentPresence\Recorders\DatabaseRecorder;
use Illuminate\Support\Facades\Event;

afterEach(fn () => CarbonImmutable::setTestNow());

function aVisit(string $token = 'tok-1', string $user = 'user-1'): PresenceVisit
{
    return new PresenceVisit($user, 'products-abc', 'https://app/admin/products', 'Products', CarbonImmutable::parse('2026-06-16 10:00:00'), $token);
}

it('opens a row + fires UserEnteredPage on enter, closes with duration on leave', function (): void {
    Event::fake([UserEnteredPage::class, UserLeftPage::class]);
    $recorder = app(DatabaseRecorder::class);

    $recorder->entered(aVisit());
    expect(app(PresenceSession::class)->newQuery()->where('session_token', 'tok-1')->whereNull('left_at')->exists())->toBeTrue();
    Event::assertDispatched(UserEnteredPage::class);

    $recorder->left(aVisit(), CarbonImmutable::parse('2026-06-16 10:05:00'));
    expect(app(PresenceSession::class)->newQuery()->where('session_token', 'tok-1')->sole()->duration_seconds)->toBe(300);
    Event::assertDispatched(UserLeftPage::class, fn (UserLeftPage $e): bool => $e->durationSeconds === 300);
});

it('bumps last_seen_at on heartbeat', function (): void {
    $recorder = app(DatabaseRecorder::class);
    CarbonImmutable::setTestNow('2026-06-16 10:00:00');
    $recorder->entered(aVisit());
    $before = app(PresenceSession::class)->newQuery()->where('session_token', 'tok-1')->sole()->last_seen_at;

    CarbonImmutable::setTestNow('2026-06-16 10:01:00');
    $recorder->heartbeat(aVisit());
    $after = app(PresenceSession::class)->newQuery()->where('session_token', 'tok-1')->sole()->last_seen_at;

    expect($after->greaterThan($before))->toBeTrue();
});

it('closeStale closes only stale rows and scopes by user', function (): void {
    Event::fake([UserLeftPage::class]);
    $recorder = app(DatabaseRecorder::class);

    $recorder->entered(aVisit('fresh'));
    $recorder->entered(aVisit('stale'));
    app(PresenceSession::class)->newQuery()->where('session_token', 'stale')
        ->update(['last_seen_at' => CarbonImmutable::now()->subSeconds(300)]);

    expect($recorder->closeStale(90))->toBe(1);
    Event::assertDispatchedTimes(UserLeftPage::class, 1);

    // a different user cannot close someone else's row with the same token
    $recorder->entered(aVisit('shared', 'owner'));
    $recorder->left(aVisit('shared', 'attacker'), CarbonImmutable::now());
    expect(app(PresenceSession::class)->newQuery()->where('user_id', 'owner')->where('session_token', 'shared')->sole()->left_at)->toBeNull();
});

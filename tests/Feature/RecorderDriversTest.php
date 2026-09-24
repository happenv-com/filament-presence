<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Happenv\FilamentPresence\Contracts\PresenceRecorder;
use Happenv\FilamentPresence\Data\PresenceVisit;
use Happenv\FilamentPresence\Events\UserEnteredPage;
use Happenv\FilamentPresence\Models\PresenceSession;
use Happenv\FilamentPresence\Recorders\ActivityLogRecorder;
use Happenv\FilamentPresence\Recorders\DatabaseRecorder;
use Happenv\FilamentPresence\Recorders\NullRecorder;
use Illuminate\Support\Facades\Event;

it('selects the driver from config', function (): void {
    config()->set('filament-presence.recorder', 'database');
    app()->forgetInstance(PresenceRecorder::class);
    expect(app(PresenceRecorder::class))->toBeInstanceOf(DatabaseRecorder::class);

    config()->set('filament-presence.recorder', 'null');
    app()->forgetInstance(PresenceRecorder::class);
    expect(app(PresenceRecorder::class))->toBeInstanceOf(NullRecorder::class);
});

it('the null recorder persists nothing and fires no events', function (): void {
    Event::fake([UserEnteredPage::class]);
    $visit = new PresenceVisit('u1', 'r', 'u', null, CarbonImmutable::now(), 'tok');
    (new NullRecorder)->entered($visit);
    expect(app(PresenceSession::class)->newQuery()->count())->toBe(0)
        ->and((new NullRecorder)->closeStale(90))->toBe(0);
    Event::assertNotDispatched(UserEnteredPage::class);
});

it('the activitylog recorder refuses to construct without spatie/activitylog', function (): void {
    expect(function_exists('activity'))->toBeFalse();
    expect(fn (): ActivityLogRecorder => new ActivityLogRecorder)->toThrow(RuntimeException::class);
});

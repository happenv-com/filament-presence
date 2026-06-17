<?php

declare(strict_types=1);

namespace Happenv\FilamentPresence\Recorders;

use Carbon\CarbonImmutable;
use Happenv\FilamentPresence\Contracts\PresenceRecorder;
use Happenv\FilamentPresence\Data\PresenceVisit;
use Happenv\FilamentPresence\Events\UserEnteredPage;
use Happenv\FilamentPresence\Events\UserLeftPage;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Logs presence as spatie/activitylog activities. Open visits are tracked in the
 * cache so stale visits can still be closed without a dedicated table.
 */
class ActivityLogRecorder implements PresenceRecorder
{
    private const CACHE_PREFIX = 'filament-presence:open:';

    public function __construct()
    {
        if (! function_exists('activity')) {
            throw new RuntimeException('spatie/laravel-activitylog is required for the activitylog presence recorder.');
        }
    }

    public function entered(PresenceVisit $visit): void
    {
        Cache::put(self::CACHE_PREFIX . $visit->sessionToken, true, now()->addDay());

        activity('presence')
            ->event('entered')
            ->withProperties($this->properties($visit))
            ->log('entered');

        UserEnteredPage::dispatch($visit);
    }

    public function heartbeat(PresenceVisit $visit): void
    {
        if (Cache::has(self::CACHE_PREFIX . $visit->sessionToken)) {
            Cache::put(self::CACHE_PREFIX . $visit->sessionToken, true, now()->addDay());
        }
    }

    public function left(PresenceVisit $visit, CarbonImmutable $leftAt): void
    {
        if (! Cache::pull(self::CACHE_PREFIX . $visit->sessionToken)) {
            return;
        }

        $duration = max(0, $leftAt->getTimestamp() - $visit->enteredAt->getTimestamp());

        activity('presence')
            ->event('left')
            ->withProperties([...$this->properties($visit), 'leftAt' => $leftAt->toIso8601String(), 'durationSeconds' => $duration])
            ->log('left');

        UserLeftPage::dispatch($visit, $leftAt, $duration);
    }

    public function closeStale(int $thresholdSeconds): int
    {
        // Cache cannot be range-scanned portably; stale activitylog sessions are
        // closed by the client beforeunload/leave beacon. The database recorder is
        // the recommended driver when guaranteed stale-close is required.
        return 0;
    }

    /** @return array<string, mixed> */
    private function properties(PresenceVisit $visit): array
    {
        return [
            'userId' => $visit->userId,
            'roomKey' => $visit->roomKey,
            'url' => $visit->url,
            'label' => $visit->label,
            'enteredAt' => $visit->enteredAt->toIso8601String(),
        ];
    }
}

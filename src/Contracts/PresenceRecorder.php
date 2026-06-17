<?php

declare(strict_types=1);

namespace Happenv\FilamentPresence\Contracts;

use Carbon\CarbonImmutable;
use Happenv\FilamentPresence\Data\PresenceVisit;

interface PresenceRecorder
{
    public function entered(PresenceVisit $visit): void;

    public function heartbeat(PresenceVisit $visit): void;

    public function left(PresenceVisit $visit, CarbonImmutable $leftAt): void;

    /**
     * Close visits whose last heartbeat is older than the threshold.
     * Returns the number of visits closed.
     */
    public function closeStale(int $thresholdSeconds): int;
}

<?php

declare(strict_types=1);

namespace Happenv\FilamentPresence\Recorders;

use Carbon\CarbonImmutable;
use Happenv\FilamentPresence\Contracts\PresenceRecorder;
use Happenv\FilamentPresence\Data\PresenceVisit;

class NullRecorder implements PresenceRecorder
{
    public function entered(PresenceVisit $visit): void {}

    public function heartbeat(PresenceVisit $visit): void {}

    public function left(PresenceVisit $visit, CarbonImmutable $leftAt): void {}

    public function closeStale(int $thresholdSeconds): int
    {
        return 0;
    }
}

<?php

declare(strict_types=1);

namespace Happenv\FilamentPresence\Events;

use Carbon\CarbonImmutable;
use Happenv\FilamentPresence\Data\PresenceVisit;
use Illuminate\Foundation\Events\Dispatchable;

class UserLeftPage
{
    use Dispatchable;

    public function __construct(
        public PresenceVisit $visit,
        public CarbonImmutable $leftAt,
        public int $durationSeconds,
    ) {}
}

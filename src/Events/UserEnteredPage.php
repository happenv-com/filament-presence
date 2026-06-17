<?php

declare(strict_types=1);

namespace Happenv\FilamentPresence\Events;

use Happenv\FilamentPresence\Data\PresenceVisit;
use Illuminate\Foundation\Events\Dispatchable;

class UserEnteredPage
{
    use Dispatchable;

    public function __construct(public PresenceVisit $visit) {}
}

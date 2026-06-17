<?php

declare(strict_types=1);

namespace Happenv\FilamentPresence\Data;

use Carbon\CarbonImmutable;

final readonly class PresenceVisit
{
    public function __construct(
        public string $userId,
        public string $roomKey,
        public string $url,
        public ?string $label,
        public CarbonImmutable $enteredAt,
        public string $sessionToken,
    ) {}
}

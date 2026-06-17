<?php

declare(strict_types=1);

namespace Happenv\FilamentPresence\Contracts;

interface DerivesRoomKey
{
    /**
     * Derive a channel-safe room key (single segment, [A-Za-z0-9_-]) from a
     * request path. Implementations decide the granularity (e.g. per exact path).
     */
    public function roomKey(string $path): string;
}

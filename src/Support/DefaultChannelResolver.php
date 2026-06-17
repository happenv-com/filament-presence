<?php

declare(strict_types=1);

namespace Happenv\FilamentPresence\Support;

use Happenv\FilamentPresence\Contracts\ResolvesPresenceChannel;

class DefaultChannelResolver implements ResolvesPresenceChannel
{
    public function channelName(string $roomKey): string
    {
        return 'filament-presence.' . $roomKey;
    }
}

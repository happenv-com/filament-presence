<?php

declare(strict_types=1);

namespace Happenv\FilamentPresence\Contracts;

interface ResolvesPresenceChannel
{
    /**
     * The logical channel name passed to Echo.join (Echo adds the "presence-"
     * wire prefix itself — do NOT add it here). Hosts may prepend a tenant key.
     */
    public function channelName(string $roomKey): string;
}

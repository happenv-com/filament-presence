<?php

declare(strict_types=1);

namespace Happenv\FilamentPresence\Concerns;

trait InteractsWithPresence
{
    public function shouldCollectPresence(): bool
    {
        return (bool) config('filament-presence.default_opt_in', true);
    }
}

<?php

declare(strict_types=1);

namespace Happenv\FilamentPresence\Commands;

use Happenv\FilamentPresence\Contracts\PresenceRecorder;
use Illuminate\Console\Command;

class CloseStalePresenceSessions extends Command
{
    protected $signature = 'presence:close-stale';

    protected $description = 'Close presence sessions whose heartbeat has gone stale.';

    public function handle(PresenceRecorder $recorder): int
    {
        $closed = $recorder->closeStale((int) config('filament-presence.stale_after_seconds', 90));

        $this->info("Closed {$closed} stale presence session(s).");

        return self::SUCCESS;
    }
}

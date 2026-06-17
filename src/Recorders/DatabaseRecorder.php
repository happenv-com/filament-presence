<?php

declare(strict_types=1);

namespace Happenv\FilamentPresence\Recorders;

use Carbon\CarbonImmutable;
use Happenv\FilamentPresence\Contracts\PresenceRecorder;
use Happenv\FilamentPresence\Data\PresenceVisit;
use Happenv\FilamentPresence\Events\UserEnteredPage;
use Happenv\FilamentPresence\Events\UserLeftPage;
use Happenv\FilamentPresence\Models\PresenceSession;
use Illuminate\Database\Eloquent\Builder;

class DatabaseRecorder implements PresenceRecorder
{
    /**
     * Query the presence-session model, resolved from the container so a host can
     * swap in its own subclass (e.g. UUID keys, a different table).
     *
     * @return Builder<PresenceSession>
     */
    private function newQuery(): Builder
    {
        return app(PresenceSession::class)->newQuery();
    }

    public function entered(PresenceVisit $visit): void
    {
        // Scope by (session_token, user_id): the session token is client-supplied,
        // so keying on it alone would let one user overwrite another user's row.
        $this->newQuery()->updateOrCreate(
            ['session_token' => $visit->sessionToken, 'user_id' => $visit->userId],
            [
                'room_key' => $visit->roomKey,
                'url' => $visit->url,
                'label' => $visit->label,
                'entered_at' => $visit->enteredAt,
                'last_seen_at' => CarbonImmutable::now(),
                'left_at' => null,
                'duration_seconds' => null,
            ],
        );

        UserEnteredPage::dispatch($visit);
    }

    public function heartbeat(PresenceVisit $visit): void
    {
        $this->newQuery()
            ->where('session_token', $visit->sessionToken)
            ->where('user_id', $visit->userId)
            ->whereNull('left_at')
            ->update(['last_seen_at' => CarbonImmutable::now()]);
    }

    public function left(PresenceVisit $visit, CarbonImmutable $leftAt): void
    {
        $session = $this->newQuery()
            ->where('session_token', $visit->sessionToken)
            ->where('user_id', $visit->userId)
            ->whereNull('left_at')
            ->first();

        if ($session === null) {
            return;
        }

        $duration = $this->durationSeconds($session->entered_at, $leftAt);
        $session->update(['left_at' => $leftAt, 'duration_seconds' => $duration]);

        UserLeftPage::dispatch($visit, $leftAt, $duration);
    }

    public function closeStale(int $thresholdSeconds): int
    {
        $cutoff = CarbonImmutable::now()->subSeconds($thresholdSeconds);

        $stale = $this->newQuery()
            ->whereNull('left_at')
            ->where('last_seen_at', '<', $cutoff)
            ->get();

        foreach ($stale as $session) {
            // entered_at / last_seen_at are already CarbonImmutable (model casts).
            $leftAt = $session->last_seen_at;
            $duration = $this->durationSeconds($session->entered_at, $leftAt);
            $session->update(['left_at' => $leftAt, 'duration_seconds' => $duration]);

            UserLeftPage::dispatch(
                new PresenceVisit(
                    userId: (string) $session->user_id,
                    roomKey: (string) $session->room_key,
                    url: (string) $session->url,
                    label: $session->label,
                    enteredAt: $session->entered_at,
                    sessionToken: (string) $session->session_token,
                ),
                $leftAt,
                $duration,
            );
        }

        return $stale->count();
    }

    private function durationSeconds(CarbonImmutable $from, CarbonImmutable $to): int
    {
        return max(0, $to->getTimestamp() - $from->getTimestamp());
    }
}

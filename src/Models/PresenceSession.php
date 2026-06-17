<?php

declare(strict_types=1);

namespace Happenv\FilamentPresence\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Default presence-session model — a plain Eloquent model with the framework
 * default integer primary key. Resolve it from the container
 * (`app(PresenceSession::class)`) so a host can bind a subclass: e.g. one that
 * adds HasUuids and/or a different table. Never reference it statically.
 *
 * @property string $session_token
 * @property string $user_id
 * @property CarbonImmutable $entered_at
 * @property CarbonImmutable $last_seen_at
 * @property CarbonImmutable|null $left_at
 * @property int|null $duration_seconds
 */
class PresenceSession extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'entered_at' => 'immutable_datetime',
            'last_seen_at' => 'immutable_datetime',
            'left_at' => 'immutable_datetime',
            'duration_seconds' => 'integer',
        ];
    }

    public function getTable(): string
    {
        return config('filament-presence.table', 'presence_sessions');
    }
}

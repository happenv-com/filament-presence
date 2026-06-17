<?php

declare(strict_types=1);

namespace Happenv\FilamentPresence;

use Happenv\FilamentPresence\Contracts\ResolvesPresenceMember;
use Illuminate\Contracts\Auth\Authenticatable;

final class PresenceChannel
{
    /**
     * Presence channel authorization payload. MUST return an array of member
     * data (returning true silently fails for presence channels).
     *
     * @return array<string, mixed>
     */
    public static function authorize(Authenticatable $user, string $roomKey): array
    {
        return app(ResolvesPresenceMember::class)->resolve($user)->toArray();
    }
}

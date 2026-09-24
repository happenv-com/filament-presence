<?php

declare(strict_types=1);

namespace Happenv\FilamentPresence\Support;

use Happenv\FilamentPresence\Contracts\ResolvesPresenceMember;
use Happenv\FilamentPresence\Data\PresenceMember;
use Illuminate\Contracts\Auth\Authenticatable;

class DefaultMemberResolver implements ResolvesPresenceMember
{
    public function resolve(Authenticatable $user): PresenceMember
    {
        return new PresenceMember(
            id: (string) $user->getAuthIdentifier(),
            name: $this->name($user),
            avatarUrl: method_exists($user, 'getFilamentAvatarUrl') ? $user->getFilamentAvatarUrl() : null,
        );
    }

    private function name(Authenticatable $user): string
    {
        /** @phpstan-ignore-next-line dynamic host model property */
        return (string) ($user->name ?? $user->getAuthIdentifier());
    }
}

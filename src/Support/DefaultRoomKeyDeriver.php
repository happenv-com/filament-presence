<?php

declare(strict_types=1);

namespace Happenv\FilamentPresence\Support;

use Happenv\FilamentPresence\Contracts\DerivesRoomKey;

class DefaultRoomKeyDeriver implements DerivesRoomKey
{
    public function roomKey(string $path): string
    {
        return RoomKey::for($path);
    }
}

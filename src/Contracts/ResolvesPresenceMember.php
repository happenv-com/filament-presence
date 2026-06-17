<?php

declare(strict_types=1);

namespace Happenv\FilamentPresence\Contracts;

use Happenv\FilamentPresence\Data\PresenceMember;
use Illuminate\Contracts\Auth\Authenticatable;

interface ResolvesPresenceMember
{
    public function resolve(Authenticatable $user): PresenceMember;
}

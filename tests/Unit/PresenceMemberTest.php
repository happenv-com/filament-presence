<?php

declare(strict_types=1);

use Happenv\FilamentPresence\Data\PresenceMember;

it('exposes a broadcast-safe array payload', function (): void {
    expect((new PresenceMember('u1', 'Ada', 'https://cdn/a.png', 'https://app/u/1', 'viewing'))->toArray())
        ->toBe([
            'id' => 'u1',
            'name' => 'Ada',
            'avatarUrl' => 'https://cdn/a.png',
            'profileUrl' => 'https://app/u/1',
            'state' => 'viewing',
        ]);
});

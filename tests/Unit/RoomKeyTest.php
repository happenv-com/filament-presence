<?php

declare(strict_types=1);

use Happenv\FilamentPresence\Contracts\DerivesRoomKey;
use Happenv\FilamentPresence\Support\DefaultRoomKeyDeriver;
use Happenv\FilamentPresence\Support\RoomKey;

it('derives a stable channel-safe room key, ignoring query string', function (): void {
    expect(RoomKey::for('/admin/products'))->toMatch('/^[A-Za-z0-9_-]+$/')
        ->and(RoomKey::for('/admin/products'))->toBe(RoomKey::for('/admin/products'))
        ->and(RoomKey::for('/admin/products/1/edit'))->not->toBe(RoomKey::for('/admin/products/2/edit'))
        ->and(RoomKey::for('/a/b'))->not->toBe(RoomKey::for('/a-b'));
});

it('the default deriver delegates to RoomKey', function (): void {
    expect((new DefaultRoomKeyDeriver)->roomKey('/admin/products'))
        ->toBe(RoomKey::for('/admin/products'))
        ->and(app(DerivesRoomKey::class))->toBeInstanceOf(DefaultRoomKeyDeriver::class);
});

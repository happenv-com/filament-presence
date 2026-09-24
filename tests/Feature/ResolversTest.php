<?php

declare(strict_types=1);

use Happenv\FilamentPresence\Contracts\ResolvesPresenceChannel;
use Happenv\FilamentPresence\Contracts\ResolvesPresenceMember;
use Happenv\FilamentPresence\PresenceChannel;
use Happenv\FilamentPresence\Support\DefaultChannelResolver;
use Happenv\FilamentPresence\Support\DefaultMemberResolver;
use Illuminate\Foundation\Auth\User as AuthUser;

it('binds and resolves the default channel + member resolvers', function (): void {
    expect(app(ResolvesPresenceChannel::class))->toBeInstanceOf(DefaultChannelResolver::class)
        ->and(app(ResolvesPresenceMember::class))->toBeInstanceOf(DefaultMemberResolver::class)
        ->and(app(ResolvesPresenceChannel::class)->channelName('products-abc'))->toBe('filament-presence.products-abc');
});

it('authorize returns a member array (never true)', function (): void {
    $user = new class extends AuthUser
    {
        public $id = 'u1';

        public $name = 'Ada';

        public function getFilamentAvatarUrl(): string
        {
            return 'https://cdn/a.png';
        }
    };

    $payload = PresenceChannel::authorize($user, 'products-abc');

    expect($payload)->toBeArray()
        ->and($payload['id'])->toBe('u1')
        ->and($payload['name'])->toBe('Ada')
        ->and($payload['avatarUrl'])->toBe('https://cdn/a.png');
});

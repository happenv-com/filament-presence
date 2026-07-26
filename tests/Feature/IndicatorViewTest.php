<?php

declare(strict_types=1);

it('renders the indicator without compiling the @livewire directive', function (): void {
    $html = view('filament-presence::indicator', [
        'roomKey' => 'products-abc',
        'channelName' => 'filament-presence.products-abc',
        'url' => 'https://app/admin/products',
        'label' => 'Products',
        'currentUserId' => '1',
    ])->render();

    expect($html)->toContain('filamentPresence(')
        // Stale since the indicator moved off the `x-on:livewire:navigating`
        // attribute onto a `livewire:navigated` listener installed by start().
        ->and($html)->toContain("'livewire:navigated'")
        ->and($html)->not->toContain("app('livewire')->mount")
        ->and($html)->not->toContain('app("livewire")->mount')
        ->and($html)->toContain('x-tooltip.html.interactive="tooltipFor(member)"')
        ->and($html)->not->toContain('x-tooltip.raw')
        ->and($html)->toContain('visibilitychange')
        ->and($html)->toContain('statusRing(member)')
        ->and($html)->toContain('ring-warning-500');
});

describe('session expiry', function (): void {
    $render = fn (): string => view('filament-presence::indicator', [
        'roomKey' => 'products-abc',
        'channelName' => 'filament-presence.products-abc',
        'url' => 'https://app/admin/products',
        'label' => 'Products',
        'currentUserId' => '1',
    ])->render();

    it('ends the presence loop on an auth verdict, not on a failed fetch', function () use ($render): void {
        // The log routes sit behind `auth`: once the session dies they answer
        // 401/419 forever, and nothing used to stop the heartbeat — a tab left
        // open kept posting every heartbeat_interval until it was closed.
        // A rejected fetch is a transient blip and must stay in .catch().
        $html = $render();

        expect($html)->toContain('response.status === 401')
            ->and($html)->toContain('response.status === 419')
            ->and($html)->toContain('this.endSession()')
            ->and($html)->toContain('sessionEnded: false')
            ->and($html)->toContain('if (!url || this.sessionEnded) return')
            ->and($html)->toContain('filament-presence:session-expired');
    });

    it('reloads onto the login screen by default', function () use ($render): void {
        expect($render())->toContain('"reloadOnSessionExpiry":true');
    });

    it('leaves the reload to the host when switched off', function () use ($render): void {
        config()->set('filament-presence.reload_on_session_expiry', false);

        // The loop still stops and the event still fires — only the navigation
        // is handed back to the host application.
        expect($render())->toContain('"reloadOnSessionExpiry":false')
            ->and($render())->toContain('filament-presence:session-expired');
    });
});

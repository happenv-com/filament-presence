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
        ->and($html)->toContain('x-on:livewire:navigating')
        ->and($html)->not->toContain("app('livewire')->mount")
        ->and($html)->not->toContain('app("livewire")->mount')
        ->and($html)->toContain('x-tooltip.html.interactive="tooltipFor(member)"')
        ->and($html)->not->toContain('x-tooltip.raw')
        ->and($html)->toContain('visibilitychange')
        ->and($html)->toContain('statusRing(member)')
        ->and($html)->toContain('ring-warning-500');
});

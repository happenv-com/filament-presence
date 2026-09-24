<?php

declare(strict_types=1);

it('themes the tooltip like every Filament tooltip, with a link readable in both themes', function (): void {
    $html = view('filament-presence::indicator', [
        'roomKey' => 'products-abc',
        'channelName' => 'filament-presence.products-abc',
        'url' => 'https://app/admin/products',
        'label' => 'Products',
        'currentUserId' => '1',
    ])->render();

    // Without a theme Tippy falls back to its dark default, so the tooltip was
    // dark on a light panel while Filament's own tooltips follow $store.theme.
    expect($html)->toContain('theme: $store.theme')
        ->and($html)->toContain('class="text-primary-600 underline dark:text-primary-400"')
        ->and($html)->not->toContain('class="text-primary-400 underline"');
});

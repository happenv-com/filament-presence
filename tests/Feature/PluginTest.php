<?php

declare(strict_types=1);

use Filament\Panel;
use Filament\View\PanelsRenderHook;
use Happenv\FilamentPresence\FilamentPresencePlugin;

it('hooks the indicator in after the page heading', function (): void {
    $panel = Panel::make()->id('admin');

    FilamentPresencePlugin::make()->register($panel);

    // PAGE_HEADER_HEADING_AFTER is the reason for the Filament floor in
    // composer.json (4.11 / 5.6): older releases do not have the hook.
    $renderHooks = (fn (): array => $this->renderHooks)->call($panel);

    expect($renderHooks)->toHaveKey(PanelsRenderHook::PAGE_HEADER_HEADING_AFTER)
        ->and(FilamentPresencePlugin::make()->getId())->toBe('happenv-filament-presence');
});

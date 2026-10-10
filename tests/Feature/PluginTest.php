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

it('is visible unless a hideWhen condition holds', function (): void {
    $plugin = FilamentPresencePlugin::make();

    expect($plugin->isHidden())->toBeFalse()
        ->and($plugin->hideWhen(fn (): bool => true)->isHidden())->toBeTrue()
        ->and($plugin->hideWhen(fn (): bool => false)->isHidden())->toBeFalse();
});

it('renders no indicator while hidden', function (): void {
    $plugin = FilamentPresencePlugin::make()->hideWhen(fn (): bool => true);

    $render = new ReflectionMethod($plugin, 'render');

    expect($render->invoke($plugin))->toBe('');
});

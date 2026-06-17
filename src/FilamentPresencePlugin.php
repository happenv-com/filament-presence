<?php

declare(strict_types=1);

namespace Happenv\FilamentPresence;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\View\PanelsRenderHook;
use Happenv\FilamentPresence\Contracts\DerivesRoomKey;
use Happenv\FilamentPresence\Contracts\ResolvesPresenceChannel;
use Livewire\Livewire;

class FilamentPresencePlugin implements Plugin
{
    public function getId(): string
    {
        return 'happenv-filament-presence';
    }

    public function register(Panel $panel): void
    {
        $panel->renderHook(
            PanelsRenderHook::PAGE_HEADER_HEADING_AFTER,
            fn (): string => $this->render(),
        );
    }

    public function boot(Panel $panel): void {}

    public static function make(): static
    {
        return resolve(static::class);
    }

    private function render(): string
    {
        if (! $this->shouldCollect()) {
            return '';
        }

        $request = request();
        $roomKey = app(DerivesRoomKey::class)->roomKey($request->path());

        return view('filament-presence::indicator', [
            'roomKey' => $roomKey,
            'channelName' => app(ResolvesPresenceChannel::class)->channelName($roomKey),
            'url' => $request->fullUrl(),
            'label' => $this->currentLabel(),
            'currentUserId' => (string) (auth(config('filament-presence.guard'))->id() ?? ''),
        ])->render();
    }

    private function shouldCollect(): bool
    {
        $page = Livewire::current();

        if ($page !== null && method_exists($page, 'shouldCollectPresence')) {
            return (bool) $page->shouldCollectPresence();
        }

        return (bool) config('filament-presence.default_opt_in', true);
    }

    private function currentLabel(): ?string
    {
        $page = Livewire::current();

        foreach (['getHeading', 'getTitle'] as $method) {
            if ($page !== null && method_exists($page, $method)) {
                $value = rescue(fn (): string => (string) $page->{$method}(), '', false);

                if ($value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }
}

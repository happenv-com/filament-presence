<?php

declare(strict_types=1);

namespace Happenv\FilamentPresence;

use Closure;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\View\PanelsRenderHook;
use Happenv\FilamentPresence\Contracts\DerivesRoomKey;
use Happenv\FilamentPresence\Contracts\ResolvesPresenceChannel;
use Livewire\Livewire;

class FilamentPresencePlugin implements Plugin
{
    /** @var (Closure(): bool)|null */
    private ?Closure $hiddenWhen = null;

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

    /**
     * Renders no indicator (so nothing joins the channel or logs a visit) while the condition
     * holds — e.g. a support operator acting on someone else's behalf, who is not a colleague
     * browsing the resource. This only hides the client; refuse the log routes and the channel
     * for the same sessions on the server.
     *
     * @param  Closure(): bool  $condition
     */
    public function hideWhen(Closure $condition): static
    {
        $this->hiddenWhen = $condition;

        return $this;
    }

    public function isHidden(): bool
    {
        return $this->hiddenWhen instanceof Closure && ($this->hiddenWhen)();
    }

    private function render(): string
    {
        if ($this->isHidden() || ! $this->shouldCollect()) {
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

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
        ->and($html)->toContain('x-tooltip.html.interactive="{ content: tooltipFor(member), theme: $store.theme }"')
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

describe('page header layout', function (): void {
    $render = fn (): string => view('filament-presence::indicator', [
        'roomKey' => 'products-abc',
        'channelName' => 'filament-presence.products-abc',
        'url' => 'https://app/admin/products',
        'label' => 'Products',
        'currentUserId' => '1',
    ])->render();

    // Regression: seating the strip beside the heading must not move the page
    // description up next to the title. The first attempt turned the header's
    // content column into a wrapping flex row and pushed the subheading down with
    // `flex-basis: 100%` — which a max-width silently defeats, because a max-width
    // CLAMPS the flex base size that decides line breaks and Filament spells
    // `.fi-header-subheading` with `max-w-2xl`. Every header wider than
    // heading + 42rem therefore seated both on one line. Narrow viewports looked
    // right, so it shipped; the host application saw it on every page.
    it('leaves the header content column a block, so the subheading keeps its own row', function () use ($render): void {
        $html = $render();

        expect($html)->toContain('.fi-header > div:has(.fi-presence-strip) {
            display: block;
        }')
            // The declarations, not the prose: the comment above the rule names
            // both of them while explaining what went wrong.
            ->and($html)->not->toContain('flex-wrap: wrap;')
            ->and($html)->not->toContain('flex-basis: 100%;')
            ->and($html)->not->toContain('.fi-header-subheading {');
    });

    it('seats the heading and the indicator on one line as inline-level boxes', function () use ($render): void {
        expect($render())->toContain('.fi-header > div:has(.fi-presence-strip) > .fi-header-heading {
            display: inline-block;
            vertical-align: middle;
        }');
    });

    // An inline-block wrapper grows a line box around the strip (room below the
    // baseline for descenders); `vertical-align: middle` centred that taller box
    // and left the avatars 3px above the heading's centre. An inline-flex
    // wrapper is exactly as tall as the strip.
    it('centres the avatar strip on the heading', function () use ($render): void {
        expect($render())->toContain('.fi-header > div:has(.fi-presence-strip) > :has(.fi-presence-strip) {
            display: inline-flex;
            align-items: center;
            vertical-align: middle;
        }');
    });
});

describe('translations', function (): void {
    $render = fn (): string => view('filament-presence::indicator', [
        'roomKey' => 'products-abc',
        'channelName' => 'filament-presence.products-abc',
        'url' => 'https://app/admin/products',
        'label' => 'Products',
        'currentUserId' => '1',
    ])->render();

    it('passes the strings the browser renders in the app locale', function () use ($render): void {
        app()->setLocale('pl');
        $html = $render();

        preg_match('/<script type="application\/json" data-filament-presence-config>(.*?)<\/script>/s', $html, $carrier);

        expect(json_decode($carrier[1], true)['i18n'])->toBe(['goToView' => 'przejdź do tego widoku', 'user' => 'Użytkownik'])
            ->and($html)->toContain('this.config?.i18n?.goToView')
            ->and($html)->toContain('this.config?.i18n?.user');
    });

    it('ships every string in every locale Filament ships', function (string $locale): void {
        $english = require __DIR__ . '/../../resources/lang/en/presence.php';
        $file = __DIR__ . "/../../resources/lang/{$locale}/presence.php";

        expect($file)->toBeFile()
            ->and(array_keys(require $file))->toEqual(array_keys($english))
            ->and(array_filter(require $file))->toHaveCount(count($english));
    })->with(fn (): array => array_map(
        basename(...),
        glob(__DIR__ . '/../../vendor/filament/filament/resources/lang/*', GLOB_ONLYDIR) ?: [],
    ));
});

it('announces location and status once subscribed, and asks members for theirs', function (): void {
    $html = view('filament-presence::indicator', [
        'roomKey' => 'products-abc',
        'channelName' => 'filament-presence.products-abc',
        'url' => 'https://app/admin/products',
        'label' => 'Products',
        'currentUserId' => '1',
    ])->render();

    // A whisper sent before the subscription succeeds is dropped by the server,
    // so the announcements live in here(), not straight after Echo.join().
    $here = substr($html, strpos($html, '.here((users) => {'), 1200);

    expect($here)->toContain('this.ownStatus = this.currentStatus()')
        ->and($here)->toContain('this.announceLocation()')
        ->and($here)->toContain('this.announceStatus()')
        ->and($here)->toContain("whisper('state-requested'")
        // Members already in the room answer the request — `joining` does not
        // fire for them when this user has another tab open there.
        ->and($html)->toContain(".listenForWhisper('state-requested', () => {")
        ->and($html)->not->toContain("this.announceStatus()\n                        this.logEnter()");
});

<?php

declare(strict_types=1);

use Happenv\FilamentPresence\Concerns\InteractsWithPresence;

it('honors default_opt_in and per-page override', function (): void {
    $page = new class
    {
        use InteractsWithPresence;
    };

    config()->set('filament-presence.default_opt_in', true);
    expect($page->shouldCollectPresence())->toBeTrue();
    config()->set('filament-presence.default_opt_in', false);
    expect($page->shouldCollectPresence())->toBeFalse();

    $forced = new class
    {
        use InteractsWithPresence;

        public function shouldCollectPresence(): bool
        {
            return true;
        }
    };
    expect($forced->shouldCollectPresence())->toBeTrue();
});

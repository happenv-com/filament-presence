<?php

declare(strict_types=1);

use Happenv\FilamentPresence\Http\PresenceLogController;
use Illuminate\Support\Facades\Route;

// Uses the "filament-presence" middleware GROUP (not a config array) so the host
// can redefine the group — middleware groups resolve at dispatch time, making
// this independent of package/module service-provider boot order.
Route::middleware('filament-presence')
    ->prefix(config('filament-presence.route_prefix', 'filament-presence'))
    ->group(function (): void {
        Route::post('enter', [PresenceLogController::class, 'enter'])->name('filament-presence.enter');
        Route::post('heartbeat', [PresenceLogController::class, 'heartbeat'])->name('filament-presence.heartbeat');
        Route::post('leave', [PresenceLogController::class, 'leave'])->name('filament-presence.leave');
    });

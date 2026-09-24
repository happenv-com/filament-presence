<?php

declare(strict_types=1);

namespace Happenv\FilamentPresence;

use Happenv\FilamentPresence\Commands\CloseStalePresenceSessions;
use Happenv\FilamentPresence\Contracts\DerivesRoomKey;
use Happenv\FilamentPresence\Contracts\PresenceRecorder;
use Happenv\FilamentPresence\Contracts\ResolvesPresenceChannel;
use Happenv\FilamentPresence\Contracts\ResolvesPresenceMember;
use Happenv\FilamentPresence\Models\PresenceSession;
use Happenv\FilamentPresence\Recorders\ActivityLogRecorder;
use Happenv\FilamentPresence\Recorders\DatabaseRecorder;
use Happenv\FilamentPresence\Recorders\NullRecorder;
use Happenv\FilamentPresence\Support\DefaultChannelResolver;
use Happenv\FilamentPresence\Support\DefaultMemberResolver;
use Happenv\FilamentPresence\Support\DefaultRoomKeyDeriver;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Broadcast;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentPresenceServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-presence')
            ->hasConfigFile()
            ->hasViews('filament-presence')
            ->hasRoute('web')
            ->hasMigration('create_presence_sessions_table')
            ->hasCommand(CloseStalePresenceSessions::class);
    }

    public function packageRegistered(): void
    {
        // Swappable extension points — a host binds its own implementations
        // (e.g. tenant-aware channel naming, a UUID-keyed model) without
        // touching package code. bindIf so host bindings always win.
        $this->app->bindIf(ResolvesPresenceChannel::class, DefaultChannelResolver::class);
        $this->app->bindIf(ResolvesPresenceMember::class, DefaultMemberResolver::class);
        $this->app->bindIf(DerivesRoomKey::class, DefaultRoomKeyDeriver::class);
        $this->app->bindIf(PresenceSession::class, PresenceSession::class);
    }

    public function packageBooted(): void
    {
        $this->app->bind(PresenceRecorder::class, fn (): PresenceRecorder => match (config('filament-presence.recorder', 'database')) {
            'activitylog' => $this->app->make(ActivityLogRecorder::class),
            'null' => $this->app->make(NullRecorder::class),
            default => $this->app->make(DatabaseRecorder::class),
        });

        $router = $this->app->make(Router::class);
        if (! array_key_exists('filament-presence', $router->getMiddlewareGroups())) {
            $router->middlewareGroup('filament-presence', (array) config('filament-presence.middleware', ['web', 'auth']));
        }

        $this->registerDefaultChannel();
    }

    /**
     * Register the bundled presence channel (named to match DefaultChannelResolver;
     * Echo adds the `presence-` wire prefix). The authorization is gated at request
     * time by `register_default_channel`, so a host that registers its own channel
     * (e.g. a tenant-namespaced one) disables this default by setting the flag false
     * — independent of service-provider boot order.
     */
    private function registerDefaultChannel(): void
    {
        Broadcast::channel(
            'filament-presence.{roomKey}',
            function (Authenticatable $user, string $roomKey): array | false {
                if (! config('filament-presence.register_default_channel', true)) {
                    return false;
                }

                return PresenceChannel::authorize($user, $roomKey);
            },
        );
    }
}

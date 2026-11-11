---
name: filament-presence-development
description: >-
  Live "who's on this page" avatars and a page-visit log for Filament panels with
  happenv-com/filament-presence (Laravel Echo presence channels). Use when registering
  FilamentPresencePlugin (hideWhen()), wiring broadcasting/Echo for it, opting pages in or out
  (InteractsWithPresence, shouldCollectPresence(), default_opt_in), customising members, channels
  or room keys (ResolvesPresenceMember, PresenceMember, ResolvesPresenceChannel, PresenceChannel::authorize(),
  register_default_channel, DerivesRoomKey), multi-tenant channel authorization, the session log
  (PresenceRecorder, database / activitylog / null drivers, PresenceSession, UserEnteredPage /
  UserLeftPage events, presence:close-stale), or testing the log routes.
---

# Filament Presence

A Filament panel plugin that renders a stack of avatars next to every page heading, showing who else has the same page open (green = viewing, amber = tab in background). It has two independent layers:

- **Live**: an Echo presence channel per page ("room"). The browser joins it; the server only authorizes the channel and supplies each member's payload. No database.
- **Durable**: the browser posts `enter` / `heartbeat` / `leave` to package routes; a `PresenceRecorder` stores visits and dispatches `UserEnteredPage` / `UserLeftPage`. A scheduled command closes visits whose heartbeat stopped.

## When to use this skill

- Adding presence to a panel, or debugging "no avatars appear".
- Changing who may see whom (tenants, panels, impersonation), what a member shows, or how pages map to rooms.
- Reacting to visits (audit, analytics, "who viewed this record") through events or the `presence_sessions` table.
- Tests for the integration.

## Installation

```bash
composer require happenv-com/filament-presence
php artisan vendor:publish --tag=filament-presence-migrations   # for the default `database` recorder
php artisan migrate
```

```php
use Happenv\FilamentPresence\FilamentPresencePlugin;

$panel->plugin(FilamentPresencePlugin::make());
```

```css
/* the panel's custom theme CSS: required, the indicator uses Tailwind classes */
@source '../../../../vendor/happenv-com/filament-presence/resources/**/*.blade.php';
```

```php
// routes/console.php
Schedule::command('presence:close-stale')->everyMinute();
```

Requirements: PHP 8.3+, Laravel 11-13, Filament `^4.11 || ^5.6`.

### Broadcasting is the host's job

The indicator calls `window.Echo.join(...)`; without `window.Echo` it shows nothing (the visit log still works). The host must provide:

1. A broadcaster: Reverb, Pusher or Ably (`php artisan install:broadcasting`), with `BROADCAST_CONNECTION` set.
2. Broadcasting auth routes (`Broadcast::routes()` / `channels:` in `bootstrap/app.php`) behind the same guard as the panel, because presence channels are private.
3. Echo in the panel's page. Either publish Filament's config (`php artisan vendor:publish --tag=filament-config`) and fill `broadcasting.echo` in `config/filament.php` (Filament then creates `window.Echo` on panel pages, unless the panel calls `->broadcasting(false)`), or load your own Echo bundle into the panel (for example with a render hook or `FilamentAsset`).
4. Client events (whispers) allowed by the broadcaster. The away status and "go to their view" link travel as whispers; on Pusher enable "Client events" in the app settings.

## Configuration (`config/filament-presence.php`)

| Key | Default | Notes |
|---|---|---|
| `recorder` | `database` (env `FILAMENT_PRESENCE_RECORDER`) | `database`, `activitylog` (needs `spatie/laravel-activitylog`), `null`. |
| `table` | `presence_sessions` | Table of the database recorder and the migration. |
| `default_opt_in` | `true` | `false`: only pages that opt in show presence. |
| `show_self` | `false` | Show your own avatar. |
| `max_visible_avatars` / `avatar_size` | `5` / `22` | Then a `+N` chip. |
| `heartbeat_interval` / `stale_after_seconds` | `30` / `90` | Keep `stale_after_seconds` well above the interval. |
| `guard` | `null` | Guard used to resolve the user in the log routes. |
| `middleware` | `['web', 'auth']` | Becomes the `filament-presence` middleware group, unless the host already defines a group with that name. |
| `route_prefix` | `filament-presence` | Routes `filament-presence.enter` / `.heartbeat` / `.leave` (POST). |
| `reload_on_session_expiry` | `true` | On 401/419 the client stops and dispatches `filament-presence:session-expired` on `window`; with `true` it also reloads to the login screen. |
| `register_default_channel` | `true` | Set `false` when you register your own channel. |

For a panel on a non-default guard, set both `guard` and `middleware` (e.g. `['web', 'auth:admin']`).

## Per-page opt-in / opt-out

The plugin asks the current Livewire page for `shouldCollectPresence()`; pages without that method follow `default_opt_in`. The trait just provides the default:

```php
use Happenv\FilamentPresence\Concerns\InteractsWithPresence;

class EditOrder extends EditRecord
{
    use InteractsWithPresence;

    public function shouldCollectPresence(): bool
    {
        return true;   // e.g. with default_opt_in = false, only edit pages show presence
    }
}
```

Hide it per session (nothing joins, nothing is logged), e.g. while impersonating:

```php
FilamentPresencePlugin::make()->hideWhen(fn (): bool => session()->has('impersonated_by'));
```

`hideWhen()` only hides the client. Also refuse those sessions in the channel authorization and in the `filament-presence` middleware group, otherwise a hand-made request still gets through.

## Rooms, channels and members

- **Room key**: `DerivesRoomKey::roomKey($request->path())`. Default `RoomKey::for()`: slug of the path plus a 10-char hash, query string ignored. So `/admin/orders?tableFilters=...` and `/admin/orders` are one room, `/admin/orders/5/edit` is its own. Must stay `[A-Za-z0-9_-]`, one segment.
- **Channel name**: `ResolvesPresenceChannel::channelName($roomKey)`, default `filament-presence.{roomKey}`. Return the name **without** the `presence-` prefix; Echo adds it.
- **Member payload**: `ResolvesPresenceMember::resolve($user): PresenceMember` (`id`, `name`, `avatarUrl`, `profileUrl`, `state`). Default: auth identifier, `$user->name`, `getFilamentAvatarUrl()` when the model has it, no `profileUrl` (the avatar then links nowhere).

All three are bound with `bindIf`, so a host binding in a service provider's `register()` wins:

```php
use Filament\Facades\Filament;
use Happenv\FilamentPresence\Contracts\ResolvesPresenceMember;
use Happenv\FilamentPresence\Data\PresenceMember;
use Illuminate\Contracts\Auth\Authenticatable;

final class MemberResolver implements ResolvesPresenceMember
{
    public function resolve(Authenticatable $user): PresenceMember
    {
        return new PresenceMember(
            id: (string) $user->getAuthIdentifier(),
            name: $user->full_name,
            avatarUrl: Filament::getUserAvatarUrl($user),
            profileUrl: UserResource::getUrl('view', ['record' => $user]),
        );
    }
}

$this->app->bind(ResolvesPresenceMember::class, MemberResolver::class);
```

The payload is broadcast to everyone in the room: put nothing private in it.

### Authorization and multi-tenancy (important)

The bundled channel `filament-presence.{roomKey}` admits **every authenticated user** of the broadcasting guard to **every room**, and room keys are deterministic (derived from the path). That is fine for one panel of mutually trusted staff. With tenants, several panels sharing a guard, or customers on the same guard, register your own channel that checks access, and name it through a channel resolver:

```php
// AppServiceProvider::register()
$this->app->bind(ResolvesPresenceChannel::class, fn () => new class implements ResolvesPresenceChannel {
    public function channelName(string $roomKey): string
    {
        return 'tenant.' . Filament::getTenant()->getKey() . '.presence.' . $roomKey;
    }
});

// config/filament-presence.php
'register_default_channel' => false,

// routes/channels.php
use Happenv\FilamentPresence\PresenceChannel;

Broadcast::channel('tenant.{tenantId}.presence.{roomKey}', function ($user, string $tenantId, string $roomKey) {
    return $user->teams()->whereKey($tenantId)->exists()
        ? PresenceChannel::authorize($user, $roomKey)   // must return the member array
        : false;
});
```

- A presence channel callback must return the member **array** (or `false`); returning `true` silently breaks presence. Always go through `PresenceChannel::authorize()`.
- The default channel is always declared, but answers `false` while `register_default_channel` is `false`, regardless of provider boot order.
- The log routes take `roomKey` and `url` from the client. They are scoped to the authenticated user but not checked against what that user may open; do not treat `presence_sessions` as an authorization or access audit without your own checks.

## Session log and events

```php
use Happenv\FilamentPresence\Events\UserEnteredPage;
use Happenv\FilamentPresence\Events\UserLeftPage;

Event::listen(function (UserEnteredPage $event): void {
    $event->visit->userId;      // string
    $event->visit->roomKey;
    $event->visit->url;         // full URL incl. query, client-supplied
    $event->visit->label;       // page heading/title, may be null
    $event->visit->enteredAt;   // CarbonImmutable, server time
    $event->visit->sessionToken;
});

Event::listen(function (UserLeftPage $event): void {
    $event->leftAt;
    $event->durationSeconds;
});
```

- Events are synchronous and fire on every page view inside an HTTP request from the browser: queue heavy listeners (`ShouldQueue`).
- `database` recorder: one row per visit in `presence_sessions` (`user_id` string, `room_key`, `url`, `label`, `session_token`, `entered_at`, `last_seen_at`, `left_at`, `duration_seconds`), unique per `(user_id, session_token)`. Query it through the container, `app(PresenceSession::class)->newQuery()`, never `PresenceSession::query()` statically, so a bound subclass is respected. There is no `user` relation; compare `user_id` as a string.
- Custom table or UUID keys: bind a subclass, `$this->app->bind(PresenceSession::class, MyPresenceSession::class)`, and adjust the published migration.
- Stale visits (closed laptop, crash) are closed by `presence:close-stale` with `left_at = last_seen_at`, and `UserLeftPage` fires then. Without the schedule they stay open forever.
- `activitylog` recorder: logs `entered` / `left` in the `presence` log; open visits live in the cache. `closeStale()` is a no-op there, so a visit that never sent `leave` never gets a `left` entry. Use `database` when durations must be complete.
- `null`: avatars only, no events.
- Your own backend: implement `PresenceRecorder` (`entered`, `heartbeat`, `left`, `closeStale`) and bind it in your service provider's `boot()`, not `register()`: the package binds `PresenceRecorder` in its own `boot()` (not with `bindIf`) from `recorder`, which overwrites a binding made in `register()`. App providers boot after package providers, so a `boot()` binding wins.

## Testing

No test helpers ship; the routes and recorder are plain Laravel and easy to drive:

```php
use Happenv\FilamentPresence\Events\UserEnteredPage;
use Happenv\FilamentPresence\Events\UserLeftPage;
use Happenv\FilamentPresence\Models\PresenceSession;

it('logs a visit', function () {
    Event::fake([UserEnteredPage::class, UserLeftPage::class]);
    $user = User::factory()->create();
    $visit = ['sessionToken' => 'tab-1', 'roomKey' => 'admin-orders-0123456789', 'url' => 'https://app.test/admin/orders', 'label' => 'Orders'];

    $this->actingAs($user)->postJson(route('filament-presence.enter'), $visit)->assertOk();
    $this->actingAs($user)->postJson(route('filament-presence.leave'), $visit)->assertOk();

    Event::assertDispatched(UserEnteredPage::class, fn ($e) => $e->visit->userId === (string) $user->id);
    expect(app(PresenceSession::class)->newQuery()->whereNotNull('left_at')->count())->toBe(1);
});

it('closes stale visits', function () {
    // ... enter as above, then:
    $this->travel(5)->minutes();
    $this->artisan('presence:close-stale')->assertSuccessful();
});
```

- Test custom resolvers directly (`app(ResolvesPresenceMember::class)->resolve($user)->toArray()`, `app(ResolvesPresenceChannel::class)->channelName('x')`), and a custom channel callback through the broadcasting auth route or by calling the closure.
- With `recorder` = `null`, nothing is stored and no events fire.

## Do not

- Do not rely on the default channel in a multi-tenant app or when untrusted users share the guard.
- Do not prefix channel names with `presence-` in a resolver.
- Do not return `true` from a presence channel callback.
- Do not forget the `presence:close-stale` schedule with the `database` recorder.
- Do not reference `PresenceSession` statically.
- Do not put anything private in `PresenceMember`; every member of the room receives it.

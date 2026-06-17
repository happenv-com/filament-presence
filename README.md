# Filament Presence

Live "who's on this page" avatars for Filament panels, plus a durable session log
(enter/leave timestamps and dwell time) exposed as domain events.

Each page renders a stacked row of avatars next to its heading, updated in real time
as users open, leave, or switch away from the page. Built on Laravel broadcasting
(Echo presence channels), so it works with Reverb, Pusher, or Ably.

## Features

- Avatars injected next to the page heading on every page type (list / create / edit / custom) — no per-page wiring.
- Real-time join / leave via Echo presence channels.
- **Online / away** status ring (green / amber) driven by the Page Visibility API — switching tab or window marks a user away; closing the tab removes them.
- Tooltip with the user's name and an optional "go to their view" link (their exact URL, only shown when it differs from yours).
- Durable session log with swappable drivers (`database`, `activitylog`, or `null`) and `UserEnteredPage` / `UserLeftPage` events.
- Per-page opt-in/opt-out.
- RTL-aware layout and a configurable avatar size.
- Every moving part is resolved from the container, so you can swap channel naming, member data, the room-key strategy, the recorder, and the model without touching package code.

## Requirements

- PHP 8.3+
- Filament v4 or v5
- Laravel 11 / 12 / 13
- A configured Laravel Echo client (`window.Echo`) backed by Reverb, Pusher, or Ably, with broadcasting authentication set up.

## Installation

```bash
composer require happenv-com/filament-presence
```

The default recorder writes to a `presence_sessions` table. Publish and run the migration:

```bash
php artisan vendor:publish --tag=filament-presence-migrations
php artisan migrate
```

Optionally publish the config:

```bash
php artisan vendor:publish --tag=filament-presence-config
```

## Usage

Register the plugin on your panel:

```php
use Filament\Panel;
use Happenv\FilamentPresence\FilamentPresencePlugin;

public function panel(Panel $panel): Panel
{
    return $panel->plugin(FilamentPresencePlugin::make());
}
```

That's it — every page in the panel now shows presence avatars. By default member
data comes from the authenticated user's `getFilamentAvatarUrl()` and `name`.

## How it works

Two decoupled layers:

- **Live (ephemeral):** native Echo presence channels (`here` / `joining` / `leaving`) drive the avatars. No database involved.
- **Durable:** a `PresenceRecorder` is fed by lightweight `enter` / `heartbeat` / `leave` HTTP calls and records each visit. A scheduled command closes sessions whose heartbeat went stale (closed laptop, crash) so timestamps stay accurate even without a clean exit.

The two layers are independent: avatars keep working if the log backend is down, and
the log stays correct even when the browser never fires `beforeunload`.

## Configuration

`config/filament-presence.php`:

| Key | Default | Description |
| --- | --- | --- |
| `recorder` | `database` | Session-log driver: `database`, `activitylog`, or `null`. |
| `table` | `presence_sessions` | Table used by the database recorder. |
| `default_opt_in` | `true` | When `false`, presence is off unless a page opts in. |
| `show_self` | `false` | Show the current user's own avatar. |
| `max_visible_avatars` | `5` | Avatars shown before collapsing into a `+N` chip. |
| `avatar_size` | `22` | Avatar diameter in pixels. |
| `heartbeat_interval` | `30` | Client heartbeat cadence (seconds). |
| `stale_after_seconds` | `90` | Server-side cutoff for closing stale sessions. |
| `guard` | `null` | Auth guard for the log routes (`null` = default guard). |
| `middleware` | `['web', 'auth']` | Middleware group for the log routes. |
| `route_prefix` | `filament-presence` | URL prefix for the log routes. |
| `register_default_channel` | `true` | Register the bundled presence channel (disable if you register your own). |

## Per-page opt-in

Toggle presence per page with the `InteractsWithPresence` trait:

```php
use Happenv\FilamentPresence\Concerns\InteractsWithPresence;

class EditOrder extends EditRecord
{
    use InteractsWithPresence;

    public function shouldCollectPresence(): bool
    {
        return false; // disable presence on this page
    }
}
```

Without the trait, pages follow `default_opt_in`.

## Session log & events

The active recorder records every visit and dispatches plain Laravel events you can
listen to:

```php
use Happenv\FilamentPresence\Events\UserEnteredPage;
use Happenv\FilamentPresence\Events\UserLeftPage;

// UserEnteredPage->visit                       (userId, roomKey, url, label, enteredAt)
// UserLeftPage->visit, ->leftAt, ->durationSeconds
```

Drivers:

- `database` — open/close rows in `presence_sessions`; queryable, exact durations.
- `activitylog` — logs `entered` / `left` activities via `spatie/laravel-activitylog` (install it to use this driver).
- `null` — live avatars only, no persistence.

### Closing stale sessions

Schedule the cleanup command so ungraceful exits are still closed:

```php
// routes/console.php
Schedule::command('presence:close-stale')->everyMinute();
```

## Extension points

Bind your own implementations in a service provider; the package binds defaults with
`bindIf`, so your bindings always win.

| Contract / class | Purpose | Default |
| --- | --- | --- |
| `Contracts\ResolvesPresenceMember` | Build the presence payload (id, name, avatar, profile URL) from a user. | reads `getFilamentAvatarUrl()` + `name` |
| `Contracts\ResolvesPresenceChannel` | The logical channel name per request (inject a tenant prefix here). | `filament-presence.{roomKey}` |
| `Contracts\DerivesRoomKey` | Turn a request path into a channel-safe room key. | slug + hash of the path |
| `Contracts\PresenceRecorder` | The session-log backend. | selected via `recorder` config |
| `Models\PresenceSession` | The Eloquent model for the database recorder. | integer key; bind a subclass for UUIDs/custom table |

```php
$this->app->bind(
    \Happenv\FilamentPresence\Contracts\ResolvesPresenceMember::class,
    MyMemberResolver::class,
);
```

### Custom presence channel

The package registers `filament-presence.{roomKey}` out of the box. To use your own
channel (e.g. tenant-namespaced), set `register_default_channel` to `false` and
register it yourself, delegating authorization to the package:

```php
use Happenv\FilamentPresence\PresenceChannel;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('my-prefix.{roomKey}', fn ($user, string $roomKey) =>
    PresenceChannel::authorize($user, $roomKey)
);
```

Then return the matching name from your `ResolvesPresenceChannel` so the client
subscribes to the same channel.

## Testing

```bash
composer install
./vendor/bin/pest
```

The suite runs on Testbench with an in-memory SQLite database — no Filament panel
required.

## License

MIT.

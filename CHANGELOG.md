# Changelog

All notable changes to `filament-presence` are documented in this file. Each section is written automatically from the GitHub release notes when a release is published — do not edit it by hand.

## v1.1.2 - 2026-09-24

<!-- Release notes generated using configuration in .github/release.yml at 1.x -->
### What's Changed

#### Other

* ci: adopt the Happenv package standard by @webard in https://github.com/happenv-com/filament-presence/pull/3
* feat: translate the indicator into every language Filament ships by @webard in https://github.com/happenv-com/filament-presence/pull/4
* fix: share location and status once subscribed, and on request by @webard in https://github.com/happenv-com/filament-presence/pull/5
* fix: theme the tooltip like Filament's own by @webard in https://github.com/happenv-com/filament-presence/pull/6
* fix: centre the avatar strip on the page heading by @webard in https://github.com/happenv-com/filament-presence/pull/7

**Full Changelog**: https://github.com/happenv-com/filament-presence/compare/v1.1.1...v1.1.2

## v1.1.1 - 2026-08-21

## What's Changed

* fix: podtytuł strony wraca pod tytuł, zamiast siadać obok niego by @gorny-dev in https://github.com/happenv-com/filament-presence/pull/2

## New Contributors

* @gorny-dev made their first contribution in https://github.com/happenv-com/filament-presence/pull/2

**Full Changelog**: https://github.com/happenv-com/filament-presence/compare/v1.1.0...v1.1.1

## v1.1.0 - 2026-07-26

## Zatrzymanie pętli presence po wygaśnięciu sesji (#1)

Trasy `enter`/`heartbeat`/`leave` siedzą za middleware `auth`. Po wygaśnięciu sesji odpowiadały **401** (gość) albo **419** (nieświeży CSRF) i ciałem HTML przekierowania — w kółko, bo nic nie zatrzymywało `heartbeatTimer`. Karta zostawiona otwarta waliła w endpoint co `heartbeat_interval` aż do zamknięcia.

`post()` sprawdza teraz status odpowiedzi i przy werdykcie uwierzytelnienia kończy sesję: gasi timer, wychodzi z kanału, emituje na `window` zdarzenie `filament-presence:session-expired`, a domyślnie przeładowuje kartę — ląduje na ekranie logowania zamiast siedzieć na stronie, której każde żądanie i tak pada.

Odrzucony `fetch` (chwilowy brak sieci) świadomie **nie** kończy sesji — zostaje w `.catch()`.

### Nowa opcja konfiguracji

```php
// config/filament-presence.php
'reload_on_session_expiry' => true, // domyślnie

```
Hosty obsługujące wygaśnięcie po swojemu ustawiają `false` — pętla i tak się zatrzymuje, a zdarzenie i tak leci.

**MINOR, nie PATCH**: dochodzi klucz konfiguracji, nowe zdarzenie na `window` i zmiana zachowania runtime (przeładowanie). Wstecznie kompatybilne, `^1.0` łapie bez zmian.

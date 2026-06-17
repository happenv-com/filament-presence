<?php

declare(strict_types=1);

namespace Happenv\FilamentPresence\Http;

use Carbon\CarbonImmutable;
use Happenv\FilamentPresence\Contracts\PresenceRecorder;
use Happenv\FilamentPresence\Data\PresenceVisit;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PresenceLogController
{
    public function enter(Request $request, PresenceRecorder $recorder): JsonResponse
    {
        $recorder->entered($this->visit($request));

        return response()->json(['ok' => true]);
    }

    public function heartbeat(Request $request, PresenceRecorder $recorder): JsonResponse
    {
        $recorder->heartbeat($this->visit($request));

        return response()->json(['ok' => true]);
    }

    public function leave(Request $request, PresenceRecorder $recorder): JsonResponse
    {
        $recorder->left($this->visit($request), CarbonImmutable::now());

        return response()->json(['ok' => true]);
    }

    private function visit(Request $request): PresenceVisit
    {
        // Resolve the user up front: the route's auth guard and the configured
        // presence guard are separate config keys, so a host middleware/guard
        // split could otherwise leave a null user reaching the recorder.
        $user = $request->user(config('filament-presence.guard'));

        if ($user === null) {
            throw new AuthenticationException;
        }

        $data = $request->validate([
            'sessionToken' => ['required', 'string', 'max:64'],
            'roomKey' => ['required', 'string', 'max:191'],
            'url' => ['required', 'string', 'max:2048'],
            'label' => ['nullable', 'string', 'max:191'],
        ]);

        return new PresenceVisit(
            userId: (string) $user->getAuthIdentifier(),
            roomKey: $data['roomKey'],
            url: $data['url'],
            label: $data['label'] ?? null,
            // Server-authoritative: ignore any client-supplied timestamp so dwell
            // time cannot be spoofed by backdating the entry.
            enteredAt: CarbonImmutable::now(),
            sessionToken: $data['sessionToken'],
        );
    }
}

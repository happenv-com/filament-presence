@php
    $maxVisible = (int) config('filament-presence.max_visible_avatars', 5);
    $showSelf = (bool) config('filament-presence.show_self', false);
    $heartbeat = (int) config('filament-presence.heartbeat_interval', 30);
    $avatarSize = (int) config('filament-presence.avatar_size', 22);
    $routes = [
        'enter' => route('filament-presence.enter'),
        'heartbeat' => route('filament-presence.heartbeat'),
        'leave' => route('filament-presence.leave'),
    ];
@endphp

<div
    wire:ignore
    style="display: none"
    x-data="filamentPresence({
        channel: @js($channelName),
        roomKey: @js($roomKey),
        url: @js($url),
        label: @js($label),
        currentUserId: @js($currentUserId),
        maxVisible: @js($maxVisible),
        showSelf: @js($showSelf),
        heartbeatInterval: @js($heartbeat),
        avatarSize: @js($avatarSize),
        routes: @js($routes),
    })"
    x-init="start()"
    {{-- x-on: form (not @-shorthand) so Blade does not parse "@livewire" as its directive --}}
    x-on:livewire:navigating.window="stop()"
>
    {{-- Only the heading that actually holds our strip becomes a centered flex row
         (scoped via :has so other headings are untouched), so avatars line up with
         the heading's cap height instead of sitting on the text baseline. --}}
    <style>
        .fi-header-heading:has(.fi-presence-strip) {
            display: inline-flex;
            align-items: center;
        }
        /* Logical properties so the separator + spacing sit on the correct side
           in both LTR and RTL. The separator colour is derived from the heading
           text colour, so it adapts to light/dark automatically. */
        .fi-presence-strip {
            display: inline-flex;
            align-items: center;
            margin-inline-start: 1rem;
            padding-inline-start: 1rem;
            border-inline-start: 1px solid
                color-mix(in srgb, currentColor 20%, transparent);
        }
    </style>

    <template x-teleport=".fi-header-heading">
        <span
            class="fi-presence-strip inline-flex items-center"
            x-show="visibleMembers.length || overflowCount"
        >
            <template
                x-for="(member, index) in visibleMembers"
                :key="member.id"
            >
                <a
                    :href="safeUrl(member.profileUrl) || '#'"
                    {{-- no .raw: evaluate the expression (raw = literal string). .html renders the markup, .interactive keeps the "go to their view" link clickable --}}
                    x-tooltip.html.interactive="tooltipFor(member)"
                    {{-- 2px ring carries presence status: green = online (tab active),
                         amber = away (tab/window hidden but still connected). --}}
                    class="block rounded-full ring-2 transition hover:z-10 hover:scale-110"
                    :class="statusRing(member)"
                    {{-- logical negative margin so avatars overlap correctly in RTL too --}}
                    :style="`z-index:${20 - index};margin-inline-start:${index === 0 ? '0' : '-' + Math.round(config.avatarSize * 0.36) + 'px'}`"
                >
                    <template x-if="member.avatarUrl">
                        <img
                            :src="member.avatarUrl"
                            :alt="member.name"
                            class="rounded-full object-cover"
                            :style="avatarStyle"
                        />
                    </template>
                    <template x-if="!member.avatarUrl">
                        <span
                            class="flex items-center justify-center rounded-full bg-primary-500 leading-none font-medium text-white"
                            :style="avatarStyle +
                            `;font-size:${Math.round(config.avatarSize * 0.42)}px`"
                            x-text="member.initials"
                        ></span>
                    </template>
                </a>
            </template>

            <span
                x-show="overflowCount > 0"
                class="flex items-center justify-center rounded-full bg-gray-200 leading-none font-medium text-gray-700 ring-2 ring-white dark:bg-gray-700 dark:text-gray-200 dark:ring-gray-900"
                :style="avatarStyle +
                `;font-size:${Math.round(config.avatarSize * 0.42)}px;margin-inline-start:-${Math.round(config.avatarSize * 0.36)}px`"
                x-text="`+${overflowCount}`"
            ></span>
        </span>
    </template>
</div>

<script>
    window.filamentPresence =
        window.filamentPresence ||
        function (config) {
            return {
                members: [],
                config,
                sessionToken: null,
                presenceChannel: null,
                heartbeatTimer: null,
                joined: false,
                enteredAt: null,
                ownStatus: 'online',
                unloadHandler: null,
                popHandler: null,
                statusHandler: null,

                get filtered() {
                    return this.config.showSelf
                        ? this.members
                        : this.members.filter(
                              (m) =>
                                  String(m.id) !==
                                  String(this.config.currentUserId),
                          )
                },
                get visibleMembers() {
                    return this.filtered.slice(0, this.config.maxVisible)
                },
                get overflowCount() {
                    return Math.max(
                        0,
                        this.filtered.length - this.config.maxVisible,
                    )
                },
                get avatarStyle() {
                    const size = this.config.avatarSize
                    return `height:${size}px;width:${size}px`
                },

                start() {
                    if (this.joined || !window.Echo) return
                    this.joined = true
                    this.sessionToken =
                        (crypto.randomUUID && crypto.randomUUID()) ||
                        String(Date.now()) + Math.random().toString(16).slice(2)

                    this.presenceChannel = window.Echo.join(this.config.channel)
                        .here((users) => {
                            this.members = users.map((u) => this.decorate(u))
                        })
                        .joining((user) => {
                            if (!this.members.some((m) => m.id === user.id)) {
                                this.members.push(this.decorate(user))
                            }
                            // Re-announce so the newcomer learns our location + status.
                            this.announceLocation()
                            this.announceStatus()
                        })
                        .leaving((user) => {
                            this.members = this.members.filter(
                                (m) => m.id !== user.id,
                            )
                        })
                        .listenForWhisper('location-changed', (e) => {
                            const m = this.members.find(
                                (m) => String(m.id) === String(e.id),
                            )
                            if (m) {
                                m.url = e.url
                                m.label = e.label
                            }
                        })
                        .listenForWhisper('status-changed', (e) => {
                            const m = this.members.find(
                                (m) => String(m.id) === String(e.id),
                            )
                            if (m) m.status = e.status
                        })
                        .listenForWhisper('state-changed', (e) => {
                            const m = this.members.find(
                                (m) => String(m.id) === String(e.id),
                            )
                            if (m) m.state = e.state
                        })
                        .error((err) =>
                            console.warn('Presence channel error', err),
                        )

                    this.announceLocation()
                    this.ownStatus = this.currentStatus()
                    this.announceStatus()
                    this.logEnter()
                    this.heartbeatTimer = setInterval(
                        () => this.logHeartbeat(),
                        this.config.heartbeatInterval * 1000,
                    )
                    window.addEventListener(
                        'beforeunload',
                        (this.unloadHandler = () => this.logLeave()),
                    )
                    window.addEventListener(
                        'popstate',
                        (this.popHandler = () => {
                            this.config.url = window.location.href
                            this.announceLocation()
                        }),
                    )
                    // Tab/window visibility + focus drive the online/away status.
                    this.statusHandler = () => this.syncStatus()
                    document.addEventListener(
                        'visibilitychange',
                        this.statusHandler,
                    )
                    window.addEventListener('focus', this.statusHandler)
                    window.addEventListener('blur', this.statusHandler)
                },

                stop() {
                    if (!this.joined) return
                    this.joined = false
                    if (this.heartbeatTimer) clearInterval(this.heartbeatTimer)
                    if (this.unloadHandler)
                        window.removeEventListener(
                            'beforeunload',
                            this.unloadHandler,
                        )
                    if (this.popHandler)
                        window.removeEventListener('popstate', this.popHandler)
                    if (this.statusHandler) {
                        document.removeEventListener(
                            'visibilitychange',
                            this.statusHandler,
                        )
                        window.removeEventListener('focus', this.statusHandler)
                        window.removeEventListener('blur', this.statusHandler)
                    }
                    try {
                        window.Echo.leave(this.config.channel)
                    } catch (e) {}
                    this.logLeave()
                },

                announceLocation() {
                    if (!this.presenceChannel) return
                    this.presenceChannel.whisper('location-changed', {
                        id: this.config.currentUserId,
                        url: this.config.url,
                        label: this.config.label,
                    })
                },

                // 'online' only when this tab is both visible AND focused; switching
                // tab/window (still connected) reports 'away'.
                currentStatus() {
                    return document.visibilityState === 'visible' &&
                        document.hasFocus()
                        ? 'online'
                        : 'away'
                },
                announceStatus() {
                    const self = this.members.find(
                        (m) =>
                            String(m.id) === String(this.config.currentUserId),
                    )
                    if (self) self.status = this.ownStatus
                    if (!this.presenceChannel) return
                    this.presenceChannel.whisper('status-changed', {
                        id: this.config.currentUserId,
                        status: this.ownStatus,
                    })
                },
                syncStatus() {
                    const status = this.currentStatus()
                    if (status === this.ownStatus) return
                    this.ownStatus = status
                    this.announceStatus()
                },
                statusRing(member) {
                    return member.status === 'away'
                        ? 'ring-warning-500 dark:ring-warning-500'
                        : 'ring-success-500 dark:ring-success-500'
                },

                // Only allow same-origin http(s) URLs. Member urls/profileUrls can be
                // influenced by client whispers, so a `javascript:`/`data:` scheme must
                // never reach an href (XSS). Returns null for anything unsafe.
                safeUrl(url) {
                    if (!url) return null
                    try {
                        const parsed = new URL(url, window.location.origin)
                        if (
                            parsed.protocol !== 'http:' &&
                            parsed.protocol !== 'https:'
                        )
                            return null
                        if (parsed.origin !== window.location.origin)
                            return null
                        return parsed.href
                    } catch (e) {
                        return null
                    }
                },

                tooltipFor(member) {
                    const escape = (s) =>
                        String(s ?? '').replace(
                            /[&<>"]/g,
                            (c) =>
                                ({
                                    '&': '&amp;',
                                    '<': '&lt;',
                                    '>': '&gt;',
                                    '"': '&quot;',
                                })[c],
                        )
                    let html = `<span class="font-medium">${escape(member.name)}</span>`
                    const link = this.safeUrl(member.url)
                    const current = this.safeUrl(window.location.href)
                    // Only offer the link when they are somewhere different from us.
                    if (link && link !== current) {
                        html += `<br><a href="${escape(link)}" class="text-primary-400 underline">↗ ${escape(member.label || 'go to their view')}</a>`
                    }
                    return html
                },

                decorate(user) {
                    const name = user.name || 'User'
                    return {
                        ...user,
                        name,
                        url: user.url || null,
                        label: user.label || null,
                        // Assume online until their first status whisper says otherwise.
                        status: user.status || 'online',
                        state: user.state || 'viewing',
                        initials: name
                            .split(' ')
                            .map((p) => p[0])
                            .slice(0, 2)
                            .join('')
                            .toUpperCase(),
                    }
                },

                payload() {
                    return {
                        sessionToken: this.sessionToken,
                        roomKey: this.config.roomKey,
                        url: this.config.url,
                        label: this.config.label,
                    }
                },
                post(url, keepalive = false) {
                    return fetch(url, {
                        method: 'POST',
                        credentials: 'same-origin',
                        keepalive,
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                            'X-CSRF-TOKEN':
                                document.querySelector('meta[name=csrf-token]')
                                    ?.content || '',
                        },
                        body: JSON.stringify(this.payload()),
                    }).catch(() => {})
                },
                logEnter() {
                    this.enteredAt = new Date().toISOString()
                    this.post(this.config.routes.enter)
                },
                logHeartbeat() {
                    this.post(this.config.routes.heartbeat)
                },
                logLeave() {
                    this.post(this.config.routes.leave, true)
                },
            }
        }
</script>

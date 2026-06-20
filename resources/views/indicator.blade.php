@php
    $maxVisible = (int) config('filament-presence.max_visible_avatars', 5);
    $showSelf = (bool) config('filament-presence.show_self', false);
    $heartbeat = (int) config('filament-presence.heartbeat_interval', 30);
    $avatarSize = (int) config('filament-presence.avatar_size', 22);
    $config = [
        'channel' => $channelName,
        'roomKey' => $roomKey,
        'url' => $url,
        'label' => $label,
        'currentUserId' => $currentUserId,
        'maxVisible' => $maxVisible,
        'showSelf' => $showSelf,
        'heartbeatInterval' => $heartbeat,
        'avatarSize' => $avatarSize,
        // Mirror Filament's generate_href_html(): in SPA mode the tooltip link
        // navigates via wire:navigate (.hover when prefetching) instead of a full
        // page load.
        'spaMode' => \Filament\Support\Facades\FilamentView::hasSpaMode(),
        'spaPrefetch' => \Filament\Support\Facades\FilamentView::hasSpaPrefetching(),
        'routes' => [
            'enter' => route('filament-presence.enter'),
            'heartbeat' => route('filament-presence.heartbeat'),
            'leave' => route('filament-presence.leave'),
        ],
    ];
@endphp

{{-- Per-page config carrier. This element is re-rendered by the render hook on
     every page, so it is intentionally NOT inside @persist: after an SPA
     (wire:navigate) navigation the persisted indicator reads it to learn the new
     room / URL / heading and switch presence channels accordingly. --}}
<script type="application/json" data-filament-presence-config>@json($config)</script>

{{-- Registered once via @assets so the component definition + styles survive
     wire:navigate page swaps (a plain inline <script> is not re-executed on SPA
     navigation, which made the indicator silently die after navigating). --}}
@assets
    <style>
        /* The strip is rendered right after the heading (PAGE_HEADER_HEADING_AFTER)
           rather than teleported into it, so it survives Livewire morphs. To still
           sit inline with the heading, the header's content column becomes a flex
           row while breadcrumbs + subheading keep their own full-width rows. */
        .fi-header > div:has(.fi-presence-strip) {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
        }
        .fi-header > div:has(.fi-presence-strip) > .fi-breadcrumbs,
        .fi-header > div:has(.fi-presence-strip) > .fi-header-subheading {
            flex-basis: 100%;
        }

        .fi-presence-host {
            display: contents;
        }

        .fi-presence-strip {
            display: inline-flex;
            align-items: center;
            margin-inline-start: 1rem;
            padding-inline-start: 1rem;
            border-inline-start: 1px solid
                color-mix(in srgb, currentColor 20%, transparent);
        }

        [x-cloak] {
            display: none !important;
        }
    </style>

    <script>
        window.filamentPresence =
            window.filamentPresence ||
            function () {
                return {
                    config: null,
                    members: [],
                    presenceChannel: null,
                    sessionToken: null,
                    heartbeatTimer: null,
                    ownStatus: 'online',
                    currentUrl: null,
                    started: false,
                    navHandler: null,
                    urlHandler: null,
                    statusHandler: null,
                    unloadHandler: null,

                    get filtered() {
                        return this.config && this.config.showSelf
                            ? this.members
                            : this.members.filter(
                                  (m) =>
                                      String(m.id) !==
                                      String(this.config?.currentUserId),
                              )
                    },
                    get visibleMembers() {
                        return this.filtered.slice(0, this.config?.maxVisible ?? 5)
                    },
                    get overflowCount() {
                        return Math.max(
                            0,
                            this.filtered.length - (this.config?.maxVisible ?? 5),
                        )
                    },
                    get avatarStyle() {
                        const size = this.config?.avatarSize ?? 22
                        return `height:${size}px;width:${size}px`
                    },

                    // ---- lifecycle -------------------------------------------------

                    start() {
                        if (this.started) return
                        this.started = true

                        this.readConfig()
                        this.installGlobalListeners()

                        if (this.config && window.Echo) {
                            this.joinRoom()
                        }
                    },

                    // Read the current page's room/url/label from the (non-persisted)
                    // carrier rendered by the server for this page.
                    readConfig() {
                        const el = document.querySelector(
                            '[data-filament-presence-config]',
                        )
                        if (!el) return
                        try {
                            this.config = JSON.parse(el.textContent)
                        } catch (e) {}
                    },

                    joinRoom() {
                        if (!this.config || !window.Echo) return
                        this.sessionToken =
                            (crypto.randomUUID && crypto.randomUUID()) ||
                            String(Date.now()) +
                                Math.random().toString(16).slice(2)
                        this.members = []
                        this.currentUrl = window.location.href
                        this.config.url = window.location.href

                        this.presenceChannel = window.Echo.join(this.config.channel)
                            .here((users) => {
                                this.members = users.map((u) => this.decorate(u))
                            })
                            .joining((user) => {
                                if (
                                    !this.members.some(
                                        (m) => String(m.id) === String(user.id),
                                    )
                                ) {
                                    this.members.push(this.decorate(user))
                                }
                                this.announceLocation()
                                this.announceStatus()
                            })
                            .leaving((user) => {
                                this.members = this.members.filter(
                                    (m) => String(m.id) !== String(user.id),
                                )
                            })
                            .listenForWhisper('location-changed', (e) => {
                                const m = this.findMember(e.id)
                                if (m) {
                                    m.url = e.url
                                    m.label = e.label
                                }
                            })
                            .listenForWhisper('status-changed', (e) => {
                                const m = this.findMember(e.id)
                                if (m) m.status = e.status
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
                    },

                    leaveRoom() {
                        if (this.heartbeatTimer) {
                            clearInterval(this.heartbeatTimer)
                            this.heartbeatTimer = null
                        }
                        this.logLeave()
                        if (this.presenceChannel && this.config) {
                            try {
                                window.Echo.leave(this.config.channel)
                            } catch (e) {}
                            this.presenceChannel = null
                        }
                        this.members = []
                    },

                    // Called after each SPA navigation: switch to the new page's room
                    // (or just re-announce the URL when we stayed in the same room).
                    reconfigure() {
                        const previousChannel = this.config?.channel
                        this.readConfig()

                        if (!this.config) {
                            this.leaveRoom()
                            return
                        }

                        if (!window.Echo) return

                        if (this.config.channel !== previousChannel) {
                            this.leaveRoom()
                            this.joinRoom()
                        } else {
                            this.syncUrl()
                        }
                    },

                    installGlobalListeners() {
                        this.statusHandler = () => this.syncStatus()
                        document.addEventListener(
                            'visibilitychange',
                            this.statusHandler,
                        )
                        window.addEventListener('focus', this.statusHandler)
                        window.addEventListener('blur', this.statusHandler)

                        this.unloadHandler = () => this.logLeave()
                        window.addEventListener('beforeunload', this.unloadHandler)

                        // Filament tables write filters/sort/search into the query
                        // string via history.replaceState (Livewire #[Url]), which does
                        // NOT fire popstate. Patch both so teammates see the live URL.
                        this.patchHistory()
                        this.urlHandler = () => this.syncUrl()
                        window.addEventListener('popstate', this.urlHandler)
                        window.addEventListener(
                            'filament-presence:locationchange',
                            this.urlHandler,
                        )

                        this.navHandler = () => this.reconfigure()
                        document.addEventListener(
                            'livewire:navigated',
                            this.navHandler,
                        )
                    },

                    patchHistory() {
                        if (window.__filamentPresenceHistoryPatched) return
                        window.__filamentPresenceHistoryPatched = true
                        const fire = () =>
                            window.dispatchEvent(
                                new Event('filament-presence:locationchange'),
                            )
                        const wrap = (original) =>
                            function () {
                                const result = original.apply(this, arguments)
                                fire()
                                return result
                            }
                        history.pushState = wrap(history.pushState)
                        history.replaceState = wrap(history.replaceState)
                    },

                    syncUrl() {
                        if (!this.config) return
                        if (window.location.href === this.currentUrl) return
                        this.currentUrl = window.location.href
                        this.config.url = window.location.href
                        this.announceLocation()
                    },

                    // ---- presence whispers ----------------------------------------

                    findMember(id) {
                        return this.members.find(
                            (m) => String(m.id) === String(id),
                        )
                    },

                    announceLocation() {
                        if (!this.presenceChannel || !this.config) return
                        this.presenceChannel.whisper('location-changed', {
                            id: this.config.currentUserId,
                            url: this.config.url,
                            label: this.config.label,
                        })
                    },

                    // 'online' only when this tab is both visible AND focused;
                    // switching tab/window (still connected) reports 'away'.
                    currentStatus() {
                        return document.visibilityState === 'visible' &&
                            document.hasFocus()
                            ? 'online'
                            : 'away'
                    },
                    announceStatus() {
                        const self = this.findMember(this.config?.currentUserId)
                        if (self) self.status = this.ownStatus
                        if (!this.presenceChannel || !this.config) return
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

                    // Only allow same-origin http(s) URLs (member urls come from client
                    // whispers, so a javascript:/data: scheme must never reach an href).
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
                        if (link && link !== current) {
                            const nav = this.config?.spaMode
                                ? this.config?.spaPrefetch
                                    ? ' wire:navigate.hover'
                                    : ' wire:navigate'
                                : ''
                            html += `<br><a href="${escape(link)}"${nav} class="text-primary-400 underline">↗ ${escape(member.label || 'go to their view')}</a>`
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

                    // ---- server-side visit log ------------------------------------

                    payload() {
                        return {
                            sessionToken: this.sessionToken,
                            roomKey: this.config?.roomKey,
                            url: this.config?.url,
                            label: this.config?.label,
                        }
                    },
                    post(url, keepalive = false) {
                        if (!url) return
                        return fetch(url, {
                            method: 'POST',
                            credentials: 'same-origin',
                            keepalive,
                            headers: {
                                'Content-Type': 'application/json',
                                Accept: 'application/json',
                                'X-CSRF-TOKEN':
                                    document.querySelector(
                                        'meta[name=csrf-token]',
                                    )?.content || '',
                            },
                            body: JSON.stringify(this.payload()),
                        }).catch(() => {})
                    },
                    logEnter() {
                        this.post(this.config?.routes?.enter)
                    },
                    logHeartbeat() {
                        this.post(this.config?.routes?.heartbeat)
                    },
                    logLeave() {
                        this.post(this.config?.routes?.leave, true)
                    },
                }
            }
    </script>
@endassets

{{-- Persisted across wire:navigate so the Echo connection + avatars are not torn
     down and rebuilt (which caused the avatars to blink/disappear) on every page
     change. wire:ignore keeps Livewire's morph (filters/sort/search) from
     overwriting the live, Alpine-rendered strip. --}}
@persist('filament-presence-indicator')
    <div wire:ignore class="fi-presence-host" x-data="filamentPresence()" x-init="start()">
        <span
            class="fi-presence-strip inline-flex items-center"
            x-cloak
            x-show="visibleMembers.length || overflowCount"
        >
            <template x-for="(member, index) in visibleMembers" :key="member.id">
                <a
                    :href="safeUrl(member.profileUrl) || '#'"
                    x-tooltip.html.interactive="tooltipFor(member)"
                    class="block rounded-full ring-2 transition hover:z-10 hover:scale-110"
                    :class="statusRing(member)"
                    :style="`z-index:${20 - index};margin-inline-start:${index === 0 ? '0' : '-' + Math.round((config?.avatarSize ?? 22) * 0.36) + 'px'}`"
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
                            `;font-size:${Math.round((config?.avatarSize ?? 22) * 0.42)}px`"
                            x-text="member.initials"
                        ></span>
                    </template>
                </a>
            </template>

            {{-- x-if (not x-show) so the overflow badge is never in the DOM unless
                 there are genuinely more members than fit — avoids a stuck "+0". --}}
            <template x-if="overflowCount > 0">
                <span
                    class="flex items-center justify-center rounded-full bg-gray-200 leading-none font-medium text-gray-700 ring-2 ring-white dark:bg-gray-700 dark:text-gray-200 dark:ring-gray-900"
                    :style="avatarStyle +
                    `;font-size:${Math.round((config?.avatarSize ?? 22) * 0.42)}px;margin-inline-start:-${Math.round((config?.avatarSize ?? 22) * 0.36)}px`"
                    x-text="`+${overflowCount}`"
                ></span>
            </template>
        </span>
    </div>
@endpersist

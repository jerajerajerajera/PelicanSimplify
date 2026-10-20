<x-filament-panels::page>
@assets
<link id="simplify-dashboard-css" rel="stylesheet" href="{{ \Simplify\Panel\Services\BuildInfo::asset('dashboard.css') }}">
<script src="{{ \Simplify\Panel\Services\BuildInfo::asset('dashboard.js') }}"></script>
@endassets
<div class="ps" data-simplify-root x-cloak wire:ignore.self
     x-data="pelicanSimplify($wire, @js($this->initial()))"
     x-on:resize.window.debounce.150ms="applyScale(); restoreOverviewView()"
     :class="{'dark': themeDark, 'server-active': !!server, 'is-embedded': embedded}"
     :style="accentStyle(server?.uuid || 'overview')">
    @include('pelican-simplify::dashboard-icons')

    <main class="ps-main">
        {{-- Header ------------------------------------------------------------------------------ --}}
        <header class="ps-top">
            <div class="ps-heading">
                <div class="ps-identity">
                    <a :href="boot.list_url"><h1 class="ps-brand" :style="brandStyle()"><template x-if="brand().logo_url && brand().mode === 'logo'"><img class="ps-brand-logo" :src="brand().logo_url" alt="" :style="{ height: (Number(brand().logo_height) || 32) + 'px' }"></template><span class="ps-panel-name" x-show="!(brand().logo_url && brand().mode === 'logo') || brand().logo_text" :style="Simplify.brandTextStyle(brand(), brand().font_stack, boot.appearance?.accent_color)" x-text="(brand().text || '').trim() || boot.panel_name || 'Pelican'"></span><span class="ps-title-pelican" x-show="brand().suffix !== false">× Pelican</span><span class="ps-visually-hidden" x-show="brand().mode === 'logo' && !brand().logo_text" x-text="boot.panel_name || 'Pelican'"></span></h1></a>
                    <small>Enhanced by <a class="ps-project-link" href="https://github.com/jerajerajerajera/PelicanSimplify" target="_blank" rel="noopener noreferrer">Simplify</a></small>
                </div>
                <a x-show="server" :href="boot.list_url" class="ps-btn ps-back-overview">
                    <svg aria-hidden="true"><use href="#ps-back"/></svg><span>Back to server overview</span>
                </a>
            </div>
            <nav class="ps-top-actions" aria-label="Panel navigation" :class="{'nav-open': navMenu}"
                 x-on:click.outside="navMenu=false; accountMenu=false"
                 x-on:keydown.escape.stop="navMenu=false; accountMenu=false">
                <button type="button" class="ps-btn is-icon is-subtle ps-mobile-menu" aria-label="Panel navigation" aria-controls="ps-navigation-items" :aria-expanded="navMenu"
                        x-on:click="navMenu=!navMenu; accountMenu=false; appearanceMenu=false; notifyMenu=false">
                    <svg aria-hidden="true"><use href="#ps-menu"/></svg>
                </button>
                <div class="ps-nav-items" id="ps-navigation-items">
                    @if(\Simplify\Panel\Services\Extensions::web()) @include('server-directory::simplify.navigation') @endif
                    <a x-show="boot.admin_url" :href="server?.admin_server_url || boot.admin_url" class="ps-btn is-icon is-subtle" data-tip="Legacy panel" aria-label="Legacy panel">
                        <svg aria-hidden="true"><use href="#ps-legacy"/></svg><span class="ps-mobile-label">Legacy panel</span>
                    </a>
                    <button type="button" x-show="boot.settings_url" x-on:click="openPanelSettings()" class="ps-btn is-icon is-subtle" data-tip="Simplify settings" aria-label="Simplify settings" aria-haspopup="dialog">
                        <span class="ps-settings-mark" aria-hidden="true"><svg><use href="#ps-simplify"/></svg><svg class="ps-settings-gear"><use href="#ps-gear"/></svg></span><span class="ps-mobile-label">Simplify settings</span>
                    </button>

                    {{-- Admin tools: Pelican admin pages Simplify does not replace, opened in a popup (admins only). --}}
                    <div class="ps-notify" x-show="adminPages().length && !(hostMode && !server)" x-on:click.outside="adminMenu=false" x-on:keydown.escape.stop="adminMenu=false; $refs.adminButton.focus()">
                        <button type="button" x-ref="adminButton" class="ps-btn is-icon is-subtle" data-tip="Admin tools" aria-label="Admin tools" aria-controls="ps-admin-menu" :aria-expanded="adminMenu"
                                x-on:click="adminMenu=!adminMenu; notifyMenu=false; appearanceMenu=false; accountMenu=false">
                            <svg aria-hidden="true"><use href="#ps-shield"/></svg><span class="ps-mobile-label">Admin tools</span>
                        </button>
                        <div id="ps-admin-menu" class="ps-dropdown is-end ps-admin-menu" x-show="adminMenu" x-cloak x-transition.opacity>
                            <strong class="ps-dropdown-title">Admin tools</strong>
                            {{-- Same sections as host mode: coloured cards, Health with its checks and Legacy at the bottom. --}}
                            <div class="ps-admin-groups">
                                <template x-for="section in hostSections().filter((x) => !['health', 'legacy'].includes(x.key))" :key="section.key">
                                    <div class="ps-admin-group" :style="{'--ps-tone': section.color}">
                                        <span class="ps-admin-group-head"><span class="ps-host-rail-icon"><svg aria-hidden="true"><use :href="'#ps-'+section.icon"/></svg></span><span x-text="section.label"></span></span>
                                        <template x-for="item in section.pages" :key="item.key">
                                            <button type="button" class="ps-admin-item" x-on:click="openAdminPage(item)">
                                                <svg aria-hidden="true"><use :href="'#ps-'+item.icon"/></svg><span x-text="item.label"></span>
                                            </button>
                                        </template>
                                    </div>
                                </template>
                            </div>
                            <template x-if="adminHealthPage()">
                                <div class="ps-admin-health">
                                    <button type="button" class="ps-admin-health-title" x-on:click="openAdminPage(adminHealthPage())"><svg aria-hidden="true"><use href="#ps-heart"/></svg><span>Host health checks</span></button>
                                    <div class="ps-health-grid">
                                        <template x-for="check in healthChecks()" :key="check.label">
                                            <button type="button" class="ps-health-check" :class="'is-'+check.status" x-on:click="openAdminPage(adminHealthPage())"
                                                    :data-tip="check.label+(check.status==='ok' ? ': OK' : ': '+(check.message || check.status))" :aria-label="check.label+': '+check.status">
                                                <svg aria-hidden="true"><use :href="check.status==='ok' ? '#ps-check' : '#ps-alert'"/></svg>
                                            </button>
                                        </template>
                                    </div>
                                </div>
                            </template>
                            <template x-for="section in hostSections().filter((x) => x.key === 'legacy')" :key="section.key">
                                <div class="ps-admin-legacy">
                                    <span class="ps-label">Legacy</span>
                                    <template x-for="item in section.pages" :key="item.key">
                                        <button type="button" class="ps-btn is-sm is-subtle" x-on:click="openAdminPage(item)" x-text="item.label"></button>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- Notifications: alerts, pending changes and plugin updates in one dropdown. --}}
                    <div class="ps-notify" x-on:click.outside="notifyMenu=false" x-on:keydown.escape.stop="notifyMenu=false; $refs.notifyButton.focus()">
            {{-- A new notification drops down from the bell and slides back up after a few seconds. --}}
            <div class="ps-incoming" x-show="incomingShown" x-cloak role="status" aria-live="polite"
                 x-transition:enter="ps-incoming-move" x-transition:enter-start="is-up" x-transition:leave="ps-incoming-move" x-transition:leave-end="is-up"
                 x-on:mouseenter="clearTimeout(incomingTimer)" x-on:mouseleave="holdIncomingNotice(2500)">
                <template x-if="incomingNotice">
                    <div class="ps-notify-item" :class="'is-'+(incomingNotice.tone || 'info')">
                        <svg aria-hidden="true"><use :href="'#ps-'+(incomingNotice.tone==='ok'?'check':(incomingNotice.tone||'info')==='info'?'info':'alert')"/></svg>
                        <strong x-text="incomingNotice.title"></strong>
                        <small x-text="incomingNotice.text" x-show="incomingNotice.text"></small>
                        <span class="ps-notify-actions">
                            <template x-if="incomingNotice.action">
                                <a class="ps-btn is-sm" :href="incomingNotice.action.url" x-on:click="followNotice($event, {...incomingNotice, pelican: true})" x-text="incomingNotice.action.label"></a>
                            </template>
                            <button type="button" class="ps-btn is-sm is-subtle" x-on:click="hideIncomingNotice()">Dismiss</button>
                        </span>
                    </div>
                </template>
            </div>
                        <button type="button" x-ref="notifyButton" class="ps-btn is-icon is-subtle" data-tip="Notifications" aria-label="Notifications" aria-controls="ps-notify-menu" :aria-expanded="notifyMenu"
                                x-on:click="toggleNotifications()">
                            <svg aria-hidden="true"><use href="#ps-bell"/></svg><span class="ps-mobile-label">Notifications</span>
                            <span class="ps-notify-count" x-show="notifications().length" x-text="notifications().length"></span>
                        </button>
                        <div id="ps-notify-menu" class="ps-dropdown is-end ps-notify-menu" x-show="notifyMenu" x-cloak x-transition.opacity>
                            <div class="ps-notify-head">
                                <strong class="ps-dropdown-title">Notifications</strong>
                                <button type="button" class="ps-btn is-sm is-subtle" x-show="panelNotices.length" x-on:click="markPanelNoticesRead(panelNotices.map((n) => n.key))">Mark all read</button>
                            </div>
                            <div class="ps-notify-list">
                                <template x-for="item in notifications()" :key="item.id">
                                    <div class="ps-notify-item" :class="'is-'+item.tone">
                                        <svg aria-hidden="true"><use :href="'#ps-'+(item.tone==='ok'?'check':item.tone==='info'?'info':'alert')"/></svg>
                                        <strong x-text="item.title"></strong>
                                        <small x-text="item.text" x-show="item.text"></small>
                                        <small class="ps-notify-time" x-show="item.time" x-text="item.time"></small>
                                        <span class="ps-notify-actions" x-show="item.action || item.pelican">
                                            <template x-if="item.action">
                                                <a class="ps-btn is-sm" :href="item.action.url" x-on:click="followNotice($event, item)" x-text="item.action.label"></a>
                                            </template>
                                            <button type="button" class="ps-btn is-sm is-subtle" x-show="item.pelican" x-on:click="markPanelNoticesRead([item.key])">Mark read</button>
                                        </span>
                                    </div>
                                </template>
                                <p class="ps-empty" x-show="!notifications().length">You are all caught up.</p>
                            </div>
                            <div class="ps-dropdown-section" x-show="noticeHistory.length">
                                <span class="ps-label">Recent messages</span>
                                <div class="ps-notify-list">
                                    <template x-for="entry in noticeHistory" :key="entry.id">
                                        <div class="ps-notify-item" :class="'is-'+entry.tone">
                                            <svg aria-hidden="true"><use href="#ps-info"/></svg>
                                            <strong x-text="entry.text"></strong>
                                            <small x-text="entry.time"></small>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Personal appearance preferences. --}}
                    <div class="ps-overview-appearance" x-show="!server && !hostMode" x-on:click.outside="appearanceMenu=false" x-on:keydown.escape.stop="appearanceMenu=false; $refs.overviewAppearance.focus()">
                        <button type="button" x-ref="overviewAppearance" class="ps-btn is-icon is-subtle" data-tip="Customize overview" aria-label="Customize overview" aria-controls="ps-overview-appearance-menu" :aria-expanded="appearanceMenu"
                                x-on:click="appearanceMenu=!appearanceMenu; accountMenu=false; notifyMenu=false">
                            <svg aria-hidden="true"><use href="#ps-wand"/></svg><span class="ps-mobile-label">Customize overview</span>
                        </button>
                        <div id="ps-overview-appearance-menu" class="ps-dropdown is-end ps-overview-appearance-menu" x-show="appearanceMenu" x-cloak x-transition.opacity>
                            <strong class="ps-dropdown-title">Customize overview</strong>
                            <p class="ps-dropdown-hint">Only for you, on this device.</p>
                            <template x-if="!server">
                                <div class="ps-customize">
                                    <div class="ps-customize-row" x-show="!overviewCompact">
                                        <div><strong>Layout</strong><small>Cards in a grid, or one row per server</small></div>
                                        <div class="ps-segmented" role="group" aria-label="Layout">
                                            <template x-for="view in [{key:'grid',label:'Grid',icon:'grid'},{key:'list',label:'List',icon:'list'}]" :key="view.key">
                                                <button type="button" :aria-pressed="overviewView===view.key" x-on:click="setOverviewView(view.key)"><svg aria-hidden="true"><use :href="'#ps-'+view.icon"/></svg><span x-text="view.label"></span></button>
                                            </template>
                                        </div>
                                    </div>
                                    <div class="ps-customize-row">
                                        <div><strong>Card icon</strong><small>The big picture at the top of each card</small></div>
                                        <div class="ps-segmented" role="group" aria-label="Card icon">
                                            <button type="button" :aria-pressed="overviewPreferences.largeIcon==='server'" x-on:click="setOverviewPreference('largeIcon','server')">Server</button>
                                            <button type="button" :aria-pressed="overviewPreferences.largeIcon==='egg'" x-on:click="setOverviewPreference('largeIcon','egg')">Game</button>
                                        </div>
                                    </div>
                                    <div class="ps-customize-row">
                                        <div><strong>Card title</strong><small>Show the alias you gave it, or the real server name</small></div>
                                        <div class="ps-segmented" role="group" aria-label="Card title">
                                            <button type="button" :aria-pressed="overviewPreferences.largeName==='alias'" x-on:click="setOverviewPreference('largeName','alias')">Alias</button>
                                            <button type="button" :aria-pressed="overviewPreferences.largeName==='name'" x-on:click="setOverviewPreference('largeName','name')">Name</button>
                                        </div>
                                    </div>
                                    <div class="ps-customize-row">
                                        <div><strong>Group by node</strong><small>Auto groups servers per node once you have more than one; Always also shows a single node</small></div>
                                        <div class="ps-segmented" role="group" aria-label="Group by node">
                                            <template x-for="mode in [{key:'auto',label:'Auto'},{key:'always',label:'Always'},{key:'off',label:'Off'}]" :key="mode.key">
                                                <button type="button" :aria-pressed="overviewPreferences.groupByNode===mode.key" x-on:click="setOverviewPreference('groupByNode', mode.key)" x-text="mode.label"></button>
                                            </template>
                                        </div>
                                    </div>
                                    <span class="ps-customize-head">Show on each card</span>
                                    <template x-for="option in [{key:'showMotd',label:'Message of the day',hint:'The Minecraft MOTD under the server name'},{key:'showCpu',label:'CPU usage',hint:'A small live CPU graph'},{key:'showRam',label:'Memory usage',hint:'A small live RAM graph'},{key:'showQuickActions',label:'Quick actions on hover',hint:'Start, stop and restart buttons when you point at a card'}]" :key="option.key">
                                        <button type="button" class="ps-customize-switch" role="switch" :aria-checked="overviewPreferences[option.key]" x-on:click="setOverviewPreference(option.key, !overviewPreferences[option.key])">
                                            <span><strong x-text="option.label"></strong><small x-text="option.hint"></small></span>
                                            <span class="ps-switch" :class="{'is-on': overviewPreferences[option.key]}" aria-hidden="true"></span>
                                        </button>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- Account: avatar with inline profile and sign-out actions. --}}
                    <div class="ps-account" x-data="{ themeOpen: false }" x-effect="accountMenu || (themeOpen = false)" :class="{'account-open': accountMenu}" x-on:click.outside="accountMenu=false" x-on:keydown.escape.stop="accountMenu=false">
                        <button type="button" x-on:click="accountMenu=!accountMenu; appearanceMenu=false; notifyMenu=false" class="ps-btn ps-account-button" aria-label="Profile options" aria-controls="ps-account-actions" :aria-expanded="accountMenu">
                            <span class="ps-account-avatar" x-data="{failed:false}">
                                <img x-show="boot.avatar && !failed" :src="boot.avatar" alt="" x-on:error="failed=true">
                                <svg x-show="!boot.avatar || failed" aria-hidden="true"><use href="#ps-account"/></svg>
                            </span>
                            <span class="ps-account-username" x-text="boot.user"></span>
                        </button>
                        <div id="ps-account-actions" class="ps-account-menu" :style="{'--ps-account-count': (adminPages().length && !server ? 4 : 3) + (themeOpen ? 2 : 0)}" :inert="!accountMenu" :aria-hidden="!accountMenu">
                            <div class="ps-account-actions-inner">
                                <button type="button" class="ps-btn is-icon" x-on:click="openAccount()" data-tip="Profile settings" aria-label="Profile settings" aria-haspopup="dialog">
                                    <svg aria-hidden="true"><use href="#ps-account"/></svg>
                                </button>
                                {{-- Theme: the current choice; clicking it unfolds Light, Dark and System. --}}
                                <button type="button" class="ps-btn is-icon" x-show="!themeOpen" x-on:click="themeOpen=true" :data-tip="'Theme: '+(theme==='light' ? 'Light' : theme==='dark' ? 'Dark' : 'System')" aria-label="Choose theme" :aria-expanded="themeOpen">
                                    <svg aria-hidden="true"><use :href="theme==='light' ? '#ps-sun' : theme==='dark' ? '#ps-moon' : '#ps-monitor'"/></svg>
                                </button>
                                <template x-for="t in ['light','dark','system']" :key="t">
                                    <button type="button" class="ps-btn is-icon ps-theme-option" x-show="themeOpen" :aria-pressed="theme===t" x-on:click="setTheme(t); themeOpen=false" :data-tip="t==='light' ? 'Light' : t==='dark' ? 'Dark' : 'System'" :aria-label="t+' theme'">
                                        <svg aria-hidden="true"><use :href="t==='light' ? '#ps-sun' : t==='dark' ? '#ps-moon' : '#ps-monitor'"/></svg>
                                    </button>
                                </template>
                                <button type="button" class="ps-btn is-icon" x-show="adminPages().length && !server" x-on:click="setHostMode(!hostMode); accountMenu=false" :data-tip="hostMode ? 'Switch to simple mode' : 'Switch to host mode'" :aria-label="hostMode ? 'Switch to simple mode' : 'Switch to host mode'">
                                    <svg aria-hidden="true"><use :href="hostMode ? '#ps-grid' : '#ps-host'"/></svg>
                                </button>
                                <form method="post" action="{{ route('filament.app.auth.logout') }}">@csrf
                                    <button type="submit" class="ps-btn is-icon" data-tip="Sign out" aria-label="Sign out"><svg aria-hidden="true"><use href="#ps-logout"/></svg></button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </nav>
        </header>

        {{-- Host mode (admins, large hosts): server buttons per node, a colour-coded admin rail and the page. --}}
        <section class="ps-host" :class="{'has-server': !!hostServer}" x-show="!server && hostMode" x-cloak :style="accentStyle('overview')">
                <aside class="ps-host-servers" :class="{'is-open': hostServersOpen}" aria-label="Servers">
                    <button type="button" class="ps-btn is-sm ps-host-servers-toggle" x-on:click="hostServersOpen=!hostServersOpen" :aria-expanded="hostServersOpen"><svg aria-hidden="true"><use href="#ps-list"/></svg><span x-text="'Servers ('+(boot.servers||[]).length+')'"></span></button>
                    <div class="ps-host-server-list">
                        <template x-for="node in hostNodes()" :key="node.id">
                            <div class="ps-host-node">
                                <span class="ps-label"><svg aria-hidden="true"><use href="#ps-host"/></svg><span x-text="node.name"></span><small x-text="node.servers.length+(node.servers.length===1?' server':' servers')"></small></span>
                                <template x-for="s in node.servers" :key="s.uuid">
                                    <a class="ps-host-server" :href="s.linked ? s.url : null" :style="accentStyle(s.uuid)" :aria-disabled="!s.linked" :aria-current="hostServer?.uuid===s.uuid ? 'page' : null" x-on:click="if (s.linked && !$event.ctrlKey && !$event.metaKey && !$event.shiftKey) { $event.preventDefault(); openHostServer(s); }">
                                        <span class="ps-host-server-arrow" x-show="hostServer?.uuid===s.uuid" x-on:click.prevent.stop="closeHostServer()" role="button" aria-label="Close this server" data-tip="Close"><svg aria-hidden="true"><use href="#ps-arrow-right"/></svg></span>
                                        <span class="ps-host-server-top">
                                            <strong x-text="cardNames(s).large"></strong>
                                            <span class="ps-badge ps-status" :class="s.status"><i></i><span x-text="s.status"></span></span>
                                        </span>
                                        <span class="ps-host-server-meta">
                                            <span x-text="hostServerVersion(s)"></span>
                                            <span class="ps-host-players"><svg aria-hidden="true"><use href="#ps-players"/></svg><span x-text="playerCount(s)"></span></span>
                                        </span>
                                    </a>
                                </template>
                            </div>
                        </template>
                        <p class="ps-empty" x-show="!(boot.servers||[]).length">No servers yet.</p>
                    </div>
                </aside>
                <nav class="ps-host-rail" aria-label="Admin sections">
                    <span class="ps-label ps-host-rail-title">Admin</span>
                    <div class="ps-host-rail-items">
                        <template x-for="section in hostSections().filter((x) => x.key !== 'health')" :key="section.key">
                            <button type="button" class="ps-host-rail-item" :style="{'--ps-tone': section.color}" :aria-current="hostSection?.key===section.key ? 'page' : null" x-on:click="openHostSection(section)" :aria-label="section.label">
                                <span class="ps-host-rail-icon"><svg aria-hidden="true"><use :href="'#ps-'+section.icon"/></svg></span><span class="ps-host-rail-label" x-text="section.label"></span>
                            </button>
                        </template>
                    </div>
                    <template x-for="section in hostSections().filter((x) => x.key === 'health')" :key="section.key">
                        <button type="button" class="ps-host-rail-item is-health" :style="{'--ps-tone': section.color}" :aria-current="hostSection?.key===section.key ? 'page' : null" x-on:click="openHostSection(section)" :aria-label="section.label">
                            <span class="ps-host-rail-icon"><svg aria-hidden="true"><use :href="'#ps-'+section.icon"/></svg></span><span class="ps-host-rail-label" x-text="section.label"></span>
                        </button>
                    </template>
                    <div class="ps-health-grid" x-show="healthChecks().length" role="group" aria-label="Current health checks">
                        <template x-for="check in healthChecks()" :key="check.label">
                            <button type="button" class="ps-health-check" :class="'is-'+check.status" x-on:click="openHostSection(hostSections().find((x) => x.key === 'health'))"
                                    :data-tip="check.label+(check.status==='ok' ? ': OK' : ': '+(check.message || check.status))" :aria-label="check.label+': '+check.status">
                                <svg aria-hidden="true"><use :href="check.status==='ok' ? '#ps-check' : '#ps-alert'"/></svg>
                            </button>
                        </template>
                    </div>
                </nav>
                {{-- A server picked on the left opens here, over the admin pages; the cross or another server closes it. --}}
                <div class="ps-host-serverpane" x-show="hostServer" x-cloak :style="accentStyle(hostServer?.uuid || 'overview')">
                    <div class="ps-host-pane-actions">
                        <a class="ps-btn is-sm is-icon" :href="hostServer?.url" data-tip="Open full page" aria-label="Open full page"><svg aria-hidden="true"><use href="#ps-external"/></svg></a>
                        <button type="button" class="ps-btn is-sm is-icon" x-on:click="closeHostServer()" data-tip="Close" aria-label="Close server"><svg aria-hidden="true"><use href="#ps-close"/></svg></button>
                    </div>
                    <div class="ps-host-frame">
                        <template x-for="s in hostServer ? [hostServer] : []" :key="s.uuid">
                            <iframe :src="frameUrl(s.url)" :title="s.name"></iframe>
                        </template>
                    </div>
                </div>
                <div class="ps-host-main">
                    <header class="ps-host-head" x-show="hostSection">
                        <div class="ps-host-title"><span class="ps-host-rail-icon"><svg aria-hidden="true"><use :href="'#ps-'+(hostSection?.icon||'host')"/></svg></span>
                        <h2 x-text="hostSection?.label"></h2></div>
                        <div class="ps-segmented ps-host-subtabs" x-show="hostSection?.pages.length > 1" role="group" aria-label="Pages">
                            <template x-for="item in hostSection?.pages || []" :key="item.key">
                                <button type="button" :aria-pressed="hostPage?.key===item.key" x-on:click="setHostPage(item)"><svg aria-hidden="true"><use :href="'#ps-'+item.icon"/></svg><span x-text="item.label"></span></button>
                            </template>
                        </div>
                    </header>
                    <div class="ps-host-frame">
                        <template x-if="hostPage">
                            <iframe data-ps-frame="native" :data-ps-accent="currentAccentHex()" :src="frameUrl(hostPage.url)" :title="hostPage.label" x-on:load="styleFrame($el, 'native')"></iframe>
                        </template>
                    </div>
                </div>
        </section>

        {{-- Overview -------------------------------------------------------------------------------- --}}
        <section x-show="!server && !hostMode" class="ps-overview" :class="{'is-list': overviewView==='list'}" :style="{'--grid-columns': overviewColumns()}">
            <div class="ps-server-scroll" tabindex="0" role="region" aria-label="Servers">
                <div class="ps-server-stage" :class="{'is-list': overviewView==='list'}">
                    <div class="ps-overview-toolbar">
                        <div class="ps-create-tiles" x-show="boot.can_create">
                            <button type="button" x-show="canCreateGame('minecraft')" x-on:click="openCreate('minecraft')" class="ps-create-card is-minecraft" :style="creationAccent('minecraft')" aria-label="Create Minecraft server" aria-haspopup="dialog">
                                <span class="ps-create-plus"><svg aria-hidden="true"><use href="#ps-creeper"/></svg><span class="ps-create-add"><svg aria-hidden="true"><use href="#ps-plus"/></svg></span></span>
                                <h3><span>Create</span><strong>Minecraft</strong><span>server</span></h3>
                            </button>
                            <button type="button" x-show="!canCreateGame('native')" :disabled="!canCreateGame('other')" x-on:click="openCreate('other')" class="ps-create-card is-other" :style="creationAccent('other')" :aria-label="boot.game_create_url ? 'Create other server' : 'Create other server with Pelican\'s form'" aria-haspopup="dialog">
                                <span class="ps-create-plus"><svg aria-hidden="true"><use href="#ps-gamepad"/></svg><span class="ps-create-add"><svg aria-hidden="true"><use href="#ps-plus"/></svg></span></span>
                                <h3><span>Create</span><strong>Other</strong><span>server</span></h3>
                            </button>
                            {{-- Easy creator off: one tile for Pelican's native creation (administrators). --}}
                            <button type="button" x-show="canCreateGame('native')" x-on:click="openCreate('native')" class="ps-create-card is-native" :style="creationAccent('native')" aria-label="Create server with Pelican's creator" aria-haspopup="dialog">
                                <span class="ps-create-plus"><svg aria-hidden="true"><use href="#ps-host"/></svg><span class="ps-create-add"><svg aria-hidden="true"><use href="#ps-plus"/></svg></span></span>
                                <h3><span>Create</span><strong>New</strong><span>server</span></h3>
                            </button>
                        </div>
                        <div class="ps-game-filters" role="group" aria-label="Filter servers by game">
                            <button type="button" class="ps-chip" :aria-pressed="gameFilter==='all'" x-on:click="setGameFilter('all')">All</button>
                            <template x-for="game in gameOptions()" :key="game.key">
                                <button type="button" class="ps-chip" :aria-pressed="gameFilter===game.key" x-on:click="setGameFilter(game.key)" x-text="game.label"></button>
                            </template>
                        </div>
                        <div class="ps-node-filters" x-show="nodeOptions().length > 1" role="group" aria-label="Filter servers by node">
                            <input type="search" class="ps-input ps-node-search" x-show="nodeOptions().length > 3" x-model.debounce.200ms="nodeSearch" placeholder="Search nodes" aria-label="Search nodes">
                            <button type="button" class="ps-chip" :aria-pressed="nodeFilter==='all'" x-on:click="nodeFilter='all'"><svg aria-hidden="true"><use href="#ps-host"/></svg>All nodes</button>
                            <template x-for="node in nodeOptions().filter((n) => !nodeSearch || n.name.toLowerCase().includes(nodeSearch.toLowerCase()))" :key="node.id">
                                <button type="button" class="ps-chip" :aria-pressed="nodeFilter===node.id" x-on:click="nodeFilter=node.id" x-text="node.name"></button>
                            </template>
                        </div>
                    </div>
                    <div class="ps-server-grid" :class="{'is-list': overviewView==='list', 'has-motd': overviewPreferences.showMotd, 'no-metrics': !overviewMetrics().length, 'no-quick-actions': !overviewPreferences.showQuickActions}">
                        <template x-for="s in overviewItems()" :key="s.uuid">
                            <div class="ps-server-entry" :class="{'is-node-heading': !!s.isNodeHeader, 'has-card-motd': overviewPreferences.showMotd && s.game==='minecraft' && !!s.motd, 'has-power': overviewPreferences.showQuickActions && s.linked && Object.values(s.permissions || {}).some(Boolean), 'confirming-power': powerConfirmation?.uuid===s.uuid}" :style="accentStyle(s.uuid)">
                                <template x-if="s.isNodeHeader">
                                    <h2 class="ps-node-heading"><svg aria-hidden="true"><use href="#ps-host"/></svg><span x-text="s.name"></span><small x-text="s.count+(s.count===1?' server':' servers')"></small></h2>
                                </template>
                                <template x-if="!s.isNodeHeader">
                                <article class="ps-server-card" :class="{'locked': !s.linked}">
                                    <span class="ps-offline-artwork" x-show="s.status==='offline'" aria-label="Server offline"><svg aria-hidden="true"><use href="#ps-offline"/></svg></span>
                                    <span class="ps-card-background ps-banner-color" :class="{'is-auto': s.background_id==='color'}" x-show="bannerColor(s) && s.status!=='offline'" :style="bannerColor(s)" aria-hidden="true"></span>
                                    <img class="ps-card-background" x-show="s.background && s.status!=='offline'" :src="safeImage(s.background)" alt="" loading="lazy" x-on:error="$el.style.visibility='hidden'">
                                    <button class="ps-server-open" x-on:click="s.linked && (location.href=s.url)" :disabled="!s.linked" :aria-label="s.linked ? 'Open '+s.name : s.name+' — not assigned to you'">
                                        <div class="ps-card-head">
                                            <span class="ps-card-icons" :class="{'egg-primary': overviewPreferences.largeIcon==='egg' && !!(s.egg_icon || s.software_icon)}">
                                                <span class="ps-card-icon">
                                                    <img x-show="s.server_icon || s.icon" :src="safeImage(s.server_icon || s.icon)" alt="Server icon" loading="lazy" x-on:load="$el.style.visibility=''" x-on:error="$el.style.visibility='hidden'">
                                                    <svg x-show="!s.server_icon && !s.icon" aria-hidden="true"><use href="#ps-cube"/></svg>
                                                </span>
                                                <span class="ps-card-egg-icon" x-show="s.egg_icon || s.software_icon" data-tip-label="Server software" :data-tip="softwareTooltip(s)" :aria-label="softwareTooltip(s)">
                                                    <img :src="safeImage(s.egg_icon || s.software_icon)" alt="" loading="lazy" x-on:load="$el.style.visibility=''" x-on:error="$el.style.visibility='hidden'">
                                                </span>
                                            </span>
                                        </div>
                                        <div class="ps-card-title">
                                            <div class="ps-card-title-row" x-data="{observer:null,width:0,init(){this.observer=new ResizeObserver(()=>{const w=this.$el.clientWidth;if(w!==this.width){this.width=w;this.fitCardTitle(this.$el,cardNames(s).large)}});this.observer.observe(this.$el)},destroy(){this.observer?.disconnect()}}"
                                                 x-effect="const name=cardNames(s).large; $nextTick(()=>fitCardTitle($el,name))">
                                                <h3 x-text="cardNames(s).large"></h3>
                                                <small class="ps-card-real-name" :class="{'no-alias': !cardNames(s).small}" x-text="cardNames(s).small ? '('+cardNames(s).small+')' : ' '"></small>
                                            </div>
                                            <div class="ps-card-motd ps-motd-preview ps-motd-display" x-show="overviewPreferences.showMotd && s.game==='minecraft' && s.motd"><template x-for="(part,index) in motdPreview(s.motd)" :key="index"><span :style="part.style" x-text="part.text"></span></template></div>
                                        </div>
                                        <div class="ps-card-usage" x-show="overviewMetrics().length" :class="{'single-metric': overviewMetrics().length===1}">
                                            <template x-for="metric in overviewMetrics()" :key="metric.key">
                                                <div class="ps-card-mini-metric">
                                                    <span class="ps-card-usage-label">
                                                        <span class="ps-card-metric-name"><svg aria-hidden="true"><use :href="'#ps-'+metric.icon"/></svg><span x-text="metric.label"></span></span>
                                                        <strong x-text="metric.key==='cpu' ? cpuLabel(s) : cardRamLabel(s)"></strong>
                                                    </span>
                                                    <svg class="ps-card-graph" viewBox="0 0 100 30" preserveAspectRatio="none" aria-hidden="true">
                                                        <path class="ps-card-graph-axis" d="M2 27H98"/>
                                                        <path :d="cardGraph(s, metric.key)"/>
                                                        <circle x-show="cardGraphPoint(s, metric.key)!==null" cx="98" :cy="cardGraphPoint(s, metric.key) || 0" r="1.4"/>
                                                    </svg>
                                                </div>
                                            </template>
                                        </div>
                                        <div class="ps-card-badges ps-identity-badges">
                                            <span class="ps-badge is-strong ps-list-game"><svg aria-hidden="true"><use :href="s.game==='minecraft' ? '#ps-cube' : '#ps-gamepad'"/></svg><span x-text="s.game_label"></span></span>
                                            <span class="ps-badge ps-status" :class="s.status"><i></i><span x-text="s.status"></span></span>
                                        </div>
                                        <div class="ps-card-bottom" x-show="overviewView==='grid'">
                                            <span x-text="playerCount(s)"></span>
                                            <svg aria-hidden="true"><use :href="s.linked ? '#ps-chevron' : '#ps-lock'"/></svg>
                                        </div>
                                    </button>
                                    <div class="ps-card-footer" x-show="boot.web || overviewView==='list'">
                                        @if(\Simplify\Panel\Services\Extensions::web()) @include('server-directory::simplify.visibility') @endif
                                        <button type="button" class="ps-list-player-count" x-show="overviewView==='list'" x-on:click="s.linked && (location.href=s.url)" :disabled="!s.linked" :aria-label="s.linked ? 'Open '+s.name : s.name+' — not assigned to you'">
                                            <span x-text="playerCount(s)"></span>
                                            <svg aria-hidden="true"><use :href="s.linked ? '#ps-chevron' : '#ps-lock'"/></svg>
                                        </button>
                                    </div>
                                    <div class="ps-overview-power-wrap" x-show="overviewPreferences.showQuickActions">
                                        <div class="ps-overview-power" :class="{'is-confirming': powerConfirmation?.uuid===s.uuid}" role="group" :aria-label="'Power controls for '+(s.alias || s.name)">
                                            <template x-for="action in [{key:'start',label:'Start',icon:'play'},{key:'stop',label:'Stop',icon:'stop'},{key:'restart',label:'Restart',icon:'restart'},{key:'kill',label:'Force stop',icon:'kill'}]" :key="action.key">
                                                <button type="button" class="ps-btn is-icon" :class="{'is-danger': action.key==='kill'}" :data-tip="action.label" :aria-label="action.label+' '+(s.alias || s.name)" :disabled="!!overviewPowerBusy || !canOverviewPower(s, action.key)" x-on:click.stop="overviewPower(s, action.key)">
                                                    <svg aria-hidden="true"><use :href="'#ps-'+action.icon"/></svg>
                                                </button>
                                            </template>
                                            <div class="ps-power-confirm" x-show="powerConfirmation?.uuid===s.uuid" x-cloak>
                                                <button type="button" class="ps-btn is-icon" data-tip="Cancel" aria-label="Cancel power action" x-on:click.stop="powerConfirmation=null"><svg aria-hidden="true"><use href="#ps-close"/></svg></button>
                                                <button type="button" class="ps-btn is-icon is-primary" :data-tip="'Confirm '+powerActionLabel(powerConfirmation?.action)+(powerConfirmation?.action==='kill' ? ' — unsaved progress may be lost' : '')" :aria-label="'Confirm '+powerActionLabel(powerConfirmation?.action)" x-on:click.stop="overviewPower(s, powerConfirmation.action, true)"><svg aria-hidden="true"><use href="#ps-check"/></svg></button>
                                            </div>
                                        </div>
                                    </div>
                                </article>
                                </template>
                            </div>
                        </template>
                    </div>
                    <p x-show="!boot.servers.length" class="ps-empty">No servers yet.</p>
                </div>
            </div>

            {{-- Host health dock. --}}
            <section class="ps-health-panel" :style="accentStyle('overview')" x-on:click.outside="healthExpanded=false; healthOpenNodes=[]" x-show="boot.features.host_health" :class="{'expanded': healthExpanded}">
                <article class="ps-host-health" role="button" tabindex="0" aria-label="View health by node" :aria-expanded="healthExpanded" aria-controls="ps-health-details"
                         x-on:click="openHostHealth()" x-on:keydown.enter.prevent="openHostHealth()" x-on:keydown.space.prevent="openHostHealth()">
                    <div class="ps-host-state">
                        <span class="ps-host-emblem ps-health-heart" :class="boot.host_health?.state || 'unavailable'" role="img" :aria-label="'Host health: '+(boot.host_health?.label || 'Unavailable')">
                            <svg class="ps-host-heart" aria-hidden="true"><use href="#ps-heart"/></svg>
                            <span class="ps-host-server-badge"><svg aria-hidden="true"><use href="#ps-host"/></svg></span>
                        </span>
                        <div>
                            <div class="ps-host-heading"><h3>Host health</h3><svg class="ps-health-expand" :class="{'expanded': healthExpanded}" aria-hidden="true"><use href="#ps-chevron"/></svg></div>
                            <span class="ps-health-status" :class="boot.host_health?.state || 'unavailable'"><strong x-text="boot.host_health?.label || 'Unavailable'"></strong></span>
                            <small x-text="(boot.host_health?.available ?? 0)+' / '+(boot.host_health?.hosts ?? 0)+' hosts reporting'"></small>
                        </div>
                    </div>
                    <template x-for="metric in hostMetrics" :key="metric.key">
                        <div class="ps-host-metric">
                            <div class="ps-host-heading">
                                <svg aria-hidden="true"><use :href="'#ps-'+metric.icon"/></svg><span x-text="metric.label"></span>
                                <span class="ps-health-heart" :class="hostMetricState(metric.key)" role="img" :aria-label="metric.label+': '+hostMetricLabel(metric.key)" :data-tip="hostMetricLabel(metric.key)"><svg aria-hidden="true"><use href="#ps-heart"/></svg></span>
                            </div>
                            <strong :class="{'ps-network-value': metric.key==='network'}" x-text="metric.key==='network' ? hostRate() : hostValue(metric.key)"></strong>
                            <span class="ps-meter" x-show="metric.key!=='network'"><span :style="{width: hostFill(metric.key)+'%'}"></span></span>
                            <small x-show="metric.key==='network'" x-text="'Used '+hostValue('network')"></small>
                            <small x-show="metric.key==='cpu' && boot.host_health?.hosts>1">Average across reporting hosts</small>
                        </div>
                    </template>
                </article>
                <div id="ps-health-details" class="ps-health-drawer" :inert="!healthExpanded" :aria-hidden="!healthExpanded">
                    <div>
                        <div class="ps-health-detail-toolbar">Expand a node to view its servers</div>
                        <div class="ps-node-health-list">
                            <template x-for="node in healthNodes()" :key="node.id">
                                <article class="ps-node-health" :class="{'expanded': healthNodeOpen(node)}">
                                    <div class="ps-node-summary" x-on:click="if(!$event.target.closest('button')) toggleHealthNode(node)">
                                        <header>
                                            <button type="button" class="ps-node-toggle" :aria-expanded="healthNodeOpen(node)" :aria-controls="'ps-node-servers-'+node.id" x-on:click="toggleHealthNode(node)">
                                                <svg class="ps-node-symbol" aria-hidden="true"><use href="#ps-host"/></svg>
                                                <strong class="ps-node-name" x-text="node.name"></strong>
                                                <span class="ps-badge ps-status" :class="node.state" x-text="node.label"></span>
                                                <svg class="ps-node-arrow" :class="{'expanded': healthNodeOpen(node)}" aria-hidden="true"><use href="#ps-chevron"/></svg>
                                            </button>
                                        </header>
                                        <div class="ps-node-metrics">
                                            <template x-for="metric in hostMetrics" :key="metric.key">
                                                <div class="ps-host-metric">
                                                    <div class="ps-host-heading"><svg aria-hidden="true"><use :href="'#ps-'+metric.icon"/></svg><span x-text="metric.label"></span></div>
                                                    <strong :class="{'ps-network-value': metric.key==='network'}" x-text="metric.key==='network' ? hostRate(node) : hostValue(metric.key, node)"></strong>
                                                    <span class="ps-meter" x-show="metric.key!=='network'"><span :style="{width: hostFill(metric.key, node)+'%'}"></span></span>
                                                    <small x-show="metric.key==='network'" x-text="'Used '+hostValue('network', node)"></small>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                    <div class="ps-node-server-drawer" :class="{'expanded': healthNodeOpen(node)}" :id="'ps-node-servers-'+node.id" :inert="!healthNodeOpen(node)" :aria-hidden="!healthNodeOpen(node)">
                                        <div>
                                            <div class="ps-node-servers">
                                                <template x-for="item in nodeServers(node)" :key="item.uuid">
                                                    <div class="ps-node-server">
                                                        <div>
                                                            <a x-show="item.linked" :href="item.url" x-text="item.alias || item.name"></a>
                                                            <strong x-show="!item.linked" x-text="item.alias || item.name"></strong>
                                                            <span class="ps-badge ps-status" :class="item.status" x-text="item.status"></span>
                                                        </div>
                                                        <span><small>CPU</small><strong x-text="cpuLabel(item)"></strong></span>
                                                        <span><small>RAM</small><strong x-text="memoryLabel(item)"></strong></span>
                                                        <span><small>Storage</small><strong x-text="item.usage?.disk_bytes==null ? '—' : trafficBytes(item.usage.disk_bytes)"></strong></span>
                                                        <span><small>Network</small><strong x-text="serverRate(item)"></strong><small x-text="'Used '+serverTraffic(item)"></small></span>
                                                    </div>
                                                </template>
                                                <p x-show="!nodeServers(node).length" class="ps-help">No visible servers on this node.</p>
                                            </div>
                                        </div>
                                    </div>
                                </article>
                            </template>
                            <p x-show="!boot.host_health?.nodes?.length" class="ps-empty">No nodes reporting yet.</p>
                        </div>
                    </div>
                </div>
            </section>
        </section>

        {{-- Server dashboard ----------------------------------------------------------------------- --}}
        <section x-show="server" class="ps-server-dashboard">
            {{-- Status bar: slides in once the server card scrolls out of view, so status and power stay in reach. --}}
            <div class="ps-statusbar" :class="{'is-visible': statusBarVisible && !!server}" :inert="!statusBarVisible" role="region" aria-label="Server status">
                <span class="ps-statusbar-name" x-text="server?.alias || server?.name"></span>
                <span class="ps-badge ps-status" :class="status"><i></i><span x-text="status"></span></span>
                <button type="button" class="ps-statusbar-item ps-statusbar-address" x-show="server?.address" x-on:click="copy(server.address)" data-tip="Copy server address"><svg aria-hidden="true"><use href="#ps-link"/></svg><span x-text="server?.address"></span></button>
                <span class="ps-statusbar-item" data-tip="Players online"><svg aria-hidden="true"><use href="#ps-players"/></svg><span x-text="playerCount({ ...server, status })"></span></span>
                <div class="ps-statusbar-power" :inert="['installing','restoring_backup'].includes(serverSetup())">
                    <button type="button" class="ps-btn is-sm is-primary" x-on:click="power('start')" :disabled="busy || !server?.permissions['control.start'] || status==='running'" aria-label="Start server" data-tip="Start"><svg aria-hidden="true"><use href="#ps-play"/></svg></button>
                    <button type="button" class="ps-btn is-sm" x-on:click="power('restart')" :disabled="busy || !server?.permissions['control.restart']" aria-label="Restart server" data-tip="Restart"><svg aria-hidden="true"><use href="#ps-restart"/></svg></button>
                    <button type="button" class="ps-btn is-sm" x-on:click="power('stop')" :disabled="busy || !server?.permissions['control.stop'] || status==='offline'" aria-label="Stop server" data-tip="Stop"><svg aria-hidden="true"><use href="#ps-stop"/></svg></button>
                    <button type="button" class="ps-btn is-sm is-danger" x-on:click="power('kill')" :disabled="busy || !server?.permissions['control.stop'] || status==='offline'" aria-label="Force stop server" data-tip="Kill"><svg aria-hidden="true"><use href="#ps-kill"/></svg></button>
                </div>
            </div>
            <div class="ps-alert is-warn ps-pending" x-show="server?.pending" x-cloak>
                <svg aria-hidden="true"><use href="#ps-alert"/></svg>
                <div>
                    <strong x-text="server?.pending?.error ? 'Changes need attention' : server?.pending?.file_only ? 'File changes saved — restart to load them' : 'Stop or restart to apply changes'"></strong>
                    <small x-text="server?.pending?.error || (server?.pending?.restart_at ? 'Applying changes when the server stops. The console remains available.' : (server?.pending?.file_only ? 'Files are saved. Restart now or schedule a restart if the game needs to reload them.' : 'Your saved changes are staged until the server stops or restarts.'))"></small>
                </div>
                <div class="ps-pending-actions">
                    <button type="button" x-on:click="restartChanges(0)" :disabled="busy" class="ps-btn is-sm" aria-label="Apply changes and restart"><svg aria-hidden="true"><use href="#ps-restart"/></svg><span>Restart now</span></button>
                    <button type="button" x-on:click="open('restart')" :disabled="busy || !showWidget('restart')" class="ps-btn is-sm is-icon" data-tip="Schedule restart" aria-label="Schedule restart"><svg aria-hidden="true"><use href="#ps-clock"/></svg></button>
                    <button type="button" x-on:click="discard()" :disabled="busy" class="ps-btn is-sm is-icon is-subtle" :aria-label="server?.pending?.file_only ? 'Dismiss restart reminder' : 'Discard changes'" :data-tip="server?.pending?.file_only ? 'Dismiss reminder (saved files stay changed)' : 'Discard changes'"><svg aria-hidden="true"><use href="#ps-undo"/></svg></button>
                </div>
            </div>

            <article class="ps-server-info" aria-label="Server information" x-init="watchServerInfo($el)">
                <span class="ps-offline-artwork" x-show="status==='offline'" aria-label="Server offline"><svg aria-hidden="true"><use href="#ps-offline"/></svg></span>
                <span class="ps-server-banner ps-banner-color" :class="{'is-auto': server?.background_id==='color'}" x-show="bannerColor(server) && status!=='offline'" :style="bannerColor(server)" aria-hidden="true"></span>
                <img class="ps-server-banner" x-show="server?.background && status!=='offline'" :src="safeImage(server?.background)" alt="" x-on:error="$el.style.visibility='hidden'">
                <div class="ps-info-badges ps-identity-badges">
                    <span class="ps-badge is-strong ps-list-game"><svg aria-hidden="true"><use :href="server?.minecraft ? '#ps-cube' : '#ps-gamepad'"/></svg><span x-text="server?.minecraft ? 'Minecraft' : (server?.software || 'Other game')"></span></span>
                    <span class="ps-badge ps-status" :class="status"><i></i><span x-text="status"></span></span>
                </div>
                <span class="ps-info-icons">
                    <span class="ps-info-icon ps-custom-server-icon" x-show="server?.server_icon" data-tip="server-icon.png"><img :src="safeImage(server?.server_icon)" alt="Custom server icon" x-on:load="$el.style.visibility=''" x-on:error="$el.style.visibility='hidden'"></span>
                    <span class="ps-info-icon ps-software-icon" data-tip-label="Server software" :data-tip="server?.pack?.modpack_name || server?.software || 'Server software'">
                        <img x-show="server?.software_icon" :src="safeImage(server?.software_icon)" alt="Server software icon" x-on:load="$el.style.visibility=''" x-on:error="$el.style.visibility='hidden'">
                        <svg x-show="!server?.software_icon" aria-hidden="true"><use href="#ps-cube"/></svg>
                    </span>
                </span>
                <div class="ps-info-content">
                    <div class="ps-game-title">
                        <h2 x-text="server?.alias || server?.name"></h2>
                        <small class="ps-real-server-name" x-show="server?.alias" x-text="'('+server?.name+')'"></small>
                        <button type="button" class="ps-btn is-icon is-sm is-subtle ps-alias-button" x-show="server?.permissions['settings.rename']" x-on:click="openAlias()" data-tip="Change alias" aria-label="Change alias" aria-haspopup="dialog"><svg aria-hidden="true"><use href="#ps-edit"/></svg></button>
                    </div>
                    <dl class="ps-info-details">
                        <div><dt>Software</dt><dd x-text="server?.software || 'Not reported'"></dd></div>
                        <div x-show="server?.minecraft"><dt>Minecraft version</dt><dd x-text="minecraftVersion()"></dd></div>
                        <div x-show="server?.pack?.modpack_name"><dt>Modpack</dt><dd x-text="server?.pack?.modpack_name"></dd></div>
                        <div x-show="server?.pack?.modpack_name"><dt>Modpack version</dt><dd x-text="server?.pack?.modpack_version || 'Not reported'"></dd></div>
                    </dl>
                    <div class="ps-motd-preview ps-motd-display" x-show="server?.properties?.motd || server?.pack?.motd"><template x-for="(part,index) in motdPreview(server?.properties?.motd || server?.pack?.motd || '')" :key="index"><span :style="part.style" x-text="part.text"></span></template></div>
                    <button type="button" class="ps-address ps-info-address" x-on:click="copy(server.address)" :disabled="!server?.address" data-tip="Copy server address">
                        <svg aria-hidden="true"><use href="#ps-link"/></svg><span x-text="server?.address || 'No address assigned'"></span><svg aria-hidden="true"><use href="#ps-copy"/></svg>
                    </button>
                </div>
                <div class="ps-color-control" x-on:click.outside="colorMenu=false" x-on:keydown.escape.stop="colorMenu=false">
                    <button type="button" class="ps-web-listing" x-show="server?.website_listing" x-cloak role="switch" :aria-checked="!!server?.website_listing?.visible" :disabled="busy" x-on:click="saveListing(!server.website_listing.visible)" aria-label="Show on website" :data-tip="server?.website_listing?.visible ? 'Shown on the website' : 'Hidden from the website'">
                        <svg aria-hidden="true"><use href="#ps-web"/></svg><span class="ps-switch" :class="{'is-on': server?.website_listing?.visible}" aria-hidden="true"></span>
                    </button>
                    <button type="button" class="ps-swatch ps-color-dot" :disabled="busy || !server?.permissions['settings.rename']" :aria-expanded="colorMenu" aria-label="Change server color" data-tip="Server color" x-on:click="toggleColorMenu($el)"></button>
                    <button type="button" class="ps-btn is-icon is-sm" :disabled="busy || !server?.permissions['settings.rename']" aria-label="Change server background" data-tip="Server background" aria-haspopup="dialog" x-on:click="openBackground()"><svg aria-hidden="true"><use href="#ps-image"/></svg></button>
                    <div class="ps-dropdown is-fixed ps-color-dropdown" x-show="colorMenu" x-cloak :style="colorMenuStyle" x-on:click.stop>
                        <span class="ps-label">Server color</span>
                        <div class="ps-swatches ps-color-presets">
                            <template x-for="(name,color) in (boot.available_colors || {})" :key="color">
                                <button type="button" class="ps-swatch" :style="{'background-color': color}" :data-tip="name" :aria-label="'Select '+name+' server color'" :aria-pressed="colorDraft===color" :disabled="busy" x-on:click="colorDraft=color"></button>
                            </template>
                        </div>
                        <label class="ps-check ps-custom-color"><span>Custom color</span><input type="color" class="ps-color-input" x-model="colorDraft" :disabled="busy" aria-label="Choose custom server color"></label>
                        <div class="ps-dropdown-actions">
                            <button type="button" class="ps-btn is-sm" x-on:click="colorMenu=false" :disabled="busy">Cancel</button>
                            <button type="button" class="ps-btn is-sm is-primary" x-on:click="commitServerColor()" :disabled="busy">Save</button>
                        </div>
                    </div>
                </div>
            </article>

            {{-- A new or reinstalled server: nothing to click until Pelican has finished installing it. --}}
            <div class="ps-setup-state" x-show="serverSetup()" x-cloak role="status" aria-live="polite" :class="'is-'+serverSetup()">
                <span class="ps-setup-spinner" x-show="serverSetup()==='installing'" aria-hidden="true"></span>
                <svg x-show="serverSetup()!=='installing'" aria-hidden="true"><use href="#ps-alert"/></svg>
                <div>
                    <strong x-text="serverSetup()==='installing' ? 'Setting up your server…' : serverSetup()==='restoring_backup' ? 'Restoring a backup…' : 'The installation did not finish'"></strong>
                    <small x-text="serverSetup()==='installing' ? 'Pelican is downloading and installing the game files. This can take a few minutes, longer for big Steam games. Everything opens as soon as it is ready; the console shows the progress.' : serverSetup()==='restoring_backup' ? 'The workspaces open again when the restore is done.' : 'Check the console for the error. An administrator can reinstall the server from its settings.'"></small>
                </div>
            </div>

            <div class="ps-server-workspace" :class="{'has-dock': docked()}" x-init="embedded && fitEmbedded($el)">
                {{-- A workspace opens in this block; the console then shrinks to a read-only view at the bottom right. --}}
                <div class="ps-dock" x-effect="syncDock($el, docked())"></div>
                <div class="ps-settings-column" :inert="['installing','restoring_backup'].includes(serverSetup())" :class="{'is-locked': ['installing','restoring_backup'].includes(serverSetup())}">
                    <nav class="ps-widget-grid" aria-label="Server sections">
                        <button type="button" class="ps-widget is-console" style="--ps-tone: #9a9aa6" :aria-current="docked() ? null : 'page'" x-on:click="docked() && close()" aria-label="Live console">
                            <span class="ps-widget-icon" aria-hidden="true">›_</span>
                            <span><strong>Console</strong></span>
                        </button>
                        <template x-for="widget in widgets" :key="widget.key">
                            <button type="button" class="ps-widget" :style="{'--ps-tone': widgetTone(widget.key)}" :aria-current="modal===widget.key ? 'page' : null" :aria-label="widgetTitle(widget)+' — '+widgetDescription(widget)" x-show="showWidget(widget.key)" x-on:click="modal===widget.key ? null : open(widget.key)">
                                <span class="ps-widget-icon"><svg aria-hidden="true"><use :href="'#ps-'+widget.icon"/></svg></span>
                                <span><strong x-text="widgetTitle(widget)"></strong><small x-text="widgetDescription(widget)"></small></span>
                            </button>
                        </template>
                        {{-- Pages other plugins add to the server panel. --}}
                        <template x-if="server?.external_pages?.length">
                            <span class="ps-widget-group">External</span>
                        </template>
                        <template x-for="page in server?.external_pages || []" :key="page.key">
                            <button type="button" class="ps-widget" style="--ps-tone: #2dd4bf" :aria-current="modal==='external' && externalPage?.key===page.key ? 'page' : null" x-on:click="openExternal(page)" :aria-label="page.label+' — from '+page.plugin" :data-tip="'From the '+page.plugin+' plugin'">
                                <span class="ps-widget-icon"><svg aria-hidden="true"><use href="#ps-plugins"/></svg></span>
                                <span><strong x-text="page.label"></strong></span>
                            </button>
                        </template>
                    </nav>
                </div>
                <div class="ps-live-column">
                    <aside class="ps-console" x-show="server">
                        <div class="ps-console-title">
                            <span class="ps-terminal-icon" aria-hidden="true">›_</span>
                            <strong>Live console</strong>
                            <span class="ps-console-dot" :class="{'connected': connected}"></span><span class="ps-live-bars" x-show="connected" aria-hidden="true"><i></i><i></i><i></i><i></i></span>
                            <span class="ps-console-state" x-text="connected ? 'Connected to Wings' : 'Connecting to Wings…'"></span>
                            <button type="button" class="ps-console-clear" x-on:click="logs=[]; renderLogs()">Clear</button>
                        </div>
                        <pre wire:ignore class="ps-console-output" x-ref="output" tabindex="0" aria-label="Server console output"></pre>
                        <form class="ps-command" x-on:submit.prevent="sendCommand()">
                            <span aria-hidden="true">›</span>
                            <input x-model="command" placeholder="Type a command…" :disabled="!connected || !server?.permissions['control.console']" x-on:keydown.up.prevent="historyBack()" x-on:keydown.down.prevent="historyForward()" aria-label="Console command">
                            <button type="submit" :disabled="!connected || !server?.permissions['control.console']" aria-label="Send command">↵</button>
                        </form>
                    </aside>
                    <article class="ps-card ps-stat ps-players-stat" x-show="server?.minecraft">
                        <div class="ps-stat-top"><span>Players</span><svg aria-hidden="true"><use href="#ps-players"/></svg></div>
                        <strong><span x-text="server?.players ?? '—'"></span><span class="ps-stat-total" x-text="server?.max_players ? ' / '+server.max_players : ''"></span></strong>
                        <small>Online right now</small>
                        <ul class="ps-online-players" aria-label="Online players">
                            <template x-for="p in server?.player_names || []" :key="p.name"><li><img :src="head(p)" alt="" loading="lazy" x-on:error="$el.style.visibility='hidden'"><span x-text="p.name"></span></li></template>
                        </ul>
                        <p class="ps-player-empty" x-show="!server?.player_names?.length" x-text="server?.players>0 ? 'Player names are not available.' : 'No players online.'"></p>
                    </article>
                    <div class="ps-card ps-resource-tile">
                        <template x-for="metric in metrics" :key="metric.key">
                            <article class="ps-stat">
                                <div class="ps-stat-top"><span x-text="metric.label"></span><svg aria-hidden="true"><use :href="'#ps-'+metric.icon"/></svg></div>
                                <strong x-text="metricValue(metric.key)"></strong>
                                <small x-text="metricLimit(metric.key)"></small>
                                <svg class="ps-spark" viewBox="0 0 200 36" preserveAspectRatio="none" aria-hidden="true"><path :d="spark(metric.key)"/></svg>
                            </article>
                        </template>
                    </div>
                    <div class="ps-card ps-power-tile" :inert="['installing','restoring_backup'].includes(serverSetup())" :class="{'is-locked': ['installing','restoring_backup'].includes(serverSetup())}">
                        <button type="button" class="ps-btn is-primary" x-on:click="power('start')" :disabled="busy || !server?.permissions['control.start'] || status==='running'" aria-label="Start server"><svg aria-hidden="true"><use href="#ps-play"/></svg><span>Start</span></button>
                        <button type="button" class="ps-btn" x-on:click="power('restart')" :disabled="busy || !server?.permissions['control.restart']" aria-label="Restart server"><svg aria-hidden="true"><use href="#ps-restart"/></svg><span>Restart</span></button>
                        <button type="button" class="ps-btn" x-on:click="power('stop')" :disabled="busy || !server?.permissions['control.stop'] || status==='offline'" aria-label="Stop server"><svg aria-hidden="true"><use href="#ps-stop"/></svg><span>Stop</span></button>
                        <button type="button" class="ps-btn is-danger" x-on:click="power('kill')" :disabled="busy || !server?.permissions['control.stop'] || status==='offline'" aria-label="Force stop server"><svg aria-hidden="true"><use href="#ps-kill"/></svg><span>Kill</span></button>
                    </div>
                </div>
            </div>
            <article x-show="server?.pack?.modpack_name" class="ps-card ps-pack-summary">
                <img :src="safeImage(server?.pack?.modpack_icon_url)" alt="Modpack icon">
                <div><p class="ps-eyebrow">Your modpack</p><h3 x-text="server?.pack?.modpack_name"></h3><p x-text="server?.pack?.modpack_version"></p></div>
                <a :href="safeLink(server?.pack?.modpack_url)" target="_blank" rel="noopener noreferrer" class="ps-btn is-icon is-subtle" data-tip="Modpack info" aria-label="Modpack info"><svg aria-hidden="true"><use href="#ps-link"/></svg></a>
            </article>
        </section>
    </main>

    {{-- Workspace popup: one shell with fixed sizes (sm, md, lg); only the body scrolls. ------------------- --}}
    <div class="ps-modal-layer" :data-open="!!modal && !docked()" :data-docked="docked()" x-show="modal" x-cloak>
        <section class="ps-modal" :class="modalSize()" :role="docked() ? 'region' : 'dialog'" :aria-modal="modal && !docked() ? 'true' : null" :aria-label="modalTitle()" x-trap.noscroll="modal && !docked()">
            <header class="ps-modal-head">
                <h2 x-text="modalTitle()"></h2>
                <button type="button" class="ps-btn is-sm is-icon is-subtle" x-show="modal==='files' && server?.files_url" x-on:click="window.open(server.files_url, 'ps-files-'+server.uuid, 'popup,width=1280,height=860')" data-tip="Open in a separate window" aria-label="Open file manager in a separate window"><svg aria-hidden="true"><use href="#ps-external"/></svg></button>
                <div class="ps-sftp-inline" x-show="modal==='files' && server?.sftp">
                    <button type="button" class="ps-btn is-sm ps-sftp-toggle" x-on:click="sftpOpen=!sftpOpen" :aria-expanded="sftpOpen" aria-controls="ps-sftp-details" x-text="sftpOpen ? 'Hide SFTP details' : 'Show SFTP details'"></button>
                    <span id="ps-sftp-details" class="ps-sftp-details" x-show="sftpOpen" x-cloak>
                        <button type="button" class="ps-sftp-value" x-on:click="copy(server.sftp.username, 'Username copied')" data-tip="Copy SFTP username"><span x-text="server?.sftp?.username"></span><svg aria-hidden="true"><use href="#ps-copy"/></svg></button>
                        <small>Password: your panel password</small>
                        <a class="ps-sftp-connect" :href="sftpLink()" data-tip="Open SFTP connection"><span x-text="server?.sftp?.url"></span><svg aria-hidden="true"><use href="#ps-link"/></svg></a>
                    </span>
                </div>
                <button type="button" class="ps-btn is-icon is-subtle ps-close" x-on:click="close()" aria-label="Close dialog"><svg aria-hidden="true"><use href="#ps-close"/></svg></button>
            </header>

            <div class="ps-modal-body" :class="{'is-flush': modalFlush()}">
                <div x-show="loading" class="ps-modal-loading" role="status"><span class="ps-spinner"></span><span>Loading your workspace…</span></div>

                <template x-if="modal==='create'">
                    <iframe :data-ps-frame="createGame==='minecraft' ? 'create' : 'native'" :data-ps-accent="currentAccentHex()" :src="createFrameUrl()" title="Create server" x-on:load="createFrameLoaded($el)"></iframe>
                </template>
                <template x-if="modal==='panel-settings'">
                    <iframe data-ps-frame="settings" :data-ps-accent="currentAccentHex()" :src="panelSettingsUrl()" title="Simplify settings" x-on:load="panelSettingsLoaded($el)"></iframe>
                </template>
                <template x-if="modal==='external' && externalPage">
                    <iframe data-ps-frame="native" :data-ps-accent="currentAccentHex()" :src="frameUrl(externalPage.url)" :title="externalPage.label" x-on:load="styleFrame($el, 'native'); loading=false"></iframe>
                </template>
                <template x-if="modal==='admin-page' && adminPage">
                    <iframe data-ps-frame="native" :data-ps-accent="currentAccentHex()" :src="frameUrl(adminPage.url)" :title="adminPage.label" x-on:load="styleFrame($el, 'native')"></iframe>
                </template>
                <template x-if="modal==='account'">
                    <iframe data-ps-frame="account" :data-ps-accent="currentAccentHex()" :src="frameUrl(boot.profile_url)" title="Profile" x-on:load="styleFrame($el, 'account')"></iframe>
                </template>

                {{-- Alias --}}
                <template x-if="modal==='alias'">
                    <div>
                        <label class="ps-field">Server alias<input x-model="aliasDraft" maxlength="80" placeholder="Leave empty to use the server name" x-on:keydown.enter="saveAlias()"></label>
                        <p class="ps-help">The actual server name stays unchanged. Clear the alias to show the server name as the title.</p>
                    </div>
                </template>

                {{-- Background --}}
                <template x-if="modal==='background'">
                    <div>
                        <p class="ps-help">Choose the artwork for this server’s overview card and dashboard.</p>
                        <div class="ps-background-grid">
                            <button type="button" class="ps-background-choice ps-background-auto" :aria-pressed="backgroundDraft==='auto'" x-on:click="backgroundDraft='auto'" :disabled="busy">
                                <svg aria-hidden="true"><use href="#ps-image"/></svg><strong>Automatic</strong><small>A consistent image for this server</small>
                            </button>
                            <button type="button" class="ps-background-choice ps-background-auto" :aria-pressed="backgroundDraft==='none'" x-on:click="backgroundDraft='none'" :disabled="busy">
                                <strong>No background</strong><small>Keep it simple</small>
                            </button>
                            <button type="button" class="ps-background-choice ps-background-auto ps-background-color" :class="{'is-auto': true}" :aria-pressed="backgroundDraft==='color'" x-on:click="backgroundDraft='color'" :disabled="busy">
                                <span class="ps-banner-swatch ps-banner-color is-auto"></span><strong>Accent colour</strong><small>A colour that stands out but matches the accent</small>
                            </button>
                            <label class="ps-background-choice ps-background-auto ps-background-color" :aria-pressed="backgroundDraft.startsWith('color:') ? 'true' : 'false'">
                                <span class="ps-banner-swatch ps-banner-color" :style="{'--ps-banner': backgroundDraft.startsWith('color:') ? backgroundDraft.slice(6) : '#7c3aed'}"></span><strong>Your own colour</strong><small>Click to pick a colour</small>
                                <input type="color" class="ps-visually-hidden" :value="backgroundDraft.startsWith('color:') ? backgroundDraft.slice(6) : '#7c3aed'" x-on:input="backgroundDraft='color:'+$event.target.value.toLowerCase()" :disabled="busy" aria-label="Pick a banner colour">
                            </label>
                            <template x-for="image in boot.background_choices || []" :key="image.id">
                                <button type="button" class="ps-background-choice" :aria-pressed="backgroundDraft===image.id" x-on:click="backgroundDraft=image.id" :disabled="busy"><img :src="image.url" :alt="image.name" loading="lazy"><strong x-text="image.name"></strong></button>
                            </template>
                        </div>
                        <h3 class="ps-section-title">Your uploaded banners</h3>
                        <div class="ps-background-grid">
                            <template x-for="image in uploadedBackgrounds" :key="image.id">
                                <article class="ps-uploaded-banner">
                                    <button type="button" class="ps-background-choice" :aria-pressed="backgroundDraft===image.id" x-on:click="backgroundDraft=image.id" :disabled="busy"><img :src="image.url" :alt="image.name" loading="lazy"><strong x-text="image.name"></strong></button>
                                    <button type="button" class="ps-btn is-sm is-danger" x-on:click="deleteBanner(image.id)" :disabled="busy">Remove</button>
                                </article>
                            </template>
                        </div>
                        <p class="ps-help" x-show="!uploadedBackgrounds.length">No uploaded banners yet.</p>
                        <label class="ps-field">Upload a banner<input type="file" accept="image/png,image/jpeg,image/webp" wire:model="bannerUpload" x-on:change.capture="bannerUploadChanged($event)" x-on:livewire-upload-error="toast('Upload failed. Check PHP, Livewire and NGINX upload limits in System checks.', 'danger')"></label>
                        @error('bannerUpload')<p class="ps-alert is-danger">{{ $message }}</p>@enderror
                        <p class="ps-help">JPG, PNG or WebP · up to {{ \Simplify\Panel\Services\UploadLimits::label(\Simplify\Panel\Services\UploadLimits::bannerBytes()) }} (PHP/Livewire; your proxy may be lower). Wide images work best.</p>
                        <button type="button" class="ps-btn" x-on:click="uploadBanner()" :disabled="busy" wire:loading.attr="disabled" wire:target="bannerUpload,uploadServerBanner"><svg aria-hidden="true"><use href="#ps-download"/></svg>Upload and use banner</button>
                    </div>
                </template>

                {{-- Game settings: tabs on the left, one save action in the footer. --}}
                <div x-show="modal==='settings' && !loading" class="ps-settings-workspace" :class="{'has-tabs': server?.minecraft}">
                    <nav class="ps-tabs" x-show="server?.minecraft" aria-label="Settings sections">
                        <template x-for="tab in settingsTabs()" :key="tab.key">
                            <button type="button" :aria-pressed="settingsTab===tab.key" x-on:click="settingsTab=tab.key"><svg aria-hidden="true"><use :href="'#ps-'+tab.icon"/></svg><span x-text="tab.label"></span></button>
                        </template>
                    </nav>
                    <div class="ps-tab-panels">
                        <section x-show="settingsTab==='general' || !server?.minecraft">
                            <h3 class="ps-section-title">Server identity</h3>
                            <label class="ps-field">Server name
                                <span class="ps-input-row"><input x-model="name" maxlength="255"><button type="button" class="ps-btn" x-on:click="rename()" :disabled="busy || !server?.permissions['settings.rename']">Rename</button></span>
                            </label>
                            <label class="ps-field">Display alias
                                <span class="ps-input-row"><input x-model="aliasDraft" maxlength="80" placeholder="Leave empty to use the server name"><button type="button" class="ps-btn" x-on:click="saveAlias()" :disabled="busy || !server?.permissions['settings.rename']">Save alias</button></span>
                            </label>
                            <p class="ps-help">Name and alias are saved immediately and do not need a restart.</p>
                            <template x-if="server?.minecraft">
                                <div>
                                    <h3 class="ps-section-title">Server icon</h3>
                                    <label class="ps-field">64 × 64 PNG<input type="file" accept="image/png" wire:model="iconUpload"></label>
                                    <p class="ps-help">The icon is uploaded with your other saved settings at the next restart.</p>
                                </div>
                            </template>
                        </section>
                        <section x-show="settingsTab==='gameplay' && server?.minecraft">
                            <h3 class="ps-section-title">Gameplay</h3>
                            <div class="ps-grid-2">
                                <label class="ps-field">Maximum players<input type="number" min="1" max="1000" x-model.number="settings.max_players"></label>
                                <label class="ps-field">Difficulty<select x-model="settings.difficulty"><option>peaceful</option><option>easy</option><option>normal</option><option>hard</option></select></label>
                                <label class="ps-field">Game mode<select x-model="settings.gamemode"><option>survival</option><option>creative</option><option>adventure</option><option>spectator</option></select></label>
                                <label class="ps-field">View distance<input type="number" min="2" max="32" x-model.number="settings.view_distance"></label>
                                <label class="ps-field">Spawn protection<input type="number" min="0" max="1000" x-model.number="settings.spawn_protection"></label>
                            </div>
                            <h3 class="ps-section-title">Rules</h3>
                            <div class="ps-checks">
                                <label class="ps-check"><input type="checkbox" role="switch" x-model="settings.pvp"> PvP</label>
                                <label class="ps-check"><input type="checkbox" role="switch" x-model="settings.white_list"> Whitelist</label>
                                <label class="ps-check"><input type="checkbox" role="switch" x-model="settings.allow_flight"> Allow flight</label>
                            </div>
                        </section>
                        <section x-show="settingsTab==='motd' && server?.minecraft" class="ps-motd-editor">
                            <h3 class="ps-section-title">Message of the day</h3>
                            <div class="ps-motd" x-data="psMotdEditor({ name: () => server?.alias || server?.name, icon: () => safeImage(server?.server_icon) })" x-modelable="value" x-model="settings.motd">
                                @include('pelican-simplify::motd-editor')
                            </div>
                        </section>
                        <p x-show="server?.minecraft && !server?.properties_available" class="ps-alert is-warn">Settings are available after server.properties has been created.</p>
                    </div>
                </div>

                {{-- Performance --}}
                <div x-show="modal==='performance' && !loading">
                    <p class="ps-help">Allocated CPU, memory and storage for this server.</p>
                    <dl class="ps-kv">
                        <dt>CPU limit</dt><dd x-text="server?.cpu ? server.cpu+'%' : 'Unlimited'"></dd>
                        <dt>Memory limit</dt><dd x-text="server?.memory ? bytes(server.memory*1048576) : 'Unlimited'"></dd>
                        <dt>Storage limit</dt><dd x-text="server?.disk ? bytes(server.disk*1048576) : 'Unlimited'"></dd>
                    </dl>
                    <section x-show="server?.can_resources">
                        <h3 class="ps-section-title">Change resources</h3>
                        <template x-for="group in resourceGroups" :key="group.key">
                            <div class="ps-resource-group">
                                <span class="ps-field" x-text="group.label"></span>
                                <div class="ps-choice-cards" role="radiogroup" :aria-label="group.label">
                                    <template x-for="option in [...group.presets, ['custom', 'Custom', 'Choose your own']]" :key="option[0]">
                                        <button type="button" role="radio" class="ps-choice" :aria-checked="String(resourceChoice(group) === option[0])" x-on:click="pickResource(group, option[0])">
                                            <svg aria-hidden="true"><use :href="'#ps-' + group.icon"/></svg>
                                            <span class="ps-choice-title" x-text="option[1]"></span>
                                            <span class="ps-choice-description" x-text="option[2]"></span>
                                        </button>
                                    </template>
                                </div>
                                <label class="ps-field" x-show="resourceChoice(group) === 'custom'"><span x-text="group.custom + (resourceMin(group) === 0 ? ' · 0 = unlimited' : '')"></span><input type="number" step="any" :min="resourceMin(group)" :max="group.max" x-model.number="settings[group.key]"></label>
                            </div>
                        </template>
                        <p class="ps-help">Save changes, then apply them with a restart.</p>
                    </section>
                    <p class="ps-help" x-show="!server?.can_resources">Ask an administrator to change these limits.</p>
                </div>

                {{-- Files: fixed workspace, each iframe scrolls on its own. --}}
                <div x-show="modal==='files' && !loading" class="ps-files-workspace" :class="{'has-editor': !!fileEditorUrl}">
                    <div class="ps-files-list">
                        <iframe x-ref="filesFrame" data-ps-frame="files" :data-ps-accent="currentAccentHex()" :src="modal==='files' ? frameUrl(server?.files_url) : 'about:blank'" x-on:load="filesFrameLoaded($el)" title="Server file manager"></iframe>
                    </div>
                    <template x-if="fileEditorUrl">
                        <section class="ps-file-editor">
                            <header>
                                <svg class="ps-file-editor-icon" aria-hidden="true"><use href="#ps-file"/></svg>
                                <strong class="ps-file-editor-path" :data-tip="fileEditorPath" x-text="fileEditorPath.split('/').pop() || fileEditorPath"></strong>
                                <span class="ps-badge is-warn" x-show="fileEditorDirty">Unsaved</span>
                                <span class="ps-file-editor-actions">
                                    <button type="button" class="ps-btn is-sm is-primary" x-show="fileEditorExternal" :disabled="fileEditorChecking || fileEditorSaving" x-on:click="saveFileEditor()" data-tip="Save and close (Ctrl+S)"><svg aria-hidden="true"><use href="#ps-save"/></svg><span>Save</span></button>
                                    <button type="button" class="ps-btn is-icon is-sm is-subtle" x-on:click="closeFileEditor()" data-tip="Close editor" aria-label="Close file editor"><svg aria-hidden="true"><use href="#ps-close"/></svg></button>
                                </span>
                            </header>
                            <iframe data-ps-frame="editor" :data-ps-accent="currentAccentHex()" :src="fileEditorUrl" x-on:load="fileEditorLoaded($el)" title="File editor"></iframe>
                        </section>
                    </template>
                </div>

                {{-- Backups --}}
                <div x-show="modal==='backups' && !loading">
                    <div class="ps-panel ps-backup-policy">
                        <svg aria-hidden="true"><use href="#ps-backups"/></svg>
                        <div>
                            <strong>Automatic backups</strong>
                            <p x-text="backupPolicyLabel()"></p>
                            <small>A failed backup never replaces a successful recovery point.</small>
                        </div>
                    </div>
                    <div x-show="server?.root_admin" class="ps-admin-options">
                        <h3 class="ps-section-title">Admin options</h3>
                        <div class="ps-grid-2">
                            <label class="ps-field">Interval · minutes<input type="number" min="30" x-model.number="backupSettings.interval_minutes"></label>
                            <label class="ps-field">Recovery points<input type="number" min="1" max="100" x-model.number="backupSettings.retention"></label>
                        </div>
                        <div class="ps-input-row">
                            <label class="ps-check"><input type="checkbox" role="switch" x-model="backupSettings.enabled"> Automatic backups enabled</label>
                            <label class="ps-check"><input type="checkbox" role="switch" x-model="backupSettings.only_when_used"> Skip while the server has not run</label>
                            <button type="button" class="ps-btn is-primary" x-on:click="saveBackups()" :disabled="busy">Save backup policy</button>
                        </div>
                    </div>
                    <p class="ps-alert is-warn" x-show="!backups.configured">Backup storage is not configured yet. Ask an administrator to configure a backup host in Pelican.</p>
                    <h3 class="ps-section-title">Recovery points</h3>
                    <div class="ps-rows">
                        <template x-for="b in backups.items" :key="b.uuid">
                            <div>
                                <span><strong x-text="b.name"></strong><small x-text="b.completed ? (b.successful ? bytes(b.bytes) : 'Failed') : 'In progress'"></small></span>
                                <button type="button" class="ps-btn is-sm" x-on:click="downloadBackup(b.uuid)" :disabled="busy || !b.successful || !b.completed || !server?.permissions['backup.download']"><svg aria-hidden="true"><use href="#ps-download"/></svg>Download</button>
                                <button type="button" class="ps-btn is-sm" x-on:click="restoreBackup(b.uuid)" :disabled="busy || status!=='offline' || !b.successful || !b.completed || !server?.permissions['backup.restore']"><svg aria-hidden="true"><use href="#ps-restart"/></svg>Restore</button>
                            </div>
                        </template>
                    </div>
                    <p x-show="!backups.items.length" class="ps-empty">Your first automatic backup will appear here.</p>
                    <div x-show="server?.root_admin">
                        <h3 class="ps-section-title">Full backup management · admin</h3>
                        <iframe class="ps-embedded-frame" x-ref="backupFrame" data-ps-frame="native" :data-ps-accent="currentAccentHex()" :src="modal==='backups' && server?.root_admin ? frameUrl(server?.backups_url) : 'about:blank'" x-on:load="styleFrame($el, 'native')" title="Admin backup management"></iframe>
                    </div>
                </div>

                {{-- Players --}}
                <div x-show="modal==='players' && !loading">
                    <div class="ps-player-actions">
                        <label class="ps-field">Player name<input x-model="playerName" maxlength="16" placeholder="Minecraft username"></label>
                        <div class="ps-action-wrap">
                            <template x-for="action in ['Whitelist','Unwhitelist','OP','DeOP','Kick','Ban','Unban']">
                                <button type="button" class="ps-btn is-sm" :class="{'is-danger': ['Kick','Ban'].includes(action)}" x-on:click="playerAction(action)" :disabled="!connected || status!=='running' || !server?.permissions['control.console']" x-text="action"></button>
                            </template>
                        </div>
                    </div>
                    <h3 class="ps-section-title">Online players</h3>
                    <p class="ps-help">Servers may hide player names in their status response.</p>
                    <div class="ps-player-list">
                        <template x-for="p in server?.player_names || []" :key="p.name">
                            <button type="button" x-on:click="playerName=p.name"><img :src="head(p)" :alt="p.name" loading="lazy"><span x-text="p.name"></span><span class="ps-badge is-ok">Online</span></button>
                        </template>
                    </div>
                    <p x-show="!server?.player_names?.length" class="ps-empty">No player names reported.</p>
                    <template x-for="[key,title] in [['whitelist','Whitelist'],['ops','Operators'],['banned-players','Banned players']]">
                        <section>
                            <h3 class="ps-section-title" x-text="title"></h3>
                            <div class="ps-player-list">
                                <template x-for="p in playerLists[key] || []" :key="p.name">
                                    <button type="button" x-on:click="playerName=p.name"><img :src="head(p)" :alt="p.name" loading="lazy"><span x-text="p.name"></span></button>
                                </template>
                            </div>
                            <p class="ps-help" x-show="!(playerLists[key] || []).length">Nobody yet.</p>
                        </section>
                    </template>
                </div>

                {{-- Plugins and mods --}}
                <div x-show="isPackageModal() && !loading">
                    <p class="ps-help">Install releases compatible with your Minecraft version and server loader. Stop the server before making changes.</p>
                    <div class="ps-input-row ps-package-search">
                        <input class="ps-input" x-model="packageQuery" x-on:keydown.enter="searchPackages()" :placeholder="modal==='plugins' ? 'Search plugins…' : 'Search mods…'">
                        <input class="ps-input ps-version-input" x-model="packageVersion" placeholder="Minecraft version">
                        <button type="button" class="ps-btn is-primary" x-on:click="searchPackages()" :disabled="busy"><svg aria-hidden="true"><use href="#ps-search"/></svg>Search</button>
                    </div>
                    <div class="ps-package-grid">
                        <template x-for="p in packageResults" :key="p.project_id">
                            <article class="ps-package">
                                <img :src="safeImage(p.icon_url)" alt="" loading="lazy">
                                <div><strong x-text="p.title"></strong><p x-text="p.description"></p><small>Modrinth</small></div>
                                <button type="button" class="ps-btn is-sm" x-on:click="installPackage(p.project_id)" :disabled="busy || status!=='offline'">Install</button>
                            </article>
                        </template>
                    </div>
                    <h3 class="ps-section-title" x-text="modal==='plugins' ? 'Installed plugins' : 'Installed mods'"></h3>
                    <div class="ps-rows">
                        <template x-for="f in installed.files || []" :key="f.name">
                            <div>
                                <span x-text="f.name"></span>
                                <button type="button" class="ps-btn is-sm" x-show="tracked(f.name)" x-on:click="installPackage(tracked(f.name).project_id)" :disabled="busy || status!=='offline'">Update</button>
                                <button type="button" class="ps-btn is-sm is-danger" x-on:click="removePackage(f.name)" :disabled="busy || status!=='offline'">Remove</button>
                            </div>
                        </template>
                    </div>
                    <p class="ps-help" x-show="!(installed.files || []).length">Nothing installed yet.</p>
                </div>

                @if(\Simplify\Panel\Services\Extensions::web()) @include('server-directory::simplify.workspace') @endif

                {{-- Network --}}
                <div x-show="modal==='connection' && !loading">
                    <h3 class="ps-section-title">Server address</h3>
                    <p class="ps-help">Use this address to connect to your server.</p>
                    <button type="button" class="ps-btn ps-address" x-on:click="copy(server.address)"><span x-text="server?.address || 'No address assigned'"></span><svg aria-hidden="true"><use href="#ps-copy"/></svg></button>
                    @if(\Simplify\Panel\Services\Subdomains::active())
                        <h3 class="ps-section-title">Subdomain</h3>
                        <p class="ps-help">Players can join with this name instead of the port address. The DNS records are created automatically; leave it empty to remove the subdomain.</p>
                        <label class="ps-field">Name
                            <span class="ps-input-row"><input x-model="requestedSubdomain" maxlength="63" placeholder="oak" x-on:keydown.enter.prevent="saveSubdomain()"><span class="ps-input-suffix" x-text="'.' + (advanced.subdomain?.domain || '')"></span><button type="button" class="ps-btn" x-on:click="saveSubdomain()" :disabled="busy || !server?.owner">Save</button></span>
                        </label>
                        <template x-if="subdomainStatus()">
                            <p class="ps-help"><span class="ps-badge" :class="subdomainStatus().tone"><i></i><span x-text="subdomainStatus().label"></span></span> <span x-text="advanced.subdomain?.message"></span></p>
                        </template>
                    @elseif(\Simplify\Panel\Services\Extensions::web()) @include('server-directory::simplify.subdomain') @endif
                </div>

                {{-- Server software (Minecraft): reinstall with another type, version or modpack. --}}
                <div x-show="modal==='software' && server?.minecraft && !loading">
                    <p class="ps-alert is-warn">Changing software reinstalls the server. A successful backup from the last 30 minutes is required. Stop the server and enter its name to confirm.</p>
                    <div class="ps-grid-2">
                        <label class="ps-field">Server type<select x-model="software.server_type"><option value="vanilla">Vanilla</option><option value="plus">Vanilla+</option><option value="modded">Modpack</option></select></label>
                        <label x-show="software.server_type!=='modded'" class="ps-field">Installed software
                            <select x-model.number="software.egg_id"><option value="">Choose software</option><template x-for="e in advanced.eggs || []" :key="e.id"><option :value="e.id" x-text="e.name"></option></template></select>
                        </label>
                        <label x-show="software.server_type!=='modded'" class="ps-field">Minecraft version<input x-model="software.minecraft_version" placeholder="1.21.3"></label>
                    </div>
                    <div x-show="software.server_type==='modded'">
                        <div class="ps-input-row">
                            <input class="ps-input" x-model="packQuery" placeholder="Search Modrinth, FTB, Technic…" x-on:keydown.enter="searchPacks()">
                            <button type="button" class="ps-btn" x-on:click="searchPacks()" :disabled="busy"><svg aria-hidden="true"><use href="#ps-search"/></svg>Search</button>
                        </div>
                        <div class="ps-package-grid">
                            <template x-for="p in packs" :key="p.key">
                                <button type="button" class="ps-package" x-on:click="selectPack(p)">
                                    <img :src="safeImage(p.icon_url)" alt="" loading="lazy">
                                    <span><strong x-text="p.title"></strong><small x-text="p.source"></small><p x-text="p.description"></p></span>
                                </button>
                            </template>
                        </div>
                        <label class="ps-field">Selected pack release
                            <select x-model="software.modpack_version"><option value="">Choose a release</option><template x-for="[id,name] in Object.entries(packReleases)" :key="id"><option :value="id" x-text="name"></option></template></select>
                        </label>
                    </div>
                    <label class="ps-field">Type the server name<input x-model="softwareConfirm" :placeholder="server?.name"></label>
                </div>
                <template x-if="modal==='software' && !server?.minecraft && server?.native_sections?.startup">
                    <iframe data-ps-frame="native" :data-ps-accent="currentAccentHex()" :src="frameUrl(server.native_sections.startup)" x-on:load="styleFrame($el, 'native')" title="Game startup settings"></iframe>
                </template>
                <template x-if="modal==='schedules' && server?.native_sections?.schedules">
                    <iframe data-ps-frame="native" :data-ps-accent="currentAccentHex()" :src="frameUrl(server.native_sections.schedules)" x-on:load="styleFrame($el, 'native')" title="Scheduled tasks"></iframe>
                </template>
                <template x-if="modal==='activity' && server?.native_sections?.activity">
                    <iframe data-ps-frame="native" :data-ps-accent="currentAccentHex()" :src="frameUrl(server.native_sections.activity)" x-on:load="styleFrame($el, 'native')" title="Activity log"></iframe>
                </template>

                {{-- Advanced --}}
                <div x-show="modal==='advanced' && !loading">
                    <div class="ps-action-wrap">
                        <a class="ps-btn" x-show="server?.permissions['database.read'] && server?.native_sections?.databases" :href="server?.native_sections?.databases" target="_blank" rel="noopener">Databases<svg aria-hidden="true"><use href="#ps-link"/></svg></a>
                        <a class="ps-btn" x-show="server?.root_admin && server?.permissions['user.read'] && server?.native_sections?.access" :href="server?.native_sections?.access" target="_blank" rel="noopener">Server access<svg aria-hidden="true"><use href="#ps-link"/></svg></a>
                    </div>
                    <h3 class="ps-section-title">Automatic runtime</h3>
                    <dl class="ps-kv">
                        <dt>Container image</dt><dd x-text="server?.image"></dd>
                        <dt>Startup command</dt><dd x-text="server?.startup"></dd>
                    </dl>
                    <p class="ps-help">Selected automatically. Only administrators can override these values.</p>
                    <section x-show="server?.owner" class="ps-danger-zone">
                        <h3 class="ps-section-title">Delete server</h3>
                        <p class="ps-help">Deletes this server and its files. This action cannot be undone.</p>
                        <label class="ps-field">Type the server name<input x-model="deleteConfirm" :placeholder="server?.name"></label>
                        <button type="button" class="ps-btn is-danger" x-on:click="deleteServer()" :disabled="busy || status!=='offline' || deleteConfirm!==server?.name">Delete server</button>
                    </section>
                </div>

                {{-- Scheduled restart --}}
                <div x-show="modal==='restart' && !loading">
                    <p class="ps-help">Choose when to stop, apply your staged settings, and start the server again.</p>
                    <label class="ps-field">Restart in · minutes<input type="number" min="1" max="10080" x-model.number="restartMinutes"></label>
                </div>
            </div>

            {{-- Footer actions belong to the open workspace. --}}
            <footer class="ps-modal-foot" x-show="['alias','background','settings','performance','software','restart'].includes(modal) && !loading && (modal!=='settings' || server?.minecraft) && (modal!=='performance' || server?.can_resources)">
                <span class="is-start" x-show="modal==='settings'" x-text="server?.pending ? 'Changes are waiting for a restart.' : 'Saved changes apply after a stop or restart.'"></span>
                <button type="button" class="ps-btn" x-on:click="close()" :disabled="busy">Cancel</button>
                <button type="button" class="ps-btn is-primary" x-show="modal==='alias'" x-on:click="saveAlias()" :disabled="busy">Save alias</button>
                <button type="button" class="ps-btn is-primary" x-show="modal==='background'" x-on:click="saveBackground()" :disabled="busy"><svg aria-hidden="true"><use href="#ps-check"/></svg><span x-text="busy ? 'Saving…' : 'Save background'"></span></button>
                <button type="button" class="ps-btn is-primary" x-show="modal==='settings'" x-on:click="saveSettings()" :disabled="busy || !server?.properties_available || !server?.permissions['file.update']">Save changes</button>
                <button type="button" class="ps-btn is-primary" x-show="modal==='performance'" x-on:click="saveSettings()" :disabled="busy">Save limits</button>
                <button type="button" class="ps-btn is-danger" x-show="modal==='software'" x-on:click="changeSoftware()" :disabled="busy || status!=='offline'">Install selected software</button>
                <button type="button" class="ps-btn is-primary" x-show="modal==='restart'" x-on:click="restartChanges(restartMinutes)" :disabled="busy"><svg aria-hidden="true"><use href="#ps-clock"/></svg>Schedule restart</button>
            </footer>
        </section>
    </div>
</div>
</x-filament-panels::page>

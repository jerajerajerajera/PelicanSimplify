<x-filament-panels::page>
@assets
<link id="simplify-dashboard-css" rel="stylesheet" href="{{ \Simplify\Panel\Services\BuildInfo::asset('dashboard.css') }}">
<script src="{{ \Simplify\Panel\Services\BuildInfo::asset('dashboard.js') }}"></script>
@endassets
<div class="ps" data-simplify-root x-cloak wire:ignore.self
     x-data="pelicanSimplify($wire, @js($this->initial()))"
     x-on:resize.window.debounce.150ms="applyScale(); restoreOverviewView()"
     :class="{'dark': themeDark, 'server-active': !!server, 'host-mode': hostMode && !server, 'is-embedded': embedded, 'is-console-window': consoleWindow, 'is-full-width': overviewPreferences.fullWidth, 'is-server-full-width': overviewPreferences.serverFullWidth, 'ps-halloween': halloween()}"
     :style="accentStyle(server?.uuid || 'overview')">
    @include('pelican-simplify::dashboard-icons')
    {{-- Halloween (25 October to 2 November): a faint web with a spider in the corner. Clicks pass through; once per window, not again inside an embedded page. --}}
    <template x-if="halloween() && !embedded">
        <div class="ps-hw-corner" aria-hidden="true">
            <svg class="ps-hw-web" viewBox="0 0 100 100"><path d="M100 0H0M100 0 10 45M100 0 40 80M100 0V100M100 0 72 95M78 0Q81.1 7 80.2 9.9Q85.5 15.8 86.8 17.6Q92.3 21.3 93.8 20.9Q98.9 23.4 100 22M60 0Q64 11 64 18Q72 27 76 32Q84.4 37 88.8 38Q96.4 41 100 40M40 0Q45 15.5 46 27Q57 39.5 64 48Q75.6 54.5 83.2 57Q93.6 60.5 100 60M20 0Q26 20 28 36Q42 52 52 64Q66.8 72 77.6 76Q90.8 80 100 80"/></svg>
            <span class="ps-hw-spider"><svg viewBox="0 0 14 14"><circle cx="7" cy="7" r="3.2"/><path d="M4 5 1 2M4 7H0M4 9l-3 3M10 5l3-3M10 7h4M10 9l3 3"/></svg></span>
        </div>
    </template>

    <main class="ps-main">
        {{-- Header ------------------------------------------------------------------------------ --}}
        <header class="ps-top">
            <div class="ps-heading">
                <div class="ps-identity">
                    <a :href="boot.list_url"><h1 class="ps-brand" :style="brandStyle()"><template x-if="brand().logo_url && brand().mode === 'logo'"><img class="ps-brand-logo" :src="brand().logo_url" alt="" :style="{ height: (Number(brand().logo_height) || 32) + 'px' }"></template><span class="ps-panel-name" x-show="!(brand().logo_url && brand().mode === 'logo') || brand().logo_text" :style="Simplify.brandTextStyle(brand(), brand().font_stack, boot.appearance?.accent_color)" x-text="(brand().text || '').trim() || boot.panel_name || @js(__('pelican-simplify::dashboard.ui.pelican_2'))"></span><span class="ps-title-pelican" x-show="brand().suffix !== false">{{ __('pelican-simplify::dashboard.ui.pelican') }}</span><span class="ps-visually-hidden" x-show="brand().mode === 'logo' && !brand().logo_text" x-text="boot.panel_name || @js(__('pelican-simplify::dashboard.ui.pelican_2'))"></span><template x-if="halloween()"><svg class="ps-hw-lantern" viewBox="0 0 24 24" aria-hidden="true"><path class="ps-hw-body" d="M12 6.5c-1.6-.9-3.6-1-5.3-.2C3.8 7.6 2.5 10.7 3.3 14c.8 3.4 3.6 5.6 6.4 5.4.8 0 1.6-.3 2.3-.7.7.4 1.5.7 2.3.7 2.8.2 5.6-2 6.4-5.4.8-3.3-.5-6.4-3.4-7.7-1.7-.8-3.7-.7-5.3.2z"/><path class="ps-hw-ribs" d="M8.2 7.2c-1.4 3-1.4 7.5.6 11.6M15.8 7.2c1.4 3 1.4 7.5-.6 11.6"/><path class="ps-hw-stem" d="M12 6.5c0-1.6.6-2.8 2-3.6"/><g class="ps-hw-face"><path d="M7.4 11.6 9.6 10l.9 2.4z"/><path d="M16.6 11.6 14.4 10l-.9 2.4z"/><path d="M7 14.6c1.4 2.2 8.6 2.2 10 0-.9.5-1.6.6-2.2.6l-.5-.9-.7 1.1c-.8.1-1.4.1-2.2 0l-.7-1.1-.5.9c-.7 0-1.3-.1-2.2-.6z"/></g></svg></template></h1></a>
                </div>
                <a x-show="server" :href="boot.list_url" class="ps-btn ps-back-overview">
                    <svg aria-hidden="true"><use href="#ps-back"/></svg><span>{{ __('pelican-simplify::dashboard.ui.all_servers') }}</span>
                </a>
            </div>
            <nav class="ps-top-actions" aria-label="{{ __('pelican-simplify::dashboard.ui.panel_navigation') }}" :class="{'nav-open': navMenu}"
                 x-on:click.outside="navMenu=false; accountMenu=false"
                 x-on:keydown.escape.stop="navMenu=false; accountMenu=false">
                {{-- Overview with health in the top bar: the health button (opens the details below it) and the create buttons. --}}
                <div class="ps-top-overview" x-show="!server && !hostMode && healthPlace()==='top-bar'" x-cloak>
                    <button type="button" class="ps-btn ps-health-pill" :class="boot.host_health?.state || 'unavailable'" x-on:click="healthPopover=!healthPopover; healthExpanded=false; healthOpenNodes=[]; placeHealthPopover()" :aria-expanded="healthPopover" aria-controls="ps-health-panel" :aria-label="Simplify.fill(@js(__('pelican-simplify::dashboard.ui.host_health_label')), { state: boot.host_health?.label || @js(__('pelican-simplify::dashboard.ui.unavailable')) })">
                        <svg aria-hidden="true"><use href="#ps-heart"/></svg><span x-text="boot.host_health?.label || @js(__('pelican-simplify::dashboard.ui.unavailable'))"></span>
                    </button>
                    <template x-for="choice in createButtons()" :key="choice.key">
                        <button type="button" class="ps-btn ps-create-button" :class="{'is-main': choice.primary}" x-on:click="openCreate(choice.key)" :aria-label="choice.label" aria-haspopup="dialog"><span class="ps-create-icon"><svg aria-hidden="true"><use :href="'#ps-'+(choice.primary ? 'host' : choice.icon)"/></svg><span class="ps-create-badge" x-show="choice.primary"><svg aria-hidden="true"><use href="#ps-plus"/></svg></span></span><span x-text="choice.label"></span></button>
                    </template>
                </div>
                <button type="button" class="ps-btn is-icon is-subtle ps-mobile-menu" aria-label="{{ __('pelican-simplify::dashboard.ui.panel_navigation') }}" aria-controls="ps-navigation-items" :aria-expanded="navMenu"
                        x-on:click="navMenu=!navMenu; accountMenu=false; appearanceMenu=false; groupsMenu=false; notifyMenu=false">
                    <svg aria-hidden="true"><use href="#ps-menu"/></svg>
                </button>
                <div class="ps-nav-items" id="ps-navigation-items">
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
                            <button type="button" class="ps-btn is-sm is-subtle" x-on:click="hideIncomingNotice()">{{ __('pelican-simplify::dashboard.ui.dismiss') }}</button>
                        </span>
                    </div>
                </template>
            </div>
                        <button type="button" x-ref="notifyButton" class="ps-btn is-icon is-subtle" data-tip="{{ __('pelican-simplify::dashboard.ui.notifications') }}" aria-label="{{ __('pelican-simplify::dashboard.ui.notifications') }}" aria-controls="ps-notify-menu" :aria-expanded="notifyMenu"
                                x-on:click="toggleNotifications()">
                            <svg aria-hidden="true"><use href="#ps-bell"/></svg><span class="ps-mobile-label">{{ __('pelican-simplify::dashboard.ui.notifications') }}</span>
                            <span class="ps-notify-count" x-show="unreadNotifications()" x-text="unreadNotifications()"></span>
                        </button>
                        <div id="ps-notify-menu" class="ps-dropdown is-end ps-notify-menu" x-show="notifyMenu" x-cloak x-transition.opacity>
                            <div class="ps-notify-head">
                                <strong class="ps-dropdown-title">{{ __('pelican-simplify::dashboard.ui.notifications') }}</strong>
                                <button type="button" class="ps-btn is-sm is-subtle" x-show="unreadNotifications()" x-on:click="markAllNotificationsRead()">{{ __('pelican-simplify::dashboard.ui.mark_all_read') }}</button>
                                <button type="button" class="ps-btn is-sm is-subtle" x-show="canClearRead()" x-on:click="clearReadNotifications()">{{ __('pelican-simplify::dashboard.ui.clear_read') }}</button>
                                <button type="button" class="ps-btn is-sm is-icon is-subtle" data-tip="{{ __('pelican-simplify::dashboard.ui.show_all') }}" aria-label="{{ __('pelican-simplify::dashboard.ui.show_all_notifications') }}" x-on:click="openAllNotifications()"><svg aria-hidden="true"><use href="#ps-expand"/></svg></button>
                            </div>
                            @include('pelican-simplify::notification-items')
                            <div class="ps-notify-list" x-show="pushOffer()">
                                <div class="ps-push-offer">
                                    <svg aria-hidden="true"><use href="#ps-bell"/></svg>
                                    <span>{{ __('pelican-simplify::dashboard.ui.get_announcements_as_notifications_on_this') }}</span>
                                    <button type="button" class="ps-btn is-sm" x-on:click="openNotifications()">{{ __('pelican-simplify::dashboard.ui.turn_on') }}</button>
                                    <button type="button" class="ps-btn is-sm is-icon is-subtle" data-tip="{{ __('pelican-simplify::dashboard.ui.not_now') }}" aria-label="{{ __('pelican-simplify::dashboard.ui.not_now') }}" x-on:click="hidePushOffer()"><svg aria-hidden="true"><use href="#ps-close"/></svg></button>
                                </div>
                            </div>
                            <div class="ps-dropdown-section" x-show="noticeHistory.length">
                                <span class="ps-label">{{ __('pelican-simplify::dashboard.ui.recent_messages') }}</span>
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

                    {{-- Own server groups and server order, saved with the account; a server sits in one group at most. --}}
                    <div class="ps-overview-appearance ps-overview-groups ps-nav-view" x-show="!server && !hostMode && overviewPreferences.showGroupsButton" x-on:click.outside="groupsMenu=false" x-on:keydown.escape.stop="groupsMenu=false; $refs.overviewGroupsButton.focus()">
                        <button type="button" x-ref="overviewGroupsButton" class="ps-btn is-icon is-subtle" data-tip="{{ __('pelican-simplify::dashboard.ui.server_groups') }}" aria-label="{{ __('pelican-simplify::dashboard.ui.server_groups') }}" aria-controls="ps-overview-groups-menu" :aria-expanded="groupsMenu"
                                x-on:click="groupsMenu=!groupsMenu; appearanceMenu=false; accountMenu=false; notifyMenu=false">
                            <svg aria-hidden="true"><use href="#ps-files"/></svg><span class="ps-mobile-label">{{ __('pelican-simplify::dashboard.ui.server_groups') }}</span>
                        </button>
                        <div id="ps-overview-groups-menu" class="ps-dropdown is-end ps-overview-appearance-menu" x-show="groupsMenu" x-cloak x-transition.opacity>
                            <strong class="ps-dropdown-title">{{ __('pelican-simplify::dashboard.ui.server_groups') }}</strong>
                            <p class="ps-dropdown-hint">{{ __('pelican-simplify::dashboard.ui.saved_with_your_account_so_every') }}</p>
                            <div class="ps-customize">
                                <div class="ps-customize-row">
                                    <div><strong>{{ __('pelican-simplify::dashboard.ui.server_order') }}</strong><small>{{ __('pelican-simplify::dashboard.ui.for_the_overview_and_for_groups') }}</small></div>
                                    <div class="ps-segmented" role="group" aria-label="{{ __('pelican-simplify::dashboard.ui.server_order') }}">
                                        <template x-for="order in SERVER_ORDERS" :key="order.key">
                                            <button type="button" :aria-pressed="overviewServerOrder===order.key" x-on:click="setServerOrder(order.key)" x-text="order.label"></button>
                                        </template>
                                    </div>
                                </div>
                                <span class="ps-customize-head">{{ __('pelican-simplify::dashboard.ui.my_groups') }}</span>
                                <small class="ps-group-note" x-show="!overviewGroups.length">{{ __('pelican-simplify::dashboard.ui.make_your_own_groups_such_as') }}</small>
                                <template x-for="(group, index) in overviewGroups" :key="group.id">
                                    <div class="ps-group-edit" :class="{'is-open': groupEditing===group.id}">
                                        <div class="ps-group-row">
                                            <button type="button" class="ps-btn is-sm is-icon is-subtle ps-group-toggle" x-on:click="groupEditing = groupEditing===group.id ? null : group.id" :aria-expanded="groupEditing===group.id" :data-tip="groupEditing===group.id ? @js(__('pelican-simplify::dashboard.ui.done')) : @js(__('pelican-simplify::dashboard.ui.servers_and_order'))" :aria-label="Simplify.fill(@js(__('pelican-simplify::dashboard.ui.servers_and_order_for')), { group: group.name })"><svg aria-hidden="true"><use href="#ps-chevron"/></svg></button>
                                            <span class="ps-group-dot" x-show="group.color" :style="{'background-color': group.color}" aria-hidden="true"></span>
                                            <input type="text" class="ps-input ps-group-name" :value="group.name" maxlength="40" aria-label="{{ __('pelican-simplify::dashboard.ui.group_name') }}" x-on:keydown.enter.prevent="$el.blur()" x-on:change="$el.value = renameOverviewGroup(group.id, $el.value)">
                                            <small class="ps-group-count" x-text="Simplify.choice(@js(__('pelican-simplify::dashboard.ui.server_count')), groupCount(group))"></small>
                                            <button type="button" class="ps-btn is-sm is-icon is-subtle ps-group-up" :disabled="index===0" x-on:click="moveOverviewGroup(group.id, -1)" data-tip="{{ __('pelican-simplify::dashboard.ui.move_up') }}" :aria-label="Simplify.fill(@js(__('pelican-simplify::dashboard.ui.move_up_name')), { name: group.name })"><svg aria-hidden="true"><use href="#ps-chevron"/></svg></button>
                                            <button type="button" class="ps-btn is-sm is-icon is-subtle ps-group-down" :disabled="index===overviewGroups.length-1" x-on:click="moveOverviewGroup(group.id, 1)" data-tip="{{ __('pelican-simplify::dashboard.ui.move_down') }}" :aria-label="Simplify.fill(@js(__('pelican-simplify::dashboard.ui.move_down_name')), { name: group.name })"><svg aria-hidden="true"><use href="#ps-chevron"/></svg></button>
                                            <button type="button" class="ps-btn is-sm is-icon is-subtle" x-on:click="removeOverviewGroup(group.id)" data-tip="{{ __('pelican-simplify::dashboard.ui.remove_group') }}" :aria-label="Simplify.fill(@js(__('pelican-simplify::dashboard.ui.remove_name')), { name: group.name })"><svg aria-hidden="true"><use href="#ps-close"/></svg></button>
                                        </div>
                                        <div class="ps-group-servers" x-show="groupEditing===group.id">
                                            <div class="ps-group-order ps-group-color"><span>{{ __('pelican-simplify::dashboard.ui.group_color') }}</span>
                                                <span class="ps-group-color-choice">
                                                    <label class="ps-swatch ps-group-swatch" :class="{'is-empty': !group.color}" :style="group.color ? {'background-color': group.color} : {}" :data-tip="group.color ? @js(__('pelican-simplify::dashboard.ui.change_group_color')) : @js(__('pelican-simplify::dashboard.ui.pick_a_group_color'))"><input type="color" class="ps-color-input" :value="group.color || '#878787'" x-on:change="setGroupColor(group.id, $el.value)" :aria-label="Simplify.fill(@js(__('pelican-simplify::dashboard.ui.color_for')), { name: group.name })"></label>
                                                    <button type="button" class="ps-btn is-sm is-subtle" x-show="group.color" x-on:click="setGroupColor(group.id, null)">{{ __('pelican-simplify::dashboard.ui.remove_color') }}</button>
                                                    <small x-show="!group.color">{{ __('pelican-simplify::dashboard.ui.off_every_server_keeps_its_own') }}</small>
                                                </span>
                                            </div>
                                            <label class="ps-group-order"><span>{{ __('pelican-simplify::dashboard.ui.order_in_this_group') }}</span>
                                                <select class="ps-input" :value="group.sort" x-on:change="setGroupOrder(group.id, $el.value)">
                                                    <template x-for="order in GROUP_ORDERS" :key="order.key"><option :value="order.key" x-text="order.label" :selected="group.sort===order.key"></option></template>
                                                </select>
                                            </label>
                                            <template x-for="(s, position) in groupServerList(group)" :key="s.uuid">
                                                <div class="ps-group-server" :class="{'is-member': group.servers.includes(s.uuid)}">
                                                    <button type="button" class="ps-customize-switch" role="switch" :aria-checked="group.servers.includes(s.uuid)" x-on:click="toggleGroupServer(group.id, s.uuid)">
                                                        <span><strong x-text="cardNames(s).large"></strong><small x-show="serverGroup(s.uuid) && serverGroup(s.uuid).id!==group.id" x-text="Simplify.fill(@js(__('pelican-simplify::dashboard.ui.now_in')), { group: serverGroup(s.uuid)?.name })"></small></span>
                                                        <span class="ps-switch" :class="{'is-on': group.servers.includes(s.uuid)}" aria-hidden="true"></span>
                                                    </button>
                                                    <span class="ps-group-server-move" x-show="group.sort==='manual' && group.servers.includes(s.uuid)">
                                                        <button type="button" class="ps-btn is-sm is-icon is-subtle ps-group-up" :disabled="position===0" x-on:click="moveGroupServer(group.id, s.uuid, -1)" data-tip="{{ __('pelican-simplify::dashboard.ui.move_up') }}" :aria-label="Simplify.fill(@js(__('pelican-simplify::dashboard.ui.move_up_name')), { name: cardNames(s).large })"><svg aria-hidden="true"><use href="#ps-chevron"/></svg></button>
                                                        <button type="button" class="ps-btn is-sm is-icon is-subtle ps-group-down" :disabled="position===groupCount(group)-1" x-on:click="moveGroupServer(group.id, s.uuid, 1)" data-tip="{{ __('pelican-simplify::dashboard.ui.move_down') }}" :aria-label="Simplify.fill(@js(__('pelican-simplify::dashboard.ui.move_down_name')), { name: cardNames(s).large })"><svg aria-hidden="true"><use href="#ps-chevron"/></svg></button>
                                                    </span>
                                                </div>
                                            </template>
                                            <small class="ps-group-note" x-show="!(boot.servers||[]).length">{{ __('pelican-simplify::dashboard.ui.no_servers_yet') }}</small>
                                        </div>
                                    </div>
                                </template>
                                <button type="button" class="ps-btn is-sm ps-group-add" x-show="overviewGroups.length < 30" x-on:click="addOverviewGroup(); $nextTick(() => [...$el.parentElement.querySelectorAll('.ps-group-name')].pop()?.select())"><svg aria-hidden="true"><use href="#ps-plus"/></svg>{{ __('pelican-simplify::dashboard.ui.new_group') }}</button>
                            </div>
                        </div>
                    </div>

                    {{-- Personal appearance preferences. --}}
                    <div class="ps-overview-appearance ps-nav-view" x-show="!hostMode" x-on:click.outside="appearanceMenu=false" x-on:keydown.escape.stop="appearanceMenu=false; $refs.overviewAppearance.focus()">
                        <button type="button" x-ref="overviewAppearance" class="ps-btn is-icon is-subtle" :data-tip="server ? @js(__('pelican-simplify::dashboard.ui.customize_server_page')) : @js(__('pelican-simplify::dashboard.ui.customize_overview'))" :aria-label="server ? @js(__('pelican-simplify::dashboard.ui.customize_server_page')) : @js(__('pelican-simplify::dashboard.ui.customize_overview'))" aria-controls="ps-overview-appearance-menu" :aria-expanded="appearanceMenu"
                                x-on:click="appearanceMenu=!appearanceMenu; groupsMenu=false; accountMenu=false; notifyMenu=false">
                            <svg aria-hidden="true"><use href="#ps-wand"/></svg><span class="ps-mobile-label" x-text="server ? @js(__('pelican-simplify::dashboard.ui.customize_server_page')) : @js(__('pelican-simplify::dashboard.ui.customize_overview'))">{{ __('pelican-simplify::dashboard.ui.customize_overview') }}</span>
                        </button>
                        <div id="ps-overview-appearance-menu" class="ps-dropdown is-end ps-overview-appearance-menu" x-show="appearanceMenu" x-cloak x-transition.opacity>
                            <strong class="ps-dropdown-title" x-text="server ? @js(__('pelican-simplify::dashboard.ui.customize_server_page')) : @js(__('pelican-simplify::dashboard.ui.customize_overview'))">{{ __('pelican-simplify::dashboard.ui.customize_overview') }}</strong>
                            <p class="ps-dropdown-hint">{{ __('pelican-simplify::dashboard.ui.only_for_you_on_this_device') }}</p>
                            {{-- Server pages: the same personal preferences, stored with the overview ones. --}}
                            <template x-if="server">
                                <div class="ps-customize">
                                    <button type="button" class="ps-customize-head is-toggle is-first" :aria-expanded="customizeOpen('server.layout')" aria-controls="ps-customize-server-layout" x-on:click="toggleCustomize('server.layout')"><span>{{ __('pelican-simplify::dashboard.ui.layout') }}</span><svg class="ps-customize-chevron" aria-hidden="true"><use href="#ps-chevron"/></svg></button>
                                    <div class="ps-customize-section" id="ps-customize-server-layout" x-show="customizeOpen('server.layout')">
                                    <button type="button" class="ps-customize-switch" role="switch" :aria-checked="overviewPreferences.serverFullWidth" x-on:click="setOverviewPreference('serverFullWidth', !overviewPreferences.serverFullWidth)">
                                        <span><strong>{{ __('pelican-simplify::dashboard.ui.full_screen_width') }}</strong><small>{{ __('pelican-simplify::dashboard.ui.use_the_whole_width_of_the') }}</small></span>
                                        <span class="ps-switch" :class="{'is-on': overviewPreferences.serverFullWidth}" aria-hidden="true"></span>
                                    </button>
                                    </div>
                                    <button type="button" class="ps-customize-head is-toggle" x-show="!phone" :aria-expanded="customizeOpen('server.console')" aria-controls="ps-customize-server-console" x-on:click="toggleCustomize('server.console')"><span>{{ __('pelican-simplify::dashboard.ui.console') }}</span><svg class="ps-customize-chevron" aria-hidden="true"><use href="#ps-chevron"/></svg></button>
                                    <div class="ps-customize-section" id="ps-customize-server-console" x-show="customizeOpen('server.console')">
                                    <button type="button" class="ps-customize-switch" role="switch" x-show="!phone" :aria-checked="consoleFocus" x-on:click="setConsoleFocus(!consoleFocus)">
                                        <span><strong>{{ __('pelican-simplify::dashboard.ui.bigger_console') }}</strong><small>{{ __('pelican-simplify::dashboard.ui.open_server_pages_with_the_large') }}</small></span>
                                        <span class="ps-switch" :class="{'is-on': consoleFocus}" aria-hidden="true"></span>
                                    </button>
                                    <button type="button" class="ps-customize-switch" role="switch" x-show="!phone" :aria-checked="consolePeek" x-on:click="setConsolePeek(!consolePeek)">
                                        <span><strong>{{ __('pelican-simplify::dashboard.ui.log_in_the_mini_console') }}</strong><small>{{ __('pelican-simplify::dashboard.ui.while_a_section_like_files_is') }}</small></span>
                                        <span class="ps-switch" :class="{'is-on': consolePeek}" aria-hidden="true"></span>
                                    </button>
                                    </div>
                                    <button type="button" class="ps-customize-head is-toggle" x-show="halloweenSeason() && boot.appearance?.seasonal !== false" :aria-expanded="customizeOpen('server.more')" aria-controls="ps-customize-server-more" x-on:click="toggleCustomize('server.more')"><span>{{ __('pelican-simplify::dashboard.ui.more') }}</span><svg class="ps-customize-chevron" aria-hidden="true"><use href="#ps-chevron"/></svg></button>
                                    <div class="ps-customize-section" id="ps-customize-server-more" x-show="customizeOpen('server.more')">
                                    <button type="button" class="ps-customize-switch" role="switch" x-show="halloweenSeason() && boot.appearance?.seasonal !== false" :aria-checked="overviewPreferences.halloween" x-on:click="setOverviewPreference('halloween', !overviewPreferences.halloween)">
                                        <span><strong>{{ __('pelican-simplify::dashboard.ui.halloween_theme') }}</strong><small>{{ __('pelican-simplify::dashboard.ui.a_few_small_halloween_touches_until') }}</small></span>
                                        <span class="ps-switch" :class="{'is-on': overviewPreferences.halloween}" aria-hidden="true"></span>
                                    </button>
                                    </div>
                                </div>
                            </template>
                            <template x-if="!server">
                                <div class="ps-customize">
                                    <button type="button" class="ps-customize-head is-toggle is-first" :aria-expanded="customizeOpen('overview.layout')" aria-controls="ps-customize-overview-layout" x-on:click="toggleCustomize('overview.layout')"><span>{{ __('pelican-simplify::dashboard.ui.layout') }}</span><svg class="ps-customize-chevron" aria-hidden="true"><use href="#ps-chevron"/></svg></button>
                                    <div class="ps-customize-section" id="ps-customize-overview-layout" x-show="customizeOpen('overview.layout')">
                                    <div class="ps-customize-row">
                                        <div><strong>{{ __('pelican-simplify::dashboard.ui.layout') }}</strong><small>{{ __('pelican-simplify::dashboard.ui.cards_small_cards_or_rows') }}</small></div>
                                        <div class="ps-segmented" role="group" aria-label="{{ __('pelican-simplify::dashboard.ui.layout') }}">
                                            <template x-for="view in [{key:'grid',label:@js(__('pelican-simplify::dashboard.ui.cards')),icon:'grid'},{key:'compact',label:@js(__('pelican-simplify::dashboard.ui.compact')),icon:'grid-small'},{key:'list',label:@js(__('pelican-simplify::dashboard.ui.list')),icon:'list'}]" :key="view.key">
                                                <button type="button" :aria-pressed="overviewView===view.key" x-on:click="setOverviewView(view.key)"><svg aria-hidden="true"><use :href="'#ps-'+view.icon"/></svg><span x-text="view.label"></span></button>
                                            </template>
                                        </div>
                                    </div>
                                    <div class="ps-customize-row" x-show="boot.features.host_health">
                                        <div><strong>{{ __('pelican-simplify::dashboard.ui.host_health') }}</strong><small>{{ __('pelican-simplify::dashboard.ui.where_the_health_summary_shows') }}</small></div>
                                        <div class="ps-segmented ps-health-place" role="group" aria-label="{{ __('pelican-simplify::dashboard.ui.host_health') }}">
                                            <template x-for="place in [{key:'top-bar',label:@js(__('pelican-simplify::dashboard.ui.top_bar'))},{key:'above',label:@js(__('pelican-simplify::dashboard.ui.above'))},{key:'bottom',label:@js(__('pelican-simplify::dashboard.ui.bottom'))}]" :key="place.key">
                                                <button type="button" :aria-pressed="overviewPreferences.healthPlace===place.key" x-on:click="setOverviewPreference('healthPlace', place.key); healthPopover=false; healthExpanded=false" x-text="place.label"></button>
                                            </template>
                                        </div>
                                    </div>
                                    <div class="ps-customize-row" x-show="overviewView!=='list' && !overviewCompact">
                                        <div><strong>{{ __('pelican-simplify::dashboard.ui.columns') }}</strong><small>{{ __('pelican-simplify::dashboard.ui.most_cards_per_row') }}</small></div>
                                        <div class="ps-segmented ps-columns-choice" role="group" aria-label="{{ __('pelican-simplify::dashboard.ui.columns') }}">
                                            <template x-for="count in ['auto','2','3','4','5','6']" :key="count">
                                                <button type="button" :aria-pressed="overviewPreferences.columns===count" x-on:click="setOverviewPreference('columns', count)" x-text="count==='auto' ? @js(__('pelican-simplify::dashboard.ui.auto')) : count"></button>
                                            </template>
                                        </div>
                                    </div>
                                    <div class="ps-customize-row" x-show="overviewView!=='list' && !overviewCompact">
                                        <div><strong>{{ __('pelican-simplify::dashboard.ui.card_width') }}</strong><small>{{ __('pelican-simplify::dashboard.ui.size_of_each_card') }}</small></div>
                                        <div class="ps-segmented" role="group" aria-label="{{ __('pelican-simplify::dashboard.ui.card_width') }}">
                                            <template x-for="size in [{key:'narrow',label:@js(__('pelican-simplify::dashboard.ui.narrow'))},{key:'normal',label:@js(__('pelican-simplify::dashboard.ui.normal'))},{key:'wide',label:@js(__('pelican-simplify::dashboard.ui.wide'))}]" :key="size.key">
                                                <button type="button" :aria-pressed="overviewPreferences.cardWidth===size.key" x-on:click="setOverviewPreference('cardWidth', size.key)" x-text="size.label"></button>
                                            </template>
                                        </div>
                                    </div>
                                    <button type="button" class="ps-customize-switch" role="switch" :aria-checked="overviewPreferences.fullWidth" x-on:click="setOverviewPreference('fullWidth', !overviewPreferences.fullWidth)">
                                        <span><strong>{{ __('pelican-simplify::dashboard.ui.full_screen_width') }}</strong><small>{{ __('pelican-simplify::dashboard.ui.use_the_whole_width_of_the_2') }}</small></span>
                                        <span class="ps-switch" :class="{'is-on': overviewPreferences.fullWidth}" aria-hidden="true"></span>
                                    </button>
                                    </div>
                                    <button type="button" class="ps-customize-head is-toggle" :aria-expanded="customizeOpen('overview.cards')" aria-controls="ps-customize-overview-cards" x-on:click="toggleCustomize('overview.cards')"><span>{{ __('pelican-simplify::dashboard.ui.cards') }}</span><svg class="ps-customize-chevron" aria-hidden="true"><use href="#ps-chevron"/></svg></button>
                                    <div class="ps-customize-section" id="ps-customize-overview-cards" x-show="customizeOpen('overview.cards')">
                                    <div class="ps-customize-row">
                                        <div><strong>{{ __('pelican-simplify::dashboard.ui.card_icon') }}</strong><small>{{ __('pelican-simplify::dashboard.ui.the_big_picture_at_the_top') }}</small></div>
                                        <div class="ps-segmented" role="group" aria-label="{{ __('pelican-simplify::dashboard.ui.card_icon') }}">
                                            <button type="button" :aria-pressed="overviewPreferences.largeIcon==='server'" x-on:click="setOverviewPreference('largeIcon','server')">{{ __('pelican-simplify::dashboard.ui.server') }}</button>
                                            <button type="button" :aria-pressed="overviewPreferences.largeIcon==='egg'" x-on:click="setOverviewPreference('largeIcon','egg')">{{ __('pelican-simplify::dashboard.ui.game') }}</button>
                                        </div>
                                    </div>
                                    <div class="ps-customize-row">
                                        <div><strong>{{ __('pelican-simplify::dashboard.ui.card_title') }}</strong><small>{{ __('pelican-simplify::dashboard.ui.show_the_alias_you_gave_it') }}</small></div>
                                        <div class="ps-segmented" role="group" aria-label="{{ __('pelican-simplify::dashboard.ui.card_title') }}">
                                            <button type="button" :aria-pressed="overviewPreferences.largeName==='alias'" x-on:click="setOverviewPreference('largeName','alias')">{{ __('pelican-simplify::dashboard.ui.alias') }}</button>
                                            <button type="button" :aria-pressed="overviewPreferences.largeName==='name'" x-on:click="setOverviewPreference('largeName','name')">{{ __('pelican-simplify::dashboard.ui.name') }}</button>
                                        </div>
                                    </div>
                                    <span class="ps-customize-sub">{{ __('pelican-simplify::dashboard.ui.show_on_each_card') }}</span>
                                    <template x-for="option in [{key:'showMotd',label:@js(__('pelican-simplify::dashboard.ui.message_of_the_day')),hint:@js(__('pelican-simplify::dashboard.ui.the_minecraft_motd_under_the_server'))},{key:'showCpu',label:@js(__('pelican-simplify::dashboard.ui.cpu_usage')),hint:@js(__('pelican-simplify::dashboard.ui.a_small_live_cpu_graph'))},{key:'showRam',label:@js(__('pelican-simplify::dashboard.ui.memory_usage')),hint:@js(__('pelican-simplify::dashboard.ui.a_small_live_ram_graph'))}]" :key="option.key">
                                        <button type="button" class="ps-customize-switch" role="switch" :aria-checked="overviewPreferences[option.key]" x-on:click="setOverviewPreference(option.key, !overviewPreferences[option.key])">
                                            <span><strong x-text="option.label"></strong><small x-text="option.hint"></small></span>
                                            <span class="ps-switch" :class="{'is-on': overviewPreferences[option.key]}" aria-hidden="true"></span>
                                        </button>
                                    </template>
                                    <div class="ps-customize-row">
                                        <div><strong>{{ __('pelican-simplify::dashboard.ui.quick_actions') }}</strong><small>{{ __('pelican-simplify::dashboard.ui.start_stop_and_restart_buttons_on') }}</small></div>
                                        <div class="ps-segmented" role="group" aria-label="{{ __('pelican-simplify::dashboard.ui.quick_actions') }}">
                                            <template x-for="mode in [{key:'off',label:@js(__('pelican-simplify::dashboard.ui.off'))},{key:'on',label:@js(__('pelican-simplify::dashboard.ui.on'))},{key:'hover',label:@js(__('pelican-simplify::dashboard.ui.hover'))}]" :key="mode.key">
                                                <button type="button" :aria-pressed="overviewPreferences.quickActions===mode.key" x-on:click="setOverviewPreference('quickActions', mode.key)" x-text="mode.label"></button>
                                            </template>
                                        </div>
                                    </div>
                                    </div>
                                    <button type="button" class="ps-customize-head is-toggle" :aria-expanded="customizeOpen('overview.grouping')" aria-controls="ps-customize-overview-grouping" x-on:click="toggleCustomize('overview.grouping')"><span>{{ __('pelican-simplify::dashboard.ui.grouping') }}</span><svg class="ps-customize-chevron" aria-hidden="true"><use href="#ps-chevron"/></svg></button>
                                    <div class="ps-customize-section" id="ps-customize-overview-grouping" x-show="customizeOpen('overview.grouping')">
                                    <div class="ps-customize-row">
                                        <div><strong>{{ __('pelican-simplify::dashboard.ui.group_by_node') }}</strong><small>{{ __('pelican-simplify::dashboard.ui.auto_groups_servers_per_node_once') }}</small></div>
                                        <div class="ps-segmented" role="group" aria-label="{{ __('pelican-simplify::dashboard.ui.group_by_node') }}">
                                            <template x-for="mode in [{key:'auto',label:@js(__('pelican-simplify::dashboard.ui.auto'))},{key:'always',label:@js(__('pelican-simplify::dashboard.ui.always'))},{key:'off',label:@js(__('pelican-simplify::dashboard.ui.off'))}]" :key="mode.key">
                                                <button type="button" :aria-pressed="overviewPreferences.groupByNode===mode.key" x-on:click="setOverviewPreference('groupByNode', mode.key)" x-text="mode.label"></button>
                                            </template>
                                        </div>
                                    </div>
                                    <button type="button" class="ps-customize-switch" role="switch" :aria-checked="overviewPreferences.showGroupsButton" x-on:click="setOverviewPreference('showGroupsButton', !overviewPreferences.showGroupsButton)">
                                        <span><strong>{{ __('pelican-simplify::dashboard.ui.server_groups_button') }}</strong><small>{{ __('pelican-simplify::dashboard.ui.a_button_in_the_top_bar') }}</small></span>
                                        <span class="ps-switch" :class="{'is-on': overviewPreferences.showGroupsButton}" aria-hidden="true"></span>
                                    </button>
                                    </div>
                                    <button type="button" class="ps-customize-head is-toggle" x-show="(halloweenSeason() && boot.appearance?.seasonal !== false) || boot.settings_url" :aria-expanded="customizeOpen('overview.more')" aria-controls="ps-customize-overview-more" x-on:click="toggleCustomize('overview.more')"><span>{{ __('pelican-simplify::dashboard.ui.more') }}</span><svg class="ps-customize-chevron" aria-hidden="true"><use href="#ps-chevron"/></svg></button>
                                    <div class="ps-customize-section" id="ps-customize-overview-more" x-show="customizeOpen('overview.more')">
                                    <button type="button" class="ps-customize-switch" role="switch" x-show="halloweenSeason() && boot.appearance?.seasonal !== false" :aria-checked="overviewPreferences.halloween" x-on:click="setOverviewPreference('halloween', !overviewPreferences.halloween)">
                                        <span><strong>{{ __('pelican-simplify::dashboard.ui.halloween_theme') }}</strong><small>{{ __('pelican-simplify::dashboard.ui.a_few_small_halloween_touches_until') }}</small></span>
                                        <span class="ps-switch" :class="{'is-on': overviewPreferences.halloween}" aria-hidden="true"></span>
                                    </button>
                                    {{-- Server colours are a panel setting; point administrators at it from here. Group colours live with each group. --}}
                                    <div class="ps-customize-row" x-show="boot.settings_url">
                                        <div><strong>{{ __('pelican-simplify::dashboard.ui.server_colors') }}</strong><small>{{ __('pelican-simplify::dashboard.ui.one_color_for_every_server_or') }}</small></div>
                                        <button type="button" class="ps-btn is-sm" x-on:click="appearanceMenu=false; openPanelSettings('appearance')">{{ __('pelican-simplify::dashboard.ui.open_settings') }}</button>
                                    </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <button type="button" x-show="installable && !embedded" x-cloak x-on:click="installApp()" class="ps-btn is-icon is-subtle ps-nav-view" data-tip="{{ __('pelican-simplify::dashboard.ui.install_app') }}" aria-label="{{ __('pelican-simplify::dashboard.ui.install_app') }}">
                        <svg aria-hidden="true"><use href="#ps-download"/></svg><span class="ps-mobile-label">{{ __('pelican-simplify::dashboard.ui.install_app') }}</span>
                    </button>

                    {{-- Phones list these buttons as a menu: first what everyone uses, then the admin buttons under their own heading. --}}
                    <span class="ps-label ps-nav-heading" x-show="adminPages().length">{{ __('pelican-simplify::dashboard.ui.panel') }}</span>
                    {{-- Admin tools: Pelican admin pages Simplify does not replace, opened in a popup (admins only). --}}
                    <div class="ps-notify ps-nav-admin" x-show="adminPages().length && !(hostMode && !server)" x-on:click.outside="adminMenu=false" x-on:keydown.escape.stop="adminMenu=false; $refs.adminButton.focus()">
                        <button type="button" x-ref="adminButton" class="ps-btn is-icon is-subtle" data-tip="{{ __('pelican-simplify::dashboard.ui.admin_tools') }}" aria-label="{{ __('pelican-simplify::dashboard.ui.admin_tools') }}" aria-controls="ps-admin-menu" :aria-expanded="adminMenu"
                                x-on:click="adminMenu=!adminMenu; notifyMenu=false; appearanceMenu=false; groupsMenu=false; accountMenu=false">
                            <svg aria-hidden="true"><use href="#ps-shield"/></svg><span class="ps-mobile-label">{{ __('pelican-simplify::dashboard.ui.admin_tools') }}</span>
                        </button>
                        <div id="ps-admin-menu" class="ps-dropdown is-end ps-admin-menu" x-show="adminMenu" x-cloak x-transition.opacity>
                            <strong class="ps-dropdown-title">{{ __('pelican-simplify::dashboard.ui.admin_tools') }}</strong>
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
                                    <button type="button" class="ps-admin-health-title" x-on:click="openAdminPage(adminHealthPage())"><svg aria-hidden="true"><use href="#ps-heart"/></svg><span>{{ __('pelican-simplify::dashboard.ui.host_health_checks') }}</span></button>
                                    <div class="ps-health-grid">
                                        @include('pelican-simplify::health-checks', ['open' => 'openAdminPage(adminHealthPage())'])
                                    </div>
                                </div>
                            </template>
                            <template x-for="section in hostSections().filter((x) => x.key === 'legacy')" :key="section.key">
                                <div class="ps-admin-legacy">
                                    <span class="ps-label">{{ __('pelican-simplify::dashboard.ui.legacy') }}</span>
                                    <template x-for="item in section.pages" :key="item.key">
                                        <button type="button" class="ps-btn is-sm is-subtle" x-on:click="openAdminPage(item)" x-text="item.label"></button>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </div>

                    <a x-show="boot.admin_url" :href="server?.admin_server_url || boot.admin_url" class="ps-btn is-icon is-subtle ps-nav-admin" data-tip="{{ __('pelican-simplify::dashboard.ui.legacy_panel') }}" aria-label="{{ __('pelican-simplify::dashboard.ui.legacy_panel') }}">
                        <svg aria-hidden="true"><use href="#ps-legacy"/></svg><span class="ps-mobile-label">{{ __('pelican-simplify::dashboard.ui.legacy_panel') }}</span>
                    </a>
                    @if(\Simplify\Panel\Services\Extensions::web()) @include('server-directory::simplify.navigation') @endif
                    {{-- Account: avatar with inline profile and sign-out actions. --}}
                    <div class="ps-account" x-data="{ themeOpen: false, pluginMenu: false }" x-effect="accountMenu || (themeOpen = pluginMenu = false)" :class="{'account-open': accountMenu}" x-on:click.outside="accountMenu=false" x-on:keydown.escape.stop="accountMenu=false">
                        <button type="button" x-on:click="accountMenu=!accountMenu; appearanceMenu=false; groupsMenu=false; notifyMenu=false" class="ps-btn ps-account-button" aria-label="{{ __('pelican-simplify::dashboard.ui.profile_options') }}" aria-controls="ps-account-actions" :aria-expanded="accountMenu">
                            <span class="ps-account-avatar" x-data="{failed:false}">
                                <img x-show="boot.avatar && !failed" :src="boot.avatar" alt="" x-on:error="failed=true">
                                <svg x-show="!boot.avatar || failed" aria-hidden="true"><use href="#ps-account"/></svg>
                            </span>
                            <span class="ps-account-username" x-text="boot.user"></span>
                        </button>
                        <div id="ps-account-actions" class="ps-account-menu" :style="{'--ps-account-count': (adminPages().length && !server ? 5 : 4) + (pluginPages().length ? 1 : 0) + (themeOpen ? 2 : 0) - (themeForced ? 1 : 0)}" :inert="!accountMenu" :aria-hidden="!accountMenu">
                            <div class="ps-account-actions-inner">
                                <button type="button" class="ps-btn is-icon" x-on:click="openAccount()" data-tip="{{ __('pelican-simplify::dashboard.ui.profile_settings') }}" aria-label="{{ __('pelican-simplify::dashboard.ui.profile_settings') }}" aria-haspopup="dialog">
                                    <svg aria-hidden="true"><use href="#ps-account"/></svg>
                                </button>
                                {{-- Theme: the current choice; clicking it unfolds Light, Dark and System. --}}
                                <button type="button" class="ps-btn is-icon" x-show="!themeOpen && !themeForced" x-on:click="themeOpen=true" :data-tip="@js(__('pelican-simplify::dashboard.ui.theme_prefix'))+' '+(theme==='light' ? @js(__('pelican-simplify::dashboard.ui.light')) : theme==='dark' ? @js(__('pelican-simplify::dashboard.ui.dark')) : @js(__('pelican-simplify::dashboard.ui.system')))" aria-label="{{ __('pelican-simplify::dashboard.ui.choose_theme') }}" :aria-expanded="themeOpen">
                                    <svg aria-hidden="true"><use :href="theme==='light' ? '#ps-sun' : theme==='dark' ? '#ps-moon' : '#ps-monitor'"/></svg>
                                </button>
                                <template x-for="t in ['light','dark','system']" :key="t">
                                    <button type="button" class="ps-btn is-icon ps-theme-option" x-show="themeOpen" :aria-pressed="theme===t" x-on:click="setTheme(t); themeOpen=false" :data-tip="t==='light' ? @js(__('pelican-simplify::dashboard.ui.light')) : t==='dark' ? @js(__('pelican-simplify::dashboard.ui.dark')) : @js(__('pelican-simplify::dashboard.ui.system'))" :aria-label="Simplify.fill(@js(__('pelican-simplify::dashboard.ui.theme_option')), { theme: { light: @js(__('pelican-simplify::dashboard.ui.light')), dark: @js(__('pelican-simplify::dashboard.ui.dark')), system: @js(__('pelican-simplify::dashboard.ui.system')) }[t] })">
                                        <svg aria-hidden="true"><use :href="t==='light' ? '#ps-sun' : t==='dark' ? '#ps-moon' : '#ps-monitor'"/></svg>
                                    </button>
                                </template>
                                <button type="button" class="ps-btn is-icon" x-on:click="openNotifications()" data-tip="{{ __('pelican-simplify::dashboard.ui.notifications_on_this_device') }}" aria-label="{{ __('pelican-simplify::dashboard.ui.notifications_on_this_device') }}" aria-haspopup="dialog">
                                    <svg aria-hidden="true"><use href="#ps-bell"/></svg>
                                </button>
                                <button type="button" class="ps-btn is-icon" x-show="adminPages().length && !server" x-on:click="setHostMode(!hostMode); accountMenu=false" :data-tip="hostMode ? @js(__('pelican-simplify::dashboard.ui.switch_to_simple_mode')) : @js(__('pelican-simplify::dashboard.ui.switch_to_host_mode'))" :aria-label="hostMode ? @js(__('pelican-simplify::dashboard.ui.switch_to_simple_mode')) : @js(__('pelican-simplify::dashboard.ui.switch_to_host_mode'))">
                                    <svg aria-hidden="true"><use :href="hostMode ? '#ps-grid' : '#ps-host'"/></svg>
                                </button>
                                {{-- Account-level pages other plugins add (such as Legal Pages), listed below the menu. --}}
                                <button type="button" class="ps-btn is-icon" x-show="pluginPages().length" x-on:click="pluginMenu=!pluginMenu; themeOpen=false" data-tip="{{ __('pelican-simplify::dashboard.ui.pages_from_other_plugins') }}" aria-label="{{ __('pelican-simplify::dashboard.ui.pages_from_other_plugins') }}" aria-controls="ps-plugin-pages" :aria-expanded="pluginMenu">
                                    <svg aria-hidden="true"><use href="#ps-plugins"/></svg>
                                </button>
                                <form method="post" action="{{ route('filament.app.auth.logout') }}">@csrf
                                    <button type="submit" class="ps-btn is-icon" data-tip="{{ __('pelican-simplify::dashboard.ui.sign_out') }}" aria-label="{{ __('pelican-simplify::dashboard.ui.sign_out') }}"><svg aria-hidden="true"><use href="#ps-logout"/></svg></button>
                                </form>
                            </div>
                        </div>
                        <div id="ps-plugin-pages" class="ps-dropdown is-end ps-plugin-pages" x-show="accountMenu && pluginMenu" x-cloak x-transition.opacity>
                            <strong class="ps-dropdown-title">{{ __('pelican-simplify::dashboard.ui.pages_from_other_plugins') }}</strong>
                            <div class="ps-notify-list">
                                <template x-for="page in pluginPages()" :key="page.key">
                                    <button type="button" class="ps-notify-item" style="--ps-tone: #2dd4bf" x-on:click="openPluginPage(page)">
                                        <svg aria-hidden="true"><use :href="page.new_tab ? '#ps-link' : '#ps-plugins'"/></svg><strong x-text="page.label"></strong><small x-text="Simplify.fill(@js(__('pelican-simplify::dashboard.ui.from_plugin')), { plugin: page.plugin })+(page.new_tab ? ' · '+@js(__('pelican-simplify::dashboard.ui.opens_new_tab')) : '')"></small>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </nav>
        </header>

        {{-- Host mode (admins, large hosts): one frame with a menu on the left (admin pages as a tree, or the servers
             per node) and the page or server on the right; each side scrolls on its own. Phones open the menu as a sheet. --}}
        <section class="ps-host" :class="{'menu-open': hostMenuOpen}" x-show="!server && hostMode" x-cloak :style="accentStyle('overview')" x-on:keydown.escape="hostMenuOpen=false">
                <button type="button" class="ps-btn ps-host-menu-toggle" x-on:click="hostMenuOpen=true" aria-controls="ps-host-side" :aria-expanded="hostMenuOpen">
                    <svg aria-hidden="true"><use href="#ps-list"/></svg><span x-text="hostServer ? cardNames(hostServer).large : (hostPage && hostSection?.pages.length > 1 ? hostSection.label+' · '+hostPage.label : hostSection?.label || @js(__('pelican-simplify::dashboard.ui.admin')))"></span><svg class="ps-host-menu-chevron" aria-hidden="true"><use href="#ps-chevron"/></svg>
                </button>
                <div class="ps-host-backdrop" x-show="hostMenuOpen" x-cloak x-on:click="hostMenuOpen=false"></div>
                <aside id="ps-host-side" class="ps-host-side" aria-label="{{ __('pelican-simplify::dashboard.ui.host_menu') }}">
                    <div class="ps-host-tabs" role="group" aria-label="{{ __('pelican-simplify::dashboard.ui.menu') }}">
                        <button type="button" :aria-pressed="hostTab==='admin'" x-on:click="hostTab='admin'"><svg aria-hidden="true"><use href="#ps-shield"/></svg>{{ __('pelican-simplify::dashboard.ui.admin') }}</button>
                        <button type="button" :aria-pressed="hostTab==='servers'" x-on:click="hostTab='servers'"><svg aria-hidden="true"><use href="#ps-host"/></svg>{{ __('pelican-simplify::dashboard.ui.servers') }}<small x-text="(boot.servers||[]).length"></small></button>
                        <button type="button" class="ps-btn is-icon is-subtle ps-host-menu-close" x-on:click="hostMenuOpen=false" aria-label="{{ __('pelican-simplify::dashboard.ui.close_menu') }}"><svg aria-hidden="true"><use href="#ps-close"/></svg></button>
                    </div>
                    <div class="ps-host-side-scroll">
                        {{-- Admin pages as a tree: every section with its pages hung underneath; a section with one page opens it directly. --}}
                        <nav class="ps-host-tree" x-show="hostTab==='admin'" aria-label="{{ __('pelican-simplify::dashboard.ui.admin_sections') }}">
                            <template x-for="section in hostSections().filter((x) => !['health', 'legacy'].includes(x.key))" :key="section.key">
                                <div class="ps-host-group" :style="{'--ps-tone': section.color}">
                                    <button type="button" class="ps-host-group-head" :aria-current="hostSection?.key===section.key && !hostServer && (section.pages.length === 1 || !hostPage) ? 'page' : null" x-on:click="openHostSection(section)">
                                        <svg aria-hidden="true"><use :href="'#ps-'+section.icon"/></svg><span x-text="section.label"></span>
                                    </button>
                                    <div class="ps-host-branch" x-show="section.pages.length > 1">
                                        <template x-for="item in section.pages" :key="item.key">
                                            <button type="button" class="ps-host-leaf" :aria-current="hostSection?.key===section.key && hostPage?.key===item.key && !hostServer ? 'page' : null" x-on:click="openHostLeaf(section, item)">
                                                <svg aria-hidden="true"><use :href="'#ps-'+item.icon"/></svg><span x-text="item.label"></span>
                                            </button>
                                        </template>
                                    </div>
                                </div>
                            </template>
                            <template x-for="section in hostSections().filter((x) => x.key === 'legacy')" :key="section.key">
                                <div class="ps-host-legacy">
                                    <span class="ps-label">{{ __('pelican-simplify::dashboard.ui.legacy') }}</span>
                                    <template x-for="item in section.pages" :key="item.key">
                                        <button type="button" x-on:click="openHostLeaf(section, item)" :aria-current="hostPage?.key===item.key && !hostServer ? 'page' : null" x-text="item.label"></button>
                                    </template>
                                </div>
                            </template>
                        </nav>
                        {{-- Servers per node; a server opens on the right, ctrl/cmd-click opens its full page. --}}
                        <div class="ps-host-server-list" x-show="hostTab==='servers'">
                            <template x-for="node in hostNodes()" :key="node.id">
                                <div class="ps-host-node">
                                    <span class="ps-label"><svg aria-hidden="true"><use href="#ps-host"/></svg><span x-text="node.name"></span><small x-text="Simplify.choice(@js(__('pelican-simplify::dashboard.ui.server_count')), node.servers.length)"></small></span>
                                    <template x-for="s in node.servers" :key="s.uuid">
                                        <a class="ps-host-server" :href="s.linked ? s.url : null" :style="accentStyle(s.uuid)" :aria-disabled="!s.linked" :aria-current="hostServer?.uuid===s.uuid ? 'page' : null" x-on:click="if (s.linked && !$event.ctrlKey && !$event.metaKey && !$event.shiftKey) { $event.preventDefault(); openHostServer(s); }">
                                            <i class="ps-host-dot ps-status" :class="s.status" :aria-label="s.status"></i>
                                            <strong x-text="cardNames(s).large"></strong>
                                            <small x-text="hostServerVersion(s)"></small>
                                            <span class="ps-host-players"><svg aria-hidden="true"><use href="#ps-players"/></svg><span x-text="playerCount(s)"></span></span>
                                        </a>
                                    </template>
                                </div>
                            </template>
                            <p class="ps-empty" x-show="!(boot.servers||[]).length">{{ __('pelican-simplify::dashboard.ui.no_servers_yet') }}</p>
                        </div>
                    </div>
                    {{-- Health at the bottom of the menu: state, CPU and RAM, and one mark per health check. --}}
                    <template x-for="section in hostSections().filter((x) => x.key === 'health')" :key="section.key">
                        <div class="ps-host-health-bar" :class="{'is-current': hostSection?.key==='health' && !hostServer}">
                            <button type="button" class="ps-host-health-open" x-on:click="openHostSection(section)">
                                <span class="ps-host-health-state"><svg class="ps-health-heart" :class="boot.host_health?.state || 'unavailable'" aria-hidden="true"><use href="#ps-heart"/></svg><strong x-text="boot.features.host_health ? (boot.host_health?.label || @js(__('pelican-simplify::dashboard.ui.unavailable'))) : @js(__('pelican-simplify::dashboard.ui.health'))"></strong><small x-show="boot.features.host_health" x-text="Simplify.fill(@js(__('pelican-simplify::dashboard.ui.hosts_available')), { available: boot.host_health?.available ?? 0, count: boot.host_health?.hosts ?? 0 })"></small></span>
                                <span class="ps-host-health-meters" x-show="boot.features.host_health">
                                    <template x-for="kind in ['cpu', 'memory']" :key="kind">
                                        <span><span><span x-text="kind==='cpu' ? @js(__('pelican-simplify::dashboard.ui.cpu')) : @js(__('pelican-simplify::dashboard.ui.ram'))"></span> <b x-text="hostValue(kind)"></b></span><span class="ps-meter"><span :style="{width: hostFill(kind)+'%'}"></span></span></span>
                                    </template>
                                </span>
                            </button>
                            <div class="ps-health-grid" x-show="healthChecks().length" role="group" aria-label="{{ __('pelican-simplify::dashboard.ui.current_health_checks') }}">
                                @include('pelican-simplify::health-checks', ['open' => 'openHostSection(hostSections().find((x) => x.key === \'health\'))'])
                            </div>
                        </div>
                    </template>
                </aside>
                <div class="ps-host-main">
                    @include('pelican-simplify::announcement-chips')
                    <header class="ps-host-head" x-show="hostServer" x-cloak :style="accentStyle(hostServer?.uuid || 'overview')">
                        <i class="ps-host-dot ps-status" :class="hostServer?.status"></i>
                        <h2 class="ps-host-server-name" x-text="hostServer ? cardNames(hostServer).large : ''"></h2>
                        <small x-text="hostServer ? hostServerVersion(hostServer) : ''"></small>
                        <span class="ps-host-head-actions">
                            <button type="button" class="ps-btn is-sm is-icon" x-on:click="closeHostServer()" data-tip="{{ __('pelican-simplify::dashboard.ui.close') }}" aria-label="{{ __('pelican-simplify::dashboard.ui.close_server') }}"><svg aria-hidden="true"><use href="#ps-close"/></svg></button>
                        </span>
                    </header>
                    <header class="ps-host-head is-page" x-show="!hostServer && hostSection" :style="{'--ps-tone': hostSection?.color}">
                        <svg class="ps-host-head-icon" aria-hidden="true"><use :href="'#ps-'+(hostSection?.icon||'host')"/></svg>
                        <h2 x-text="hostSection?.label"></h2>
                        <small x-show="hostSection?.pages.length > 1" x-text="hostPage?.label"></small>
                    </header>
                    <div class="ps-host-frame is-server" x-show="hostServer" :style="accentStyle(hostServer?.uuid || 'overview')">
                        <template x-for="s in hostServer ? [hostServer] : []" :key="s.uuid">
                            <iframe :src="frameUrl(s.url)" :title="s.name"></iframe>
                        </template>
                    </div>
                    <div class="ps-host-frame" x-show="!hostServer">
                        <template x-if="hostPage">
                            <iframe data-ps-frame="native" data-ps-host="1" :data-ps-accent="currentAccentHex()" :src="frameUrl(hostPage.url)" :title="hostPage.label" x-on:load="styleFrame($el, 'native')"></iframe>
                        </template>
                    </div>
                </div>
        </section>

        {{-- Overview -------------------------------------------------------------------------------- --}}
        <section x-show="!server && !hostMode" class="ps-overview" :class="{'is-list': overviewView==='list'}" :style="{'--grid-columns': overviewColumns(), ...overviewGridStyle()}">
            <div class="ps-server-scroll" tabindex="0" role="region" aria-label="{{ __('pelican-simplify::dashboard.ui.servers') }}">
                <div class="ps-server-stage" :class="{'is-list': overviewView==='list'}">
                    @include('pelican-simplify::announcement-bars')
                    <div class="ps-overview-toolbar" x-init="scrollHint($el)">
                        <div class="ps-overview-filters">
                            <div class="ps-game-filters" role="group" aria-label="{{ __('pelican-simplify::dashboard.ui.filter_servers_by_game') }}">
                                <button type="button" class="ps-chip" :aria-pressed="gameFilter==='all'" x-on:click="setGameFilter('all')">{{ __('pelican-simplify::dashboard.ui.all') }}</button>
                                <template x-for="game in gameOptions()" :key="game.key">
                                    <button type="button" class="ps-chip" :aria-pressed="gameFilter===game.key" x-on:click="setGameFilter(game.key)" x-text="game.label"></button>
                                </template>
                            </div>
                            <div class="ps-node-filters" x-show="nodeOptions().length > 1" role="group" aria-label="{{ __('pelican-simplify::dashboard.ui.filter_servers_by_node') }}">
                                <input type="search" class="ps-input ps-node-search" x-show="nodeOptions().length > 3" x-model.debounce.200ms="nodeSearch" placeholder="{{ __('pelican-simplify::dashboard.ui.search_nodes') }}" aria-label="{{ __('pelican-simplify::dashboard.ui.search_nodes') }}">
                                <button type="button" class="ps-chip" :aria-pressed="nodeFilter==='all'" x-on:click="nodeFilter='all'"><svg aria-hidden="true"><use href="#ps-host"/></svg>{{ __('pelican-simplify::dashboard.ui.all_nodes') }}</button>
                                <template x-for="node in nodeOptions().filter((n) => !nodeSearch || n.name.toLowerCase().includes(nodeSearch.toLowerCase()))" :key="node.id">
                                    <button type="button" class="ps-chip" :aria-pressed="nodeFilter===node.id" x-on:click="nodeFilter=node.id" x-text="node.name"></button>
                                </template>
                            </div>
                            {{-- Administrators see everyone's servers; like Pelican's own list they can narrow it to their own (owner or subuser) or other people's. --}}
                            <div class="ps-owner-filters" x-show="ownerFilterShown()" role="group" aria-label="{{ __('pelican-simplify::dashboard.ui.filter_servers_by_owner') }}">
                                <template x-for="scope in [{ key: 'all', label: @js(__('pelican-simplify::dashboard.ui.all_servers')) }, { key: 'mine', label: @js(__('pelican-simplify::dashboard.ui.my_servers')) }, { key: 'others', label: @js(__('pelican-simplify::dashboard.ui.others')) }]" :key="scope.key">
                                    <button type="button" class="ps-chip" :aria-pressed="overviewPreferences.serverScope===scope.key" x-on:click="setOverviewPreference('serverScope', scope.key)"><svg x-show="scope.key==='all'" aria-hidden="true"><use href="#ps-account"/></svg><span x-text="scope.label"></span></button>
                                </template>
                            </div>
                        </div>
                        {{-- Create buttons at the end of the filter row, unless they sit in the top bar with host health. --}}
                        <div class="ps-create-buttons" x-show="healthPlace()!=='top-bar' && createButtons().length">
                            <template x-for="choice in createButtons()" :key="choice.key">
                                <button type="button" class="ps-btn ps-create-button" :class="{'is-main': choice.primary}" x-on:click="openCreate(choice.key)" :aria-label="choice.label" aria-haspopup="dialog"><span class="ps-create-icon"><svg aria-hidden="true"><use :href="'#ps-'+(choice.primary ? 'host' : choice.icon)"/></svg><span class="ps-create-badge" x-show="choice.primary"><svg aria-hidden="true"><use href="#ps-plus"/></svg></span></span><span x-text="choice.label"></span></button>
                            </template>
                        </div>
                    </div>
                    {{-- Buttons other plugins add to Pelican's server list (such as Create server from User Creatable Servers). --}}
                    <div class="ps-plugin-actions" x-show="pluginActions('app').length" role="group" aria-label="{{ __('pelican-simplify::dashboard.ui.buttons_from_other_plugins') }}">
                        <template x-for="action in pluginActions('app')" :key="action.key">
                            <button type="button" class="ps-btn" :disabled="action.disabled || pluginActionBusy===action.key" :aria-busy="pluginActionBusy===action.key" x-on:click="runPluginAction(action)" :data-tip="action.plugin ? Simplify.fill(@js(__('pelican-simplify::dashboard.ui.from_the_plugin')), { plugin: action.plugin }) : @js(__('pelican-simplify::dashboard.ui.from_another_plugin'))">
                                <span class="ps-plugin-action-icon" x-show="action.icon" x-html="action.icon" aria-hidden="true"></span><span x-text="action.label"></span>
                            </button>
                        </template>
                    </div>
                    {{-- Widgets other plugins add to Pelican's server list (such as resource limits), sized to their content. --}}
                    <template x-if="boot.plugin_widgets_url">
                        <div class="ps-plugin-widgets" :class="{'is-empty': !pluginWidgetHeights.app}" :style="{'--ps-widgets-height': (pluginWidgetHeights.app || 0)+'px'}">
                            <iframe data-ps-frame="native" :data-ps-accent="currentAccentHex()" :src="frameUrl(boot.plugin_widgets_url)" title="{{ __('pelican-simplify::dashboard.ui.plugin_widgets') }}" x-on:load="pluginWidgetsLoaded($el, 'app')"></iframe>
                        </div>
                    </template>
                    <div class="ps-server-grid" :class="{'is-list': overviewView==='list', 'is-compact': overviewView==='compact', 'has-motd': overviewPreferences.showMotd, 'no-metrics': !overviewMetrics().length, 'no-quick-actions': quickActionsMode()==='off', 'quick-actions-on': quickActionsMode()==='on'}">
                        <template x-for="s in overviewItems()" :key="s.uuid">
                            <div class="ps-server-entry" :class="{'is-node-heading': !!s.isNodeHeader, 'has-card-motd': overviewPreferences.showMotd && s.game==='minecraft' && !!s.motd, 'has-power': quickActionsMode()!=='off' && s.linked && Object.values(s.permissions || {}).some(Boolean), 'power-shown': quickActionsMode()==='on', 'confirming-power': powerConfirmation?.uuid===s.uuid}" :style="accentStyle(s.uuid)">
                                <template x-if="s.isNodeHeader">
                                    <h2 class="ps-node-heading" :class="{'has-color': !!s.color}"><svg aria-hidden="true"><use :href="'#ps-'+(s.icon || 'host')"/></svg><span x-text="s.name"></span><small x-text="Simplify.choice(@js(__('pelican-simplify::dashboard.ui.server_count')), s.count)"></small></h2>
                                </template>
                                <template x-if="!s.isNodeHeader">
                                <article class="ps-server-card" :class="{'locked': !s.linked}">
                                    <span class="ps-offline-artwork" x-show="s.status==='offline'" aria-label="{{ __('pelican-simplify::dashboard.ui.server_offline') }}"><svg aria-hidden="true"><use href="#ps-offline"/></svg></span>
                                    <span class="ps-card-background ps-banner-color" :class="{'is-auto': s.background_id==='color'}" x-show="bannerColor(s) && s.status!=='offline'" :style="bannerColor(s)" aria-hidden="true"></span>
                                    <img class="ps-card-background" x-show="s.background && s.status!=='offline'" :src="safeImage(s.background)" alt="" loading="lazy" x-on:error="$el.style.visibility='hidden'">
                                    <button class="ps-server-open" x-on:click="s.linked && (location.href=s.url)" :disabled="!s.linked" :aria-label="s.linked ? Simplify.fill(@js(__('pelican-simplify::dashboard.ui.open_name')), { name: s.name }) : Simplify.fill(@js(__('pelican-simplify::dashboard.ui.not_assigned')), { name: s.name })">
                                        <div class="ps-card-head">
                                            <span class="ps-card-icons" :class="{'egg-primary': overviewPreferences.largeIcon==='egg' && !!(s.egg_icon || s.software_icon)}">
                                                <span class="ps-card-icon">
                                                    <img x-show="s.server_icon || s.icon" :src="safeImage(s.server_icon || s.icon)" alt="{{ __('pelican-simplify::dashboard.ui.server_icon') }}" loading="lazy" x-on:load="$el.style.visibility=''" x-on:error="$el.style.visibility='hidden'">
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
                                            <div class="ps-card-motd ps-motd-preview ps-motd-display" x-show="overviewPreferences.showMotd && s.game==='minecraft' && s.motd" x-data="{observer:null,init(){this.observer=new ResizeObserver(()=>this.fitMotd(this.$el));this.observer.observe(this.$el)},destroy(){this.observer?.disconnect()}}" x-effect="s.motd; overviewPreferences.showMotd; $nextTick(()=>fitMotd($el))"><template x-for="(part,index) in motdPreview(s.motd)" :key="index"><span :style="part.style" x-text="part.text"></span></template></div>
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
                                        <div class="ps-card-bottom" x-show="overviewView!=='list'">
                                            <span x-text="playerCount(s)"></span>
                                            <svg aria-hidden="true"><use :href="s.linked ? '#ps-chevron' : '#ps-lock'"/></svg>
                                        </div>
                                    </button>
                                    <div class="ps-card-footer" x-show="boot.web || overviewView==='list'">
                                        @if(\Simplify\Panel\Services\Extensions::web()) @include('server-directory::simplify.visibility') @endif
                                        <button type="button" class="ps-list-player-count" x-show="overviewView==='list'" x-on:click="s.linked && (location.href=s.url)" :disabled="!s.linked" :aria-label="s.linked ? Simplify.fill(@js(__('pelican-simplify::dashboard.ui.open_name')), { name: s.name }) : Simplify.fill(@js(__('pelican-simplify::dashboard.ui.not_assigned')), { name: s.name })">
                                            <span x-text="playerCount(s)"></span>
                                            <svg aria-hidden="true"><use :href="s.linked ? '#ps-chevron' : '#ps-lock'"/></svg>
                                        </button>
                                    </div>
                                    <div class="ps-overview-power-wrap" x-show="quickActionsMode()!=='off'">
                                        <div class="ps-overview-power" :class="{'is-confirming': powerConfirmation?.uuid===s.uuid}" role="group" :aria-label="Simplify.fill(@js(__('pelican-simplify::dashboard.ui.power_controls_for')), { name: s.alias || s.name })">
                                            <template x-for="action in [{key:'start',label:@js(__('pelican-simplify::dashboard.ui.start')),icon:'play'},{key:'stop',label:@js(__('pelican-simplify::dashboard.ui.stop')),icon:'stop'},{key:'restart',label:@js(__('pelican-simplify::dashboard.ui.restart')),icon:'restart'},{key:'kill',label:@js(__('pelican-simplify::dashboard.ui.force_stop')),icon:'kill'}]" :key="action.key">
                                                <button type="button" class="ps-btn is-icon" :class="{'is-danger': action.key==='kill'}" :data-power="action.key" :data-tip="action.label" :aria-label="action.label+' '+(s.alias || s.name)" :disabled="!!overviewPowerBusy || !canOverviewPower(s, action.key)" x-on:click.stop="overviewPower(s, action.key)">
                                                    <svg aria-hidden="true"><use :href="'#ps-'+action.icon"/></svg>
                                                </button>
                                            </template>
                                            <div class="ps-power-confirm" x-show="powerConfirmation?.uuid===s.uuid" x-cloak>
                                                <button type="button" class="ps-btn is-icon" data-tip="{{ __('pelican-simplify::dashboard.ui.cancel') }}" aria-label="{{ __('pelican-simplify::dashboard.ui.cancel_power_action') }}" x-on:click.stop="powerConfirmation=null"><svg aria-hidden="true"><use href="#ps-close"/></svg></button>
                                                <button type="button" class="ps-btn is-icon is-primary" :data-tip="Simplify.fill(@js(__('pelican-simplify::dashboard.ui.confirm_action')), { action: powerActionLabel(powerConfirmation?.action) })+(powerConfirmation?.action==='kill' ? ' — '+@js(__('pelican-simplify::dashboard.ui.unsaved_progress_lost')) : '')" :aria-label="Simplify.fill(@js(__('pelican-simplify::dashboard.ui.confirm_action')), { action: powerActionLabel(powerConfirmation?.action) })" x-on:click.stop="overviewPower(s, powerConfirmation.action, true)"><svg aria-hidden="true"><use href="#ps-check"/></svg></button>
                                            </div>
                                        </div>
                                    </div>
                                </article>
                                </template>
                            </div>
                        </template>
                    </div>
                    <p x-show="!boot.servers.length" class="ps-empty">{{ __('pelican-simplify::dashboard.ui.no_servers_yet') }}</p>
                </div>
            </div>

            {{-- Host health: a bar at the bottom (as before), a bar above the servers, or details under the top-bar button. --}}
            <section id="ps-health-panel" class="ps-health-panel" :style="accentStyle('overview')" x-on:click.outside="if (!$event.target.closest('.ps-health-pill')) { healthExpanded=false; healthOpenNodes=[]; healthPopover=false }" x-on:keydown.escape="if (healthPopover) { healthPopover=false; $root.querySelector('.ps-health-pill')?.focus() }" x-on:resize.window="healthPopover && placeHealthPopover()" x-on:scroll.window.passive="healthPopover && placeHealthPopover()" x-show="healthPlace() && (healthPlace()!=='top-bar' || healthPopover)" :class="{'expanded': healthExpanded, 'is-popover': healthPlace()==='top-bar', 'is-above': healthPlace()==='above'}">
                <article class="ps-host-health" role="button" tabindex="0" aria-label="{{ __('pelican-simplify::dashboard.ui.view_health_by_node') }}" :aria-expanded="healthExpanded" aria-controls="ps-health-details"
                         x-on:click="openHostHealth()" x-on:keydown.enter.prevent="openHostHealth()" x-on:keydown.space.prevent="openHostHealth()">
                    <div class="ps-host-state">
                        <span class="ps-host-emblem ps-health-heart" :class="boot.host_health?.state || 'unavailable'" role="img" :aria-label="Simplify.fill(@js(__('pelican-simplify::dashboard.ui.host_health_label')), { state: boot.host_health?.label || @js(__('pelican-simplify::dashboard.ui.unavailable')) })">
                            <svg class="ps-host-heart" aria-hidden="true"><use href="#ps-heart"/></svg>
                            <span class="ps-host-server-badge"><svg aria-hidden="true"><use href="#ps-host"/></svg></span>
                        </span>
                        <div>
                            <div class="ps-host-heading"><h3>{{ __('pelican-simplify::dashboard.ui.host_health') }}</h3><button type="button" class="ps-health-refresh" :class="{'is-busy': healthRefreshing}" :disabled="healthRefreshing" x-on:click.stop="refreshHostHealth()" x-on:keydown.stop aria-label="{{ __('pelican-simplify::dashboard.ui.refresh_host_health') }}" data-tip="{{ __('pelican-simplify::dashboard.ui.refresh_now') }}"><svg aria-hidden="true"><use href="#ps-restart"/></svg></button><svg class="ps-health-expand" :class="{'expanded': healthExpanded}" aria-hidden="true"><use href="#ps-chevron"/></svg></div>
                            <span class="ps-health-status" :class="boot.host_health?.state || 'unavailable'"><strong x-text="boot.host_health?.label || @js(__('pelican-simplify::dashboard.ui.unavailable'))"></strong><small x-text="(boot.host_health?.available ?? 0)+' / '+(boot.host_health?.hosts ?? 0)+' hosts reporting'"></small></span>
                            <small class="ps-health-problem" x-show="boot.host_health?.problem" x-text="boot.host_health?.problem"></small>
                            <small class="ps-health-phone-summary" x-text="Simplify.fill(@js(__('pelican-simplify::dashboard.ui.cpu_ram')), { cpu: hostValue('cpu'), ram: hostValue('memory') })"></small>
                        </div>
                    </div>
                    <template x-for="metric in hostMetrics" :key="metric.key">
                        <div class="ps-host-metric">
                            {{-- One line per meter: label, a heart only when it needs attention, and the value; the bar below. Network shows its rate on the second line and its total as a tip. --}}
                            <div class="ps-host-heading">
                                <svg aria-hidden="true"><use :href="'#ps-'+metric.icon"/></svg><span x-text="metric.label" :data-tip="metric.key==='network' ? Simplify.fill(@js(__('pelican-simplify::dashboard.ui.used')), { amount: hostValue('network') }) : (metric.key==='cpu' && boot.host_health?.hosts>1 ? @js(__('pelican-simplify::dashboard.ui.average_across_reporting_hosts')) : null)"></span>
                                <span class="ps-health-heart" :class="hostMetricState(metric.key)" role="img" :aria-label="metric.label+': '+hostMetricLabel(metric.key)" :data-tip="hostMetricLabel(metric.key)"><svg aria-hidden="true"><use href="#ps-heart"/></svg></span>
                                <strong :class="{'ps-network-value': metric.key==='network'}" x-text="metric.key==='network' ? hostRate() : hostValue(metric.key)"></strong>
                            </div>
                            <span class="ps-meter" x-show="metric.key!=='network'"><span :style="{width: hostFill(metric.key)+'%'}"></span></span>
                        </div>
                    </template>
                </article>
                <div id="ps-health-details" class="ps-health-drawer" :inert="!healthExpanded" :aria-hidden="!healthExpanded">
                    <div>
                        <div class="ps-health-detail-toolbar">{{ __('pelican-simplify::dashboard.ui.expand_a_node_to_view_its') }}</div>
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
                                            <small class="ps-health-problem" x-show="node.problem" x-text="node.problem"></small>
                                        </header>
                                        <div class="ps-node-metrics">
                                            <template x-for="metric in hostMetrics" :key="metric.key">
                                                <div class="ps-host-metric">
                                                    <div class="ps-host-heading"><svg aria-hidden="true"><use :href="'#ps-'+metric.icon"/></svg><span x-text="metric.label"></span></div>
                                                    <strong :class="{'ps-network-value': metric.key==='network'}" x-text="metric.key==='network' ? hostRate(node) : hostValue(metric.key, node)"></strong>
                                                    <span class="ps-meter" x-show="metric.key!=='network'"><span :style="{width: hostFill(metric.key, node)+'%'}"></span></span>
                                                    <small x-show="metric.key==='network'" x-text="Simplify.fill(@js(__('pelican-simplify::dashboard.ui.used')), { amount: hostValue('network', node) })"></small>
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
                                                        <span><small>{{ __('pelican-simplify::dashboard.ui.cpu') }}</small><strong x-text="cpuLabel(item)"></strong></span>
                                                        <span><small>{{ __('pelican-simplify::dashboard.ui.ram') }}</small><strong x-text="memoryLabel(item)"></strong></span>
                                                        <span><small>{{ __('pelican-simplify::dashboard.ui.storage') }}</small><strong x-text="item.usage?.disk_bytes==null ? '—' : trafficBytes(item.usage.disk_bytes)"></strong></span>
                                                        <span><small>{{ __('pelican-simplify::dashboard.ui.network') }}</small><strong x-text="serverRate(item)"></strong><small x-text="Simplify.fill(@js(__('pelican-simplify::dashboard.ui.used')), { amount: serverTraffic(item) })"></small></span>
                                                    </div>
                                                </template>
                                                <p x-show="!nodeServers(node).length" class="ps-help">{{ __('pelican-simplify::dashboard.ui.no_visible_servers_on_this_node') }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </article>
                            </template>
                            <p x-show="!boot.host_health?.nodes?.length" class="ps-empty">{{ __('pelican-simplify::dashboard.ui.no_nodes_reporting_yet') }}</p>
                        </div>
                    </div>
                </div>
            </section>
        </section>

        {{-- Server dashboard ----------------------------------------------------------------------- --}}
        <section x-show="server" class="ps-server-dashboard" :class="{'is-installing': installView()}">
            {{-- Status bar: slides in once the server card scrolls out of view, so status and power stay in reach. --}}
            <div class="ps-statusbar" :class="{'is-visible': statusBarVisible && !!server}" :inert="!statusBarVisible" role="region" aria-label="{{ __('pelican-simplify::dashboard.ui.server_status') }}">
                <span class="ps-statusbar-name" x-text="server?.alias || server?.name"></span>
                <span class="ps-badge ps-status" :class="status"><i></i><span x-text="status"></span></span>
                <button type="button" class="ps-statusbar-item ps-statusbar-address" x-show="server?.address" x-on:click="copyText(server.address)" data-tip="{{ __('pelican-simplify::dashboard.ui.copy_server_address') }}"><svg aria-hidden="true"><use href="#ps-link"/></svg><span x-text="server?.address"></span></button>
                <span class="ps-statusbar-item" data-tip="{{ __('pelican-simplify::dashboard.ui.players_online') }}"><svg aria-hidden="true"><use href="#ps-players"/></svg><span x-text="playerCount({ ...server, status })"></span></span>
                <div class="ps-statusbar-power" :inert="['installing','restoring_backup'].includes(serverSetup())">
                    <button type="button" class="ps-btn is-sm is-primary" x-on:click="power('start')" :disabled="busy || !server?.permissions['control.start'] || status==='running'" aria-label="{{ __('pelican-simplify::dashboard.ui.start_server') }}" data-tip="{{ __('pelican-simplify::dashboard.ui.start') }}"><svg aria-hidden="true"><use href="#ps-play"/></svg></button>
                    <button type="button" class="ps-btn is-sm" x-on:click="power('restart')" :disabled="busy || !server?.permissions['control.restart']" aria-label="{{ __('pelican-simplify::dashboard.ui.restart_server') }}" data-tip="{{ __('pelican-simplify::dashboard.ui.restart') }}"><svg aria-hidden="true"><use href="#ps-restart"/></svg></button>
                    <button type="button" class="ps-btn is-sm" x-on:click="power('stop')" :disabled="busy || !server?.permissions['control.stop'] || status==='offline'" aria-label="{{ __('pelican-simplify::dashboard.ui.stop_server') }}" data-tip="{{ __('pelican-simplify::dashboard.ui.stop') }}"><svg aria-hidden="true"><use href="#ps-stop"/></svg></button>
                    <button type="button" class="ps-btn is-sm is-danger" x-on:click="power('kill')" :disabled="busy || !server?.permissions['control.stop'] || status==='offline'" aria-label="{{ __('pelican-simplify::dashboard.ui.force_stop_server') }}" data-tip="{{ __('pelican-simplify::dashboard.ui.kill') }}"><svg aria-hidden="true"><use href="#ps-kill"/></svg></button>
                </div>
            </div>
            @include('pelican-simplify::announcement-bars')
            <div class="ps-alert is-warn ps-pending" x-show="server?.pending" x-cloak>
                <svg aria-hidden="true"><use href="#ps-alert"/></svg>
                <div>
                    <strong x-text="server?.pending?.error ? @js(__('pelican-simplify::dashboard.ui.changes_need_attention')) : server?.pending?.file_only ? @js(__('pelican-simplify::dashboard.ui.file_changes_saved_restart_to_load')) : @js(__('pelican-simplify::dashboard.ui.stop_or_restart_to_apply_changes'))"></strong>
                    <small x-text="server?.pending?.error || (server?.pending?.restart_at ? @js(__('pelican-simplify::dashboard.ui.applying_changes_when_the_server_stops')) : (server?.pending?.file_only ? @js(__('pelican-simplify::dashboard.ui.files_are_saved_restart_now_or')) : @js(__('pelican-simplify::dashboard.ui.your_saved_changes_are_staged_until'))))"></small>
                </div>
                <div class="ps-pending-actions">
                    <button type="button" x-on:click="restartChanges(0)" :disabled="busy" class="ps-btn is-sm" aria-label="{{ __('pelican-simplify::dashboard.ui.apply_changes_and_restart') }}"><svg aria-hidden="true"><use href="#ps-restart"/></svg><span>{{ __('pelican-simplify::dashboard.ui.restart_now') }}</span></button>
                    <button type="button" x-on:click="open('restart')" :disabled="busy || !showWidget('restart')" class="ps-btn is-sm is-icon" data-tip="{{ __('pelican-simplify::dashboard.ui.schedule_restart') }}" aria-label="{{ __('pelican-simplify::dashboard.ui.schedule_restart') }}"><svg aria-hidden="true"><use href="#ps-clock"/></svg></button>
                    <button type="button" x-on:click="discard()" :disabled="busy" class="ps-btn is-sm is-icon is-subtle" :aria-label="server?.pending?.file_only ? @js(__('pelican-simplify::dashboard.ui.dismiss_restart_reminder')) : @js(__('pelican-simplify::dashboard.ui.discard_changes'))" :data-tip="server?.pending?.file_only ? @js(__('pelican-simplify::dashboard.ui.dismiss_reminder_saved_files_stay_changed')) : @js(__('pelican-simplify::dashboard.ui.discard_changes'))"><svg aria-hidden="true"><use href="#ps-undo"/></svg></button>
                </div>
            </div>

            {{-- Shown once after an installation finished on this page: one clear next step. --}}
            {{-- The same banner asks for the Minecraft EULA when it is still needed, so Pelican's own popup never shows. --}}
            <div class="ps-ready" :class="{'is-eula': eulaNeeded && !installReady}" x-show="(installReady || eulaNeeded) && !installView() && !['running','starting'].includes(status)" x-cloak x-transition.opacity role="status">
                <span class="ps-ready-mark" aria-hidden="true"><svg><use :href="installReady ? '#ps-check' : '#ps-file'"/></svg></span>
                <div>
                    <strong x-text="installReady ? @js(__('pelican-simplify::dashboard.ui.your_server_is_ready')) : @js(__('pelican-simplify::dashboard.ui.accept_the_minecraft_eula'))"></strong>
                    <small x-show="!eulaNeeded" x-text="server?.minecraft ? @js(__('pelican-simplify::dashboard.ui.you_can_now_start_your_server')) : @js(__('pelican-simplify::dashboard.ui.you_can_now_start_your_server_2'))"></small>
                    <small x-show="eulaNeeded">{{ __('pelican-simplify::dashboard.ui.minecraft_needs_this_once_before_the') }} <a href="https://www.minecraft.net/eula" target="_blank" rel="noopener noreferrer">{{ __('pelican-simplify::dashboard.ui.read_the_minecraft_eula') }}</a></small>
                </div>
                <button type="button" class="ps-btn ps-ready-resources" x-show="installReady && server?.can_resources && showWidget('performance')" x-on:click="open('performance')" :disabled="busy"><svg aria-hidden="true"><use href="#ps-cpu"/></svg><span>{{ __('pelican-simplify::dashboard.ui.change_resources') }}</span></button>
                <button type="button" class="ps-btn is-primary ps-ready-start" x-show="server?.permissions['control.start']" x-on:click="readyStart()" :disabled="busy"><svg aria-hidden="true"><use href="#ps-play"/></svg><span x-text="eulaNeeded ? @js(__('pelican-simplify::dashboard.ui.accept_and_start')) : @js(__('pelican-simplify::dashboard.ui.start_server'))"></span></button>
                <button type="button" class="ps-btn is-icon is-subtle" x-on:click="installReady=false; eulaNeeded=false" aria-label="{{ __('pelican-simplify::dashboard.ui.dismiss') }}" data-tip="{{ __('pelican-simplify::dashboard.ui.dismiss') }}"><svg aria-hidden="true"><use href="#ps-close"/></svg></button>
            </div>

            <article class="ps-server-info" aria-label="{{ __('pelican-simplify::dashboard.ui.server_information') }}" x-init="watchServerInfo($el)">
                {{-- Artwork has its own clipping layer, so it stays inside the card while the ⋯ menu lets the card overflow. --}}
                <span class="ps-info-decor">
                <span class="ps-offline-artwork" x-show="status==='offline'" aria-label="{{ __('pelican-simplify::dashboard.ui.server_offline') }}"><svg aria-hidden="true"><use href="#ps-offline"/></svg></span>
                <span class="ps-server-banner ps-banner-color" :class="{'is-auto': server?.background_id==='color'}" x-show="bannerColor(server) && status!=='offline'" :style="bannerColor(server)" aria-hidden="true"></span>
                <img class="ps-server-banner" x-show="server?.background && status!=='offline'" :src="safeImage(server?.background)" alt="" x-on:error="$el.style.visibility='hidden'">
                </span>
                <span class="ps-info-icons">
                    <span class="ps-info-icon ps-custom-server-icon" x-show="server?.server_icon" data-tip="server-icon.png"><img :src="safeImage(server?.server_icon)" alt="{{ __('pelican-simplify::dashboard.ui.custom_server_icon') }}" x-on:load="$el.style.visibility=''" x-on:error="$el.style.visibility='hidden'"></span>
                    <span class="ps-info-icon ps-software-icon" :class="{'is-badge': server?.server_icon}" data-tip-label="Server software" :data-tip="server?.pack?.modpack_name || server?.software || @js(__('pelican-simplify::dashboard.ui.server_software'))">
                        <img x-show="server?.software_icon" :src="safeImage(server?.software_icon)" alt="{{ __('pelican-simplify::dashboard.ui.server_software_icon') }}" x-on:load="$el.style.visibility=''" x-on:error="$el.style.visibility='hidden'">
                        <svg x-show="!server?.software_icon" aria-hidden="true"><use :href="server?.minecraft ? '#ps-cube' : '#ps-gamepad'"/></svg>
                    </span>
                </span>
                <div class="ps-info-content">
                    <div class="ps-game-title">
                        <h2 x-text="server?.alias || server?.name"></h2>
                        <small class="ps-real-server-name" x-show="server?.alias" x-text="'('+server?.name+')'"></small>
                        <button type="button" class="ps-btn is-icon is-sm is-subtle ps-alias-button" x-show="server?.permissions['settings.rename']" x-on:click="openAlias()" data-tip="{{ __('pelican-simplify::dashboard.ui.change_alias') }}" aria-label="{{ __('pelican-simplify::dashboard.ui.change_alias') }}" aria-haspopup="dialog"><svg aria-hidden="true"><use href="#ps-edit"/></svg></button>
                    </div>
                    {{-- One line: status, software and version, modpack, address. --}}
                    <div class="ps-info-meta">
                        <span class="ps-badge ps-status" :class="status"><i></i><span x-text="status"></span></span>
                        <span class="ps-info-meta-item" data-tip-label="Server software" :data-tip="server?.software || @js(__('pelican-simplify::dashboard.ui.not_reported'))"><svg aria-hidden="true"><use :href="server?.minecraft ? '#ps-cube' : '#ps-gamepad'"/></svg><span x-text="softwareLabel()"></span></span>
                        {{-- The modpack links to its page; this replaced the separate modpack card under the console. --}}
                        <a class="ps-info-meta-item ps-info-modpack" x-show="server?.pack?.modpack_name && safeLink(server?.pack?.modpack_url) !== '#'" :href="safeLink(server?.pack?.modpack_url)" target="_blank" rel="noopener noreferrer" data-tip="{{ __('pelican-simplify::dashboard.ui.modpack_info') }}"><svg aria-hidden="true"><use href="#ps-packages"/></svg><span x-text="modpackLabel()"></span><svg class="ps-info-modpack-out" aria-hidden="true"><use href="#ps-external"/></svg></a>
                        <span class="ps-info-meta-item ps-info-modpack" x-show="server?.pack?.modpack_name && safeLink(server?.pack?.modpack_url) === '#'" data-tip="{{ __('pelican-simplify::dashboard.ui.modpack') }}"><svg aria-hidden="true"><use href="#ps-packages"/></svg><span x-text="modpackLabel()"></span></span>
                        <button type="button" class="ps-address ps-info-address" x-on:click="copyText(server.address)" :disabled="!server?.address" data-tip="{{ __('pelican-simplify::dashboard.ui.copy_server_address') }}">
                            <svg aria-hidden="true"><use href="#ps-link"/></svg><span x-text="server?.address || @js(__('pelican-simplify::dashboard.ui.no_address_assigned'))"></span><svg aria-hidden="true"><use href="#ps-copy"/></svg>
                        </button>
                    </div>
                </div>
                <div class="ps-motd-preview ps-motd-display ps-info-motd" x-show="server?.properties?.motd || server?.pack?.motd" x-data="{observer:null,init(){this.observer=new ResizeObserver(()=>this.fitMotd(this.$el));this.observer.observe(this.$el)},destroy(){this.observer?.disconnect()}}" x-effect="server?.properties?.motd; server?.pack?.motd; $nextTick(()=>fitMotd($el))" aria-label="{{ __('pelican-simplify::dashboard.ui.message_of_the_day') }}"><template x-for="(part,index) in motdPreview(server?.properties?.motd || server?.pack?.motd || '')" :key="index"><span :style="part.style" x-text="part.text"></span></template></div>
                {{-- Low screens (a phone on its side) fold the pinned bars into chips here, so the console keeps the screen. --}}
                <div class="ps-pins-low">@include('pelican-simplify::announcement-chips')</div>
                {{-- Power: one button that follows the state (Start or Stop), Restart, and the rest under ⋯. --}}
                <div class="ps-info-power" :inert="['installing','restoring_backup'].includes(serverSetup())" :class="{'is-locked': ['installing','restoring_backup'].includes(serverSetup())}" x-on:click.outside="colorMenu=false; infoMenu=false" x-on:keydown.escape.stop="colorMenu=false; infoMenu=false">
                    <button type="button" class="ps-btn ps-power-main" :class="{'is-primary': mainPower().action==='start'}" x-on:click="power(mainPower().action)" :disabled="busy || !server?.permissions['control.'+mainPower().action] || ['starting','stopping'].includes(status)" :aria-label="Simplify.fill(@js(__('pelican-simplify::dashboard.ui.action_server')), { action: mainPower().label })"><svg aria-hidden="true"><use :href="'#ps-'+mainPower().icon"/></svg><span x-text="mainPower().label"></span></button>
                    <button type="button" class="ps-btn ps-power-restart" x-on:click="power('restart')" :disabled="busy || !server?.permissions['control.restart'] || status!=='running'" aria-label="{{ __('pelican-simplify::dashboard.ui.restart_server') }}"><svg aria-hidden="true"><use href="#ps-restart"/></svg><span>{{ __('pelican-simplify::dashboard.ui.restart') }}</span></button>
                    <button type="button" class="ps-btn is-icon ps-info-more-button" x-ref="infoMore" aria-label="{{ __('pelican-simplify::dashboard.ui.more_server_options') }}" aria-controls="ps-info-menu" :aria-expanded="infoMenu" x-on:click="infoMenu=!infoMenu; colorMenu=false"><svg aria-hidden="true"><use href="#ps-dots"/></svg></button>
                    <div id="ps-info-menu" class="ps-dropdown is-end ps-info-menu" x-show="infoMenu" x-cloak x-transition.opacity role="menu">
                        <span class="ps-label ps-menu-heading" x-show="server?.permissions['settings.rename']">{{ __('pelican-simplify::dashboard.ui.look') }}</span>
                        <button type="button" class="ps-menu-item" role="menuitem" :disabled="busy || !server?.permissions['settings.rename']" x-on:click="infoMenu=false; toggleColorMenu($refs.infoMore)"><span class="ps-color-dot" aria-hidden="true"></span><span>{{ __('pelican-simplify::dashboard.ui.server_color') }}</span></button>
                        <button type="button" class="ps-menu-item" role="menuitem" :disabled="busy || !server?.permissions['settings.rename']" x-on:click="infoMenu=false; openBackground()"><svg aria-hidden="true"><use href="#ps-image"/></svg><span>{{ __('pelican-simplify::dashboard.ui.server_background') }}</span></button>
                        <button type="button" class="ps-menu-item" role="menuitem" x-show="server?.permissions['settings.rename']" x-on:click="infoMenu=false; openAlias()"><svg aria-hidden="true"><use href="#ps-edit"/></svg><span>{{ __('pelican-simplify::dashboard.ui.change_alias') }}</span></button>
                        <span class="ps-label ps-menu-heading" x-show="server?.website_listing" x-cloak>{{ __('pelican-simplify::dashboard.ui.website') }}</span>
                        <button type="button" class="ps-menu-item ps-web-listing" role="menuitemcheckbox" x-show="server?.website_listing" x-cloak :aria-checked="!!server?.website_listing?.visible" :disabled="busy" x-on:click="saveListing(!server.website_listing.visible)"><svg aria-hidden="true"><use href="#ps-web"/></svg><span>{{ __('pelican-simplify::dashboard.ui.show_on_the_website') }}</span><span class="ps-switch" :class="{'is-on': server?.website_listing?.visible}" aria-hidden="true"></span></button>
                        <hr>
                        <button type="button" class="ps-menu-item is-danger" role="menuitem" :disabled="busy || !server?.permissions['control.stop'] || status==='offline'" x-on:click="infoMenu=false; power('kill')"><svg aria-hidden="true"><use href="#ps-kill"/></svg><span>{{ __('pelican-simplify::dashboard.ui.force_stop') }}</span></button>
                    </div>
                    <div class="ps-dropdown is-fixed ps-color-dropdown" x-show="colorMenu" x-cloak :style="colorMenuStyle" x-on:click.stop>
                        <span class="ps-label">{{ __('pelican-simplify::dashboard.ui.server_color') }}</span>
                        <small class="ps-color-group-note" x-show="serverGroup(server?.uuid)?.color" x-text="Simplify.fill(@js(__('pelican-simplify::dashboard.ui.group_color_note')), { group: serverGroup(server?.uuid)?.name })"></small>
                        <div class="ps-swatches ps-color-presets">
                            <template x-for="(name,color) in (boot.available_colors || {})" :key="color">
                                <button type="button" class="ps-swatch" :style="{'background-color': color}" :data-tip="name" :aria-label="Simplify.fill(@js(__('pelican-simplify::dashboard.ui.select_color')), { color: name })" :aria-pressed="colorDraft===color" :disabled="busy" x-on:click="colorDraft=color"></button>
                            </template>
                        </div>
                        <label class="ps-check ps-custom-color"><span>{{ __('pelican-simplify::dashboard.ui.custom_color') }}</span><input type="color" class="ps-color-input" x-model="colorDraft" :disabled="busy" aria-label="{{ __('pelican-simplify::dashboard.ui.choose_custom_server_color') }}"></label>
                        <div class="ps-dropdown-actions">
                            <button type="button" class="ps-btn is-sm" x-on:click="colorMenu=false" :disabled="busy">{{ __('pelican-simplify::dashboard.ui.cancel') }}</button>
                            <button type="button" class="ps-btn is-sm is-primary" x-on:click="commitServerColor()" :disabled="busy">{{ __('pelican-simplify::dashboard.ui.save') }}</button>
                        </div>
                    </div>
                </div>
            </article>

            {{-- Live numbers in one row: players and the resource metrics. --}}
            <section class="ps-server-stats" aria-label="{{ __('pelican-simplify::dashboard.ui.server_status') }}">
                <article class="ps-card ps-stat ps-players-stat" x-show="server?.minecraft">
                    <div class="ps-stat-top"><span>{{ __('pelican-simplify::dashboard.ui.players') }}</span><svg aria-hidden="true"><use href="#ps-players"/></svg></div>
                    <strong><span x-text="server?.players ?? '—'"></span><span class="ps-stat-total" x-text="server?.max_players ? ' / '+server.max_players : ''"></span></strong>
                    <ul class="ps-online-players" aria-label="{{ __('pelican-simplify::dashboard.ui.online_players') }}" x-show="server?.player_names?.length">
                        <template x-for="p in (server?.player_names || []).slice(0, 6)" :key="p.name"><li :data-tip="p.name"><img :src="head(p)" :alt="p.name" loading="lazy" x-on:error="$el.style.visibility='hidden'"></li></template>
                        <li class="ps-online-more" x-show="(server?.players || 0) > Math.min(6, server?.player_names?.length || 0)" x-text="'+'+((server?.players || 0) - Math.min(6, server?.player_names?.length || 0))"></li>
                    </ul>
                    <small class="ps-player-empty" x-show="!server?.player_names?.length" x-text="server?.players>0 ? @js(__('pelican-simplify::dashboard.ui.names_not_available')) : @js(__('pelican-simplify::dashboard.ui.no_players_online'))"></small>
                </article>
                <template x-for="metric in metrics" :key="metric.key">
                    <article class="ps-card ps-stat">
                        <div class="ps-stat-top"><span x-text="metric.label"></span><svg aria-hidden="true"><use :href="'#ps-'+metric.icon"/></svg></div>
                        <strong x-text="metricValue(metric.key)"></strong>
                        <small x-text="metricLimit(metric.key)"></small>
                        <svg class="ps-spark" viewBox="0 0 200 36" preserveAspectRatio="none" aria-hidden="true"><path :d="spark(metric.key)"/></svg>
                    </article>
                </template>
            </section>

            {{-- Widgets other plugins add above Pelican's console (such as Player Counter), sized to their content. --}}
            <template x-if="server?.plugin_widgets_url">
                <div class="ps-plugin-widgets" :class="{'is-empty': !pluginWidgetHeights[server.uuid]}" :style="{'--ps-widgets-height': (pluginWidgetHeights[server.uuid] || 0)+'px'}">
                    <iframe data-ps-frame="native" :data-ps-accent="currentAccentHex()" :src="frameUrl(server.plugin_widgets_url)" title="{{ __('pelican-simplify::dashboard.ui.plugin_widgets') }}" x-on:load="pluginWidgetsLoaded($el, server.uuid)"></iframe>
                </div>
            </template>

            {{-- A new or reinstalled server: only the steps and the installation log until Pelican has finished. --}}
            <section class="ps-install" x-show="installView()" x-cloak role="status" aria-live="polite" :class="'is-'+serverSetup()" aria-label="{{ __('pelican-simplify::dashboard.ui.installation_progress') }}">
                <div class="ps-install-head">
                    <span class="ps-install-icon" aria-hidden="true">
                        <img x-show="server?.software_icon" :src="safeImage(server?.software_icon)" alt="" x-on:error="$el.style.visibility='hidden'">
                        <svg x-show="!server?.software_icon" aria-hidden="true"><use :href="server?.minecraft ? '#ps-cube' : '#ps-gamepad'"/></svg>
                    </span>
                    <div class="ps-install-title">
                        <h2 x-text="installTitle()"></h2>
                        <p x-text="installText()"></p>
                    </div>
                    <span class="ps-install-time" x-show="!installFailed() && installElapsed()"><strong x-text="installElapsed()"></strong><small x-show="installTypical()" x-text="installTypical()"></small></span>
                    <div class="ps-install-actions" x-on:click.outside="installMenu=false" x-on:keydown.escape.stop="installMenu=false">
                        <button type="button" class="ps-btn" x-show="serverSetup()==='installing'" :class="{'is-on': installNotify}" :aria-pressed="installNotify" x-on:click="toggleInstallNotify()"><svg aria-hidden="true"><use :href="installNotify ? '#ps-check' : '#ps-bell'"/></svg><span x-show="!installNotify">{{ __('pelican-simplify::dashboard.ui.notify_me') }}<span class="ps-install-long"> {{ __('pelican-simplify::dashboard.ui.when_ready') }}</span></span><span x-show="installNotify">{{ __('pelican-simplify::dashboard.ui.you_get_a_notification') }}</span></button>
                        <button type="button" class="ps-btn is-danger ps-install-kill" x-show="canKillInstall()" x-on:click="killInstall()" :disabled="busy"><svg aria-hidden="true"><use href="#ps-kill"/></svg><span>{{ __('pelican-simplify::dashboard.ui.kill') }}</span></button>
                        <button type="button" class="ps-btn" x-show="installFailed() && !installKilled() && canExplain()" x-on:click="explainError('console')" :disabled="!!explain?.loading"><svg aria-hidden="true"><use href="#ps-question"/></svg><span>{{ __('pelican-simplify::dashboard.ui.explain') }}<span class="ps-install-long"> {{ __('pelican-simplify::dashboard.ui.this_error') }}</span></span></button>
                        <button type="button" class="ps-btn ps-install-resources" x-show="installFailed() && server?.can_resources && showWidget('performance')" x-on:click="open('performance')" :disabled="busy"><svg aria-hidden="true"><use href="#ps-cpu"/></svg><span>{{ __('pelican-simplify::dashboard.ui.change_resources') }}</span></button>
                        <button type="button" class="ps-btn is-primary" x-show="installFailed() && server?.permissions['settings.reinstall']" x-on:click="reinstall()" :disabled="busy"><svg aria-hidden="true"><use href="#ps-restart"/></svg><span>{{ __('pelican-simplify::dashboard.ui.try_again') }}</span></button>
                        <button type="button" class="ps-btn is-icon" x-show="serverSetup()!=='restoring_backup'" aria-label="{{ __('pelican-simplify::dashboard.ui.more_options') }}" aria-controls="ps-install-menu" :aria-expanded="installMenu" x-on:click="installMenu=!installMenu"><svg aria-hidden="true"><use href="#ps-dots"/></svg></button>
                        <div id="ps-install-menu" class="ps-dropdown is-end ps-install-menu" x-show="installMenu" x-cloak x-transition.opacity role="menu">
                            <button type="button" class="ps-menu-item" role="menuitem" x-show="server?.files_url" x-on:click="installMenu=false; installFiles()"><svg aria-hidden="true"><use href="#ps-files"/></svg><span>{{ __('pelican-simplify::dashboard.ui.browse_files') }}</span></button>
                            <button type="button" class="ps-menu-item" role="menuitem" x-show="installFailed() && server?.permissions['startup.read']" x-on:click="installMenu=false; installStartup()"><svg aria-hidden="true"><use href="#ps-settings"/></svg><span>{{ __('pelican-simplify::dashboard.ui.startup_settings') }}</span></button>
                            <button type="button" class="ps-menu-item" role="menuitem" x-on:click="installMenu=false; copyText(installLogText(), @js(__('pelican-simplify::dashboard.ui.log_copied')))"><svg aria-hidden="true"><use href="#ps-copy"/></svg><span>{{ __('pelican-simplify::dashboard.ui.copy_the_log') }}</span></button>
                            <hr x-show="server?.owner">
                            <button type="button" class="ps-menu-item is-danger" role="menuitem" x-show="server?.owner" x-on:click="installMenu=false; cancelInstall()"><svg aria-hidden="true"><use href="#ps-kill"/></svg><span x-text="installFailed() ? @js(__('pelican-simplify::dashboard.ui.delete_server')) : @js(__('pelican-simplify::dashboard.ui.cancel_and_delete_server'))"></span></button>
                        </div>
                    </div>
                </div>
                <ol class="ps-install-steps" x-show="serverSetup()!=='restoring_backup'">
                    <template x-for="(step, index) in installSteps()" :key="step.key">
                        <li class="ps-install-step" :class="'is-'+step.state" :aria-current="step.state==='active' ? 'step' : null">
                            <span class="ps-install-mark" aria-hidden="true">
                                <svg x-show="step.state==='done'"><use href="#ps-check"/></svg>
                                <svg x-show="step.state==='failed'"><use href="#ps-close"/></svg>
                                <i x-show="step.state==='active'"></i>
                                <b x-show="step.state==='todo'" x-text="index+1"></b>
                            </span>
                            <span class="ps-install-step-text"><strong x-text="step.label"></strong><small x-text="step.detail"></small></span>
                        </li>
                    </template>
                </ol>
            </section>

            <div class="ps-server-workspace" :class="{'has-dock': docked(), 'is-peek': consolePeek, 'is-focus': consoleFocus && !docked()}" x-init="fitWorkspace($el)" x-effect="docked(); consolePeek; consoleFocus; refitWorkspace()">
                {{-- A workspace opens in this block; the console then shrinks to a read-only view at the bottom right. --}}
                <div class="ps-dock" x-effect="syncDock($el, docked())"></div>
                <div class="ps-settings-column" :inert="['installing','restoring_backup'].includes(serverSetup())" :class="{'is-locked': ['installing','restoring_backup'].includes(serverSetup())}">
                    <nav class="ps-widget-grid" aria-label="{{ __('pelican-simplify::dashboard.ui.server_sections') }}" x-init="scrollHint($el)">
                        <button type="button" class="ps-widget is-console" style="--ps-tone: #9a9aa6" :aria-current="docked() ? null : 'page'" x-on:click="docked() && close()" aria-label="{{ __('pelican-simplify::dashboard.ui.live_console') }}">
                            <span class="ps-widget-icon" aria-hidden="true">›_</span>
                            <span><strong>{{ __('pelican-simplify::dashboard.ui.console') }}</strong></span>
                        </button>
                        <template x-for="widget in widgets" :key="widget.key">
                            <button type="button" class="ps-widget" :style="{'--ps-tone': widgetTone(widget.key)}" :aria-current="modal===widget.key ? 'page' : null" :aria-label="widgetTitle(widget)+' — '+widgetDescription(widget)" x-show="showWidget(widget.key)" x-on:click="modal===widget.key ? null : open(widget.key)">
                                <span class="ps-widget-icon"><svg aria-hidden="true"><use :href="'#ps-'+widget.icon"/></svg></span>
                                <span><strong x-text="widgetTitle(widget)"></strong><small x-text="widgetDescription(widget)"></small></span>
                            </button>
                        </template>
                        {{-- Pages other plugins add to the server panel. --}}
                        <template x-if="server?.external_pages?.length">
                            <span class="ps-widget-group">{{ __('pelican-simplify::dashboard.ui.other_plugins') }}</span>
                        </template>
                        <template x-for="page in server?.external_pages || []" :key="page.key">
                            <button type="button" class="ps-widget" style="--ps-tone: #2dd4bf" :aria-current="modal==='external' && externalPage?.key===page.key ? 'page' : null" x-on:click="openExternal(page)" :aria-label="Simplify.fill(@js(__('pelican-simplify::dashboard.ui.page_from_plugin')), { page: page.label, plugin: page.plugin })" :data-tip="Simplify.fill(@js(__('pelican-simplify::dashboard.ui.from_the_plugin')), { plugin: page.plugin })">
                                <span class="ps-widget-icon"><svg aria-hidden="true"><use href="#ps-plugins"/></svg></span>
                                <span><strong x-text="page.label"></strong></span>
                            </button>
                        </template>
                    </nav>
                    {{-- Phones: four tabs that always fit. Console and the two most used sections, the rest under More. --}}
                    <nav class="ps-phone-sections" aria-label="{{ __('pelican-simplify::dashboard.ui.server_sections') }}" x-on:click.outside="phoneMore=false" x-on:keydown.escape.stop="phoneMore=false">
                        <button type="button" class="ps-phone-section" :aria-current="modal ? null : 'page'" x-on:click="phoneMore=false; modal ? close() : $refs.output.closest('.ps-console').scrollIntoView({behavior: 'smooth', block: 'start'})"><svg class="ps-phone-section-icon" aria-hidden="true"><use href="#ps-terminal"/></svg><span>{{ __('pelican-simplify::dashboard.ui.console') }}</span></button>
                        <template x-for="widget in widgets.filter((w) => PHONE_SECTIONS.includes(w.key))" :key="widget.key">
                            <button type="button" class="ps-phone-section" x-show="showWidget(widget.key)" :aria-current="modal===widget.key ? 'page' : null" x-on:click="phoneMore=false; modal===widget.key || open(widget.key)"><svg class="ps-phone-section-icon" aria-hidden="true"><use :href="'#ps-'+widget.icon"/></svg><span x-text="widget.key==='players' ? @js(__('pelican-simplify::dashboard.ui.players')) : widgetTitle(widget)"></span></button>
                        </template>
                        <div class="ps-phone-more">
                            <button type="button" class="ps-phone-section" aria-controls="ps-phone-more-menu" :aria-expanded="phoneMore" :aria-current="phoneMoreActive() ? 'page' : null" x-on:click="phoneMore=!phoneMore"><svg class="ps-phone-section-icon" aria-hidden="true"><use href="#ps-dots"/></svg><span>{{ __('pelican-simplify::dashboard.ui.more') }}</span></button>
                            <div id="ps-phone-more-menu" class="ps-dropdown is-end ps-phone-more-menu" x-show="phoneMore" x-cloak x-transition.opacity>
                                <template x-for="widget in widgets.filter((w) => !PHONE_SECTIONS.includes(w.key))" :key="widget.key">
                                    <button type="button" class="ps-menu-item" x-show="showWidget(widget.key)" :aria-current="modal===widget.key ? 'page' : null" x-on:click="phoneMore=false; modal===widget.key || open(widget.key)">
                                        <svg aria-hidden="true"><use :href="'#ps-'+widget.icon"/></svg><span x-text="widgetTitle(widget)"></span>
                                    </button>
                                </template>
                                <template x-for="page in server?.external_pages || []" :key="page.key">
                                    <button type="button" class="ps-menu-item" x-on:click="phoneMore=false; openExternal(page)">
                                        <svg aria-hidden="true"><use href="#ps-plugins"/></svg><span x-text="page.label"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </nav>
                </div>
                <div class="ps-live-column">
                    <aside class="ps-console" x-show="server" :class="{'can-explain': canExplain()}">
                        <div class="ps-console-title">
                            <span class="ps-terminal-icon" aria-hidden="true">›_</span>
                            <strong x-text="installView() ? @js(__('pelican-simplify::dashboard.ui.installation_log')) : @js(__('pelican-simplify::dashboard.ui.live_console'))">{{ __('pelican-simplify::dashboard.ui.live_console') }}</strong>
                            <span class="ps-console-dot" :class="{'connected': connected}"></span><span class="ps-live-bars" x-show="connected" aria-hidden="true"><i></i><i></i><i></i><i></i></span>
                            <span class="ps-console-state" x-text="connected ? @js(__('pelican-simplify::dashboard.ui.connected_to_wings')) : @js(__('pelican-simplify::dashboard.ui.connecting_to_wings'))"></span>
                            {{-- Buttons other plugins add to Pelican's console (such as Upload logs from Mclogs Uploader). --}}
                            <template x-for="action in pluginActions('server')" :key="action.key">
                                <button type="button" class="ps-console-clear ps-console-plugin-action" :disabled="action.disabled || pluginActionBusy===action.key" :aria-busy="pluginActionBusy===action.key" x-on:click="runPluginAction(action)" :data-tip="action.plugin ? Simplify.fill(@js(__('pelican-simplify::dashboard.ui.action_from_plugin')), { action: action.label, plugin: action.plugin }) : Simplify.fill(@js(__('pelican-simplify::dashboard.ui.action_from_another_plugin')), { action: action.label })" :aria-label="action.label">
                                    <span class="ps-plugin-action-icon" x-show="action.icon" x-html="action.icon" aria-hidden="true"></span><span x-text="action.label"></span>
                                </button>
                            </template>
                            <button type="button" class="ps-console-clear" x-on:click="logs=[]; renderLogs()">{{ __('pelican-simplify::dashboard.ui.clear') }}</button>
                            {{-- Explain this error: only once an admin saved an AI API key (Claude, GPT or Gemini) in Simplify settings. --}}
                            <button type="button" class="ps-console-clear ps-console-tool ps-console-explain" x-show="canExplain()" x-on:click="explainError('console')" :disabled="!!explain?.loading" data-tip="{{ __('pelican-simplify::dashboard.ui.explain_what_went_wrong_from_the') }}" aria-label="{{ __('pelican-simplify::dashboard.ui.explain_this_error') }}"><svg aria-hidden="true"><use href="#ps-question"/></svg><span>{{ __('pelican-simplify::dashboard.ui.explain') }}</span></button>
                            <button type="button" class="ps-console-clear ps-console-tool ps-console-grow" x-show="!docked() && !consoleWindow" x-on:click="setConsoleFocus(!consoleFocus)" :aria-pressed="consoleFocus" :data-tip="consoleFocus ? @js(__('pelican-simplify::dashboard.ui.normal_size')) : @js(__('pelican-simplify::dashboard.ui.bigger_console'))" :aria-label="consoleFocus ? @js(__('pelican-simplify::dashboard.ui.normal_size')) : @js(__('pelican-simplify::dashboard.ui.bigger_console'))"><svg aria-hidden="true"><use href="#ps-expand"/></svg></button>
                            <button type="button" class="ps-console-clear ps-console-tool ps-console-window" x-show="!docked() && !consoleWindow && !embedded" x-on:click="openConsoleWindow()" data-tip="{{ __('pelican-simplify::dashboard.ui.open_in_a_new_window') }}" aria-label="{{ __('pelican-simplify::dashboard.ui.open_the_console_in_a_new') }}"><svg aria-hidden="true"><use href="#ps-external"/></svg></button>
                            <button type="button" class="ps-console-clear ps-console-tool ps-console-peek" x-show="docked()" x-on:click="setConsolePeek(!consolePeek)" :aria-pressed="consolePeek" :data-tip="consolePeek ? @js(__('pelican-simplify::dashboard.ui.hide_the_log')) : @js(__('pelican-simplify::dashboard.ui.show_the_last_log_lines'))" :aria-label="consolePeek ? @js(__('pelican-simplify::dashboard.ui.hide_the_log')) : @js(__('pelican-simplify::dashboard.ui.show_the_last_log_lines'))"><svg aria-hidden="true"><use href="#ps-chevron"/></svg></button>
                            <button type="button" class="ps-console-clear ps-console-tool ps-console-full" x-show="docked()" x-on:click="close()"><svg aria-hidden="true"><use href="#ps-expand"/></svg><span>{{ __('pelican-simplify::dashboard.ui.full_console') }}</span></button>
                        </div>
                        <pre wire:ignore class="ps-console-output" x-ref="output" tabindex="0" aria-label="{{ __('pelican-simplify::dashboard.ui.server_console_output') }}"></pre>
                        <div class="ps-quick-commands" x-show="server?.minecraft" role="group" aria-label="{{ __('pelican-simplify::dashboard.ui.quick_commands') }}" x-init="scrollHint($el)">
                            <template x-for="quick in QUICK_COMMANDS" :key="quick">
                                <button type="button" class="ps-quick-command" :disabled="!connected || !server?.permissions['control.console']" x-on:click="useQuickCommand(quick)" x-text="quick.endsWith(' ') ? quick+'…' : quick"></button>
                            </template>
                        </div>
                        <form class="ps-command" x-on:submit.prevent="sendCommand()">
                            <span aria-hidden="true">›</span>
                            <input x-model="command" placeholder="{{ __('pelican-simplify::dashboard.ui.type_a_command') }}" :disabled="!connected || !server?.permissions['control.console']" x-on:keydown.up.prevent="historyBack()" x-on:keydown.down.prevent="historyForward()" aria-label="{{ __('pelican-simplify::dashboard.ui.console_command') }}">
                            <button type="submit" :disabled="!connected || !server?.permissions['control.console']" aria-label="{{ __('pelican-simplify::dashboard.ui.send_command') }}">↵</button>
                        </form>
                    </aside>
                </div>
            </div>
        </section>
    </main>

    {{-- Phone tab bar (overview only): servers, create and the panel menu within thumb reach. --}}
    <nav class="ps-tabbar" x-show="!server && !hostMode" aria-label="{{ __('pelican-simplify::dashboard.ui.phone_navigation') }}" x-on:click.outside="createMenu=false" x-on:keydown.escape.stop="createMenu=false">
        <button type="button" class="ps-tab is-active" aria-current="page" x-on:click="createMenu=false; navMenu=false; scrollTo({top: 0, behavior: 'smooth'})">
            <svg aria-hidden="true"><use href="#ps-host"/></svg><span>{{ __('pelican-simplify::dashboard.ui.servers') }}</span>
        </button>
        <div class="ps-tab-create" x-show="boot.can_create">
            <button type="button" class="ps-tab" aria-haspopup="dialog" :aria-expanded="createMenu" x-on:click="navMenu=false; createChoices().length===1 ? openCreate(createChoices()[0].key) : (createMenu=!createMenu)">
                <span class="ps-tab-plus" :class="{'is-pumpkin': halloween()}"><svg aria-hidden="true" x-show="!halloween()"><use href="#ps-plus"/></svg><svg class="ps-hw-tab" viewBox="0 3 24 18" aria-hidden="true" x-show="halloween()" x-cloak><path class="ps-hw-body" d="M12 6.5c-1.6-.9-3.6-1-5.3-.2C3.8 7.6 2.5 10.7 3.3 14c.8 3.4 3.6 5.6 6.4 5.4.8 0 1.6-.3 2.3-.7.7.4 1.5.7 2.3.7 2.8.2 5.6-2 6.4-5.4.8-3.3-.5-6.4-3.4-7.7-1.7-.8-3.7-.7-5.3.2z"/><path class="ps-hw-ribs" d="M8.2 7.2c-1.4 3-1.4 7.5.6 11.6M15.8 7.2c1.4 3 1.4 7.5-.6 11.6"/><path class="ps-hw-stem" d="M12 6.5c0-1.6.6-2.8 2-3.6"/><path class="ps-hw-carve" d="M12 9.6v6.4M8.8 12.8h6.4"/></svg></span><span>{{ __('pelican-simplify::dashboard.ui.new') }}</span>
            </button>
            <div class="ps-dropdown ps-tab-create-menu" x-show="createMenu" x-cloak x-transition.opacity role="menu">
                <template x-for="choice in createChoices()" :key="choice.key">
                    <button type="button" class="ps-menu-item" role="menuitem" x-on:click="createMenu=false; openCreate(choice.key)">
                        <svg aria-hidden="true"><use :href="'#ps-'+choice.icon"/></svg><span x-text="choice.label"></span>
                    </button>
                </template>
            </div>
        </div>
        <button type="button" class="ps-tab" aria-controls="ps-navigation-items" :aria-expanded="navMenu" x-on:click.stop="createMenu=false; accountMenu=false; navMenu=!navMenu">
            <svg aria-hidden="true"><use href="#ps-menu"/></svg><span>{{ __('pelican-simplify::dashboard.ui.menu') }}</span>
        </button>
    </nav>

    {{-- A plugin button running in its own panel: invisible until its dialog opens, which then shows over Simplify. --}}
    <template x-if="pluginActionFrame">
        <div class="ps-plugin-action-layer" :class="{'is-open': pluginActionOpen}">
            <iframe data-ps-frame="native" :data-ps-accent="currentAccentHex()" :src="pluginActionFrame.url" title="{{ __('pelican-simplify::dashboard.ui.plugin_action') }}" x-on:load="pluginActionLoaded($el)"></iframe>
        </div>
    </template>

    {{-- Workspace popup: one shell with fixed sizes (sm, md, lg); only the body scrolls. ------------------- --}}
    <div class="ps-modal-layer" data-ps-workspace :data-open="!!modal && !docked()" :data-docked="docked()" x-show="modal" x-cloak x-effect="modal && !docked() && focusPopup($el)" x-on:keydown.escape="modal && !docked() && close()">
        <section class="ps-modal" :class="modalSize()" x-on:touchstart="sheetStart($event)" x-on:touchmove="sheetMove($event)" x-on:touchend="sheetEnd()" x-on:touchcancel="sheetEnd()" :role="docked() ? 'region' : 'dialog'" :aria-modal="modal && !docked() ? 'true' : null" :aria-label="modalTitle()" x-trap.noscroll="modal && !docked()">
            <header class="ps-modal-head">
                <h2 x-text="modalTitle()"></h2>
                <button type="button" class="ps-btn is-sm is-icon is-subtle" x-show="modal==='files' && server?.files_url" x-on:click="window.open(server.files_url, 'ps-files-'+server.uuid, 'popup,width=1280,height=860')" data-tip="{{ __('pelican-simplify::dashboard.ui.open_in_a_separate_window') }}" aria-label="{{ __('pelican-simplify::dashboard.ui.open_file_manager_in_a_separate') }}"><svg aria-hidden="true"><use href="#ps-external"/></svg></button>
                <div class="ps-sftp-inline" x-show="modal==='files' && server?.sftp">
                    <button type="button" class="ps-btn is-sm ps-sftp-toggle" x-on:click="sftpOpen=!sftpOpen" :aria-expanded="sftpOpen" aria-controls="ps-sftp-details" x-text="sftpOpen ? @js(__('pelican-simplify::dashboard.ui.hide_sftp_details')) : @js(__('pelican-simplify::dashboard.ui.show_sftp_details'))"></button>
                    <span id="ps-sftp-details" class="ps-sftp-details" x-show="sftpOpen" x-cloak>
                        <button type="button" class="ps-sftp-value" x-on:click="copyText(server.sftp.username, @js(__('pelican-simplify::dashboard.ui.username_copied')))" data-tip="{{ __('pelican-simplify::dashboard.ui.copy_sftp_username') }}"><span x-text="server?.sftp?.username"></span><svg aria-hidden="true"><use href="#ps-copy"/></svg></button>
                        <small>{{ __('pelican-simplify::dashboard.ui.password_your_panel_password') }}</small>
                        <a class="ps-sftp-connect" :href="sftpLink()" data-tip="{{ __('pelican-simplify::dashboard.ui.open_sftp_connection') }}"><span x-text="server?.sftp?.url"></span><svg aria-hidden="true"><use href="#ps-link"/></svg></a>
                    </span>
                </div>
                <button type="button" class="ps-btn is-icon is-subtle ps-close" x-on:click="close()" aria-label="{{ __('pelican-simplify::dashboard.ui.close_dialog') }}"><svg aria-hidden="true"><use href="#ps-close"/></svg></button>
            </header>

            <div class="ps-modal-body" :class="{'is-flush': modalFlush()}">
                <div x-show="loading" class="ps-modal-loading" role="status"><span class="ps-spinner"></span><span>{{ __('pelican-simplify::dashboard.ui.loading_your_workspace') }}</span></div>

                <template x-if="modal==='create'">
                    <iframe :data-ps-frame="createFrameKind()" :data-ps-accent="currentAccentHex()" :src="createFrameUrl()" data-ps-create title="{{ __('pelican-simplify::dashboard.ui.create_server') }}" x-on:load="createFrameLoaded($el)"></iframe>
                </template>
                <template x-if="modal==='panel-settings'">
                    <iframe data-ps-frame="settings" :data-ps-accent="currentAccentHex()" :src="panelSettingsUrl()" title="{{ __('pelican-simplify::dashboard.ui.simplify_settings') }}" x-on:load="panelSettingsLoaded($el)"></iframe>
                </template>
                <template x-if="modal==='external' && externalPage">
                    <iframe data-ps-frame="native" :data-ps-accent="currentAccentHex()" :src="frameUrl(externalPage.url)" :title="externalPage.label" x-on:load="styleFrame($el, 'native'); loading=false"></iframe>
                </template>
                <template x-if="modal==='admin-page' && adminPage">
                    <iframe data-ps-frame="native" :data-ps-accent="currentAccentHex()" :src="frameUrl(adminPage.url)" :title="adminPage.label" x-on:load="styleFrame($el, 'native')"></iframe>
                </template>
                <template x-if="modal==='account'">
                    <iframe data-ps-frame="account" :data-ps-accent="currentAccentHex()" :src="frameUrl(boot.profile_url)" title="{{ __('pelican-simplify::dashboard.ui.profile') }}" x-on:load="styleFrame($el, 'account')"></iframe>
                </template>

                {{-- Every notification: what an admin pinned on top, then the rest with "Show more"; a full-screen sheet on phones. --}}
                <div x-show="modal==='inbox'" class="ps-inbox">
                    <div class="ps-inbox-bar">
                        <span class="ps-help" x-text="unreadNotifications() ? Simplify.fill(@js(__('pelican-simplify::dashboard.ui.unread')), { count: unreadNotifications() }) : @js(__('pelican-simplify::dashboard.ui.everything_is_read'))"></span>
                        <button type="button" class="ps-btn is-sm" x-show="announcementsPage()" x-on:click="newAnnouncement()"><svg aria-hidden="true"><use href="#ps-plus"/></svg>{{ __('pelican-simplify::dashboard.ui.new_announcement') }}</button>
                        <button type="button" class="ps-btn is-sm is-subtle" x-show="unreadNotifications()" x-on:click="markAllNotificationsRead()"><svg aria-hidden="true"><use href="#ps-check"/></svg>{{ __('pelican-simplify::dashboard.ui.mark_all_read') }}</button>
                        <button type="button" class="ps-btn is-sm is-subtle" x-show="canClearRead()" x-on:click="clearReadNotifications()" data-tip="{{ __('pelican-simplify::dashboard.ui.moves_read_notifications_to_archived_pinned') }}">{{ __('pelican-simplify::dashboard.ui.clear_read') }}</button>
                    </div>
                    <template x-if="modal==='inbox'">
                        <div>@include('pelican-simplify::notification-items', ['archive' => true])</div>
                    </template>
                </div>

                {{-- Alias --}}
                {{-- App notifications on this and the account's other devices (Web Push through the installed app or browser). --}}
                <div x-show="modal==='notifications'" class="ps-customize ps-push">
                    <p class="ps-help" x-show="pushNeedsInstall()">{{ __('pelican-simplify::dashboard.ui.on_iphone_and_ipad_notifications_only') }}</p>
                    <p class="ps-help" x-show="!pushSupported() && !pushNeedsInstall()">{{ __('pelican-simplify::dashboard.ui.this_browser_cannot_show_notifications_from') }}</p>
                    <p class="ps-error" x-show="push.error" x-text="push.error"></p>
                    {{-- The test is sent straight away; real notifications go out through Pelican's scheduler (simplify:announcements). --}}
                    <template x-if="push.settings?.scheduler?.stalled">
                        <div class="ps-alert is-warn ps-push-scheduler" role="alert"><svg aria-hidden="true"><use href="#ps-alert"/></svg><div>
                            <strong>{{ __('pelican-simplify::dashboard.ui.notifications_are_not_being_sent') }}</strong>
                            <small x-show="push.settings.scheduler.cron">{{ __('pelican-simplify::dashboard.ui.pelicans_scheduler_is_not_running_add') }}</small>
                            <code x-show="push.settings.scheduler.cron" x-text="push.settings.scheduler.cron"></code>
                            <small x-show="!push.settings.scheduler.cron">{{ __('pelican-simplify::dashboard.ui.the_panels_scheduler_is_not_running') }}</small>
                        </div></div>
                    </template>
                    <template x-if="pushSupported()">
                        <div>
                            <span class="ps-label">{{ __('pelican-simplify::dashboard.ui.this_device') }}</span>
                            <button type="button" class="ps-customize-switch" role="switch" :aria-checked="pushOn()" :disabled="push.busy || !push.settings" x-on:click="pushOn() ? disablePush() : enablePush()">
                                <span><strong>{{ __('pelican-simplify::dashboard.ui.notifications_on_this_device') }}</strong><small x-text="pushBlocked() ? @js(__('pelican-simplify::dashboard.ui.blocked_in_the_browsers_site_settings')) : deviceLabel()"></small></span>
                                <span class="ps-switch" :class="{'is-on': pushOn()}" aria-hidden="true"></span>
                            </button>
                            <div class="ps-customize-row">
                                <div><strong>{{ __('pelican-simplify::dashboard.ui.test_notification') }}</strong><small>{{ __('pelican-simplify::dashboard.ui.sends_a_test_to_this_device') }}</small></div>
                                <button type="button" class="ps-btn is-sm" x-on:click="testPush()" :disabled="!pushOn() || push.busy">{{ __('pelican-simplify::dashboard.ui.send_test') }}</button>
                            </div>
                        </div>
                    </template>
                    <template x-if="push.settings">
                        <div>
                            <span class="ps-label">{{ __('pelican-simplify::dashboard.ui.what_you_get') }}</span>
                            <button type="button" class="ps-customize-switch" role="switch" :aria-checked="!!push.settings.preferences.announcements" x-on:click="setPushPreference('announcements', !push.settings.preferences.announcements)">
                                <span><strong>{{ __('pelican-simplify::dashboard.ui.announcements') }}</strong><small>{{ __('pelican-simplify::dashboard.ui.announcements_that_an_admin_sends_with') }}</small></span>
                                <span class="ps-switch" :class="{'is-on': push.settings.preferences.announcements}" aria-hidden="true"></span>
                            </button>
                            <button type="button" class="ps-customize-switch" role="switch" x-show="push.settings.admin" :aria-checked="!!push.settings.preferences.updates" x-on:click="setPushPreference('updates', !push.settings.preferences.updates)">
                                <span><strong>{{ __('pelican-simplify::dashboard.ui.updates') }}</strong><small>{{ __('pelican-simplify::dashboard.ui.a_new_version_of_simplify_a') }}</small></span>
                                <span class="ps-switch" :class="{'is-on': push.settings.preferences.updates}" aria-hidden="true"></span>
                            </button>
                            <small class="ps-push-note">{{ __('pelican-simplify::dashboard.ui.pelicans_own_notifications_in_the_bell') }}</small>
                            <template x-if="push.settings.devices.some((device) => !device.current)">
                                <div>
                                    <span class="ps-label">{{ __('pelican-simplify::dashboard.ui.your_other_devices') }}</span>
                                    <template x-for="device in push.settings.devices.filter((device) => !device.current)" :key="device.id">
                                        <div class="ps-customize-row">
                                            <div><strong x-text="device.label"></strong><small x-text="pushDeviceTime(device)"></small></div>
                                            <button type="button" class="ps-btn is-sm is-subtle" x-on:click="removePushDevice(device.id)">{{ __('pelican-simplify::dashboard.ui.remove') }}</button>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
                <template x-if="modal==='alias'">
                    <div>
                        <label class="ps-field">{{ __('pelican-simplify::dashboard.ui.server_alias') }}<input x-model="aliasDraft" maxlength="80" placeholder="{{ __('pelican-simplify::dashboard.ui.leave_empty_to_use_the_server') }}" x-on:keydown.enter="saveAlias()"></label>
                        <p class="ps-help">{{ __('pelican-simplify::dashboard.ui.the_actual_server_name_stays_unchanged') }}</p>
                    </div>
                </template>

                {{-- Background --}}
                <template x-if="modal==='background'">
                    <div>
                        <p class="ps-help">{{ __('pelican-simplify::dashboard.ui.choose_the_artwork_for_this_server') }}</p>
                        <div class="ps-background-grid">
                            <button type="button" class="ps-background-choice ps-background-auto" :aria-pressed="backgroundDraft==='auto'" x-on:click="backgroundDraft='auto'" :disabled="busy">
                                <svg aria-hidden="true"><use href="#ps-image"/></svg><strong>{{ __('pelican-simplify::dashboard.ui.automatic') }}</strong><small>{{ __('pelican-simplify::dashboard.ui.a_consistent_image_for_this_server') }}</small>
                            </button>
                            <button type="button" class="ps-background-choice ps-background-auto" :aria-pressed="backgroundDraft==='none'" x-on:click="backgroundDraft='none'" :disabled="busy">
                                <strong>{{ __('pelican-simplify::dashboard.ui.no_background') }}</strong><small>{{ __('pelican-simplify::dashboard.ui.keep_it_simple') }}</small>
                            </button>
                            <button type="button" class="ps-background-choice ps-background-auto ps-background-color" :class="{'is-auto': true}" :aria-pressed="backgroundDraft==='color'" x-on:click="backgroundDraft='color'" :disabled="busy">
                                <span class="ps-banner-swatch ps-banner-color is-auto"></span><strong>{{ __('pelican-simplify::dashboard.ui.accent_colour') }}</strong><small>{{ __('pelican-simplify::dashboard.ui.a_colour_that_stands_out_but') }}</small>
                            </button>
                            <label class="ps-background-choice ps-background-auto ps-background-color" :aria-pressed="backgroundDraft.startsWith('color:') ? 'true' : 'false'">
                                <span class="ps-banner-swatch ps-banner-color" :style="{'--ps-banner': backgroundDraft.startsWith('color:') ? backgroundDraft.slice(6) : '#7c3aed'}"></span><strong>{{ __('pelican-simplify::dashboard.ui.your_own_colour') }}</strong><small>{{ __('pelican-simplify::dashboard.ui.click_to_pick_a_colour') }}</small>
                                <input type="color" class="ps-visually-hidden" :value="backgroundDraft.startsWith('color:') ? backgroundDraft.slice(6) : '#7c3aed'" x-on:input="backgroundDraft='color:'+$event.target.value.toLowerCase()" :disabled="busy" aria-label="{{ __('pelican-simplify::dashboard.ui.pick_a_banner_colour') }}">
                            </label>
                            <template x-for="image in boot.background_choices || []" :key="image.id">
                                <button type="button" class="ps-background-choice" :aria-pressed="backgroundDraft===image.id" x-on:click="backgroundDraft=image.id" :disabled="busy"><img :src="image.url" :alt="image.name" loading="lazy"><strong x-text="image.name"></strong></button>
                            </template>
                        </div>
                        <h3 class="ps-section-title">{{ __('pelican-simplify::dashboard.ui.your_uploaded_banners') }}</h3>
                        <div class="ps-background-grid">
                            <template x-for="image in uploadedBackgrounds" :key="image.id">
                                <article class="ps-uploaded-banner">
                                    <button type="button" class="ps-background-choice" :aria-pressed="backgroundDraft===image.id" x-on:click="backgroundDraft=image.id" :disabled="busy"><img :src="image.url" :alt="image.name" loading="lazy"><strong x-text="image.name"></strong></button>
                                    <button type="button" class="ps-btn is-sm is-danger" x-on:click="deleteBanner(image.id)" :disabled="busy">{{ __('pelican-simplify::dashboard.ui.remove') }}</button>
                                </article>
                            </template>
                        </div>
                        <p class="ps-help" x-show="!uploadedBackgrounds.length">{{ __('pelican-simplify::dashboard.ui.no_uploaded_banners_yet') }}</p>
                        <label class="ps-field">{{ __('pelican-simplify::dashboard.ui.upload_a_banner') }}<input type="file" accept="image/png,image/jpeg,image/webp" wire:model="bannerUpload" x-on:change.capture="bannerUploadChanged($event)" x-on:livewire-upload-error="toast(@js(__('pelican-simplify::dashboard.ui.upload_failed')), 'danger')"></label>
                        @error('bannerUpload')<p class="ps-alert is-danger">{{ $message }}</p>@enderror
                        <p class="ps-help">{{ __('pelican-simplify::dashboard.ui.banner_help', ['limit' => \Simplify\Panel\Services\UploadLimits::label(\Simplify\Panel\Services\UploadLimits::bannerBytes())]) }}</p>
                        <button type="button" class="ps-btn" x-on:click="uploadBanner()" :disabled="busy" wire:loading.attr="disabled" wire:target="bannerUpload,uploadServerBanner"><svg aria-hidden="true"><use href="#ps-download"/></svg>{{ __('pelican-simplify::dashboard.ui.upload_and_use_banner') }}</button>
                    </div>
                </template>

                {{-- Game settings: tabs on the left, one save action in the footer. --}}
                <div x-show="modal==='settings' && !loading" class="ps-settings-workspace" :class="{'has-tabs': server?.minecraft}">
                    <nav class="ps-tabs" x-show="server?.minecraft" aria-label="{{ __('pelican-simplify::dashboard.ui.settings_sections') }}" x-init="scrollHint($el)">
                        <template x-for="tab in settingsTabs()" :key="tab.key">
                            <button type="button" :aria-pressed="settingsTab===tab.key" x-on:click="settingsTab=tab.key"><svg aria-hidden="true"><use :href="'#ps-'+tab.icon"/></svg><span x-text="tab.label"></span></button>
                        </template>
                    </nav>
                    <div class="ps-tab-panels">
                        <section x-show="settingsTab==='general' || !server?.minecraft">
                            <h3 class="ps-section-title">{{ __('pelican-simplify::dashboard.ui.server_identity') }}</h3>
                            <label class="ps-field">{{ __('pelican-simplify::dashboard.ui.server_name') }}
                                <span class="ps-input-row"><input x-model="name" maxlength="255"><button type="button" class="ps-btn" x-on:click="renameServer()" :disabled="busy || !server?.permissions['settings.rename']">{{ __('pelican-simplify::dashboard.ui.rename') }}</button></span>
                            </label>
                            <label class="ps-field">{{ __('pelican-simplify::dashboard.ui.display_alias') }}
                                <span class="ps-input-row"><input x-model="aliasDraft" maxlength="80" placeholder="{{ __('pelican-simplify::dashboard.ui.leave_empty_to_use_the_server') }}"><button type="button" class="ps-btn" x-on:click="saveAlias()" :disabled="busy || !server?.permissions['settings.rename']">{{ __('pelican-simplify::dashboard.ui.save_alias') }}</button></span>
                            </label>
                            <p class="ps-help">{{ __('pelican-simplify::dashboard.ui.name_and_alias_are_saved_immediately') }}</p>
                            <template x-if="server?.minecraft">
                                <div>
                                    <h3 class="ps-section-title">{{ __('pelican-simplify::dashboard.ui.server_icon') }}</h3>
                                    <label class="ps-field">{{ __('pelican-simplify::dashboard.ui.64_64_png') }}<input type="file" accept="image/png" wire:model="iconUpload"></label>
                                    <p class="ps-help">{{ __('pelican-simplify::dashboard.ui.the_icon_is_uploaded_with_your') }}</p>
                                </div>
                            </template>
                        </section>
                        <section x-show="settingsTab==='gameplay' && server?.minecraft">
                            <h3 class="ps-section-title">{{ __('pelican-simplify::dashboard.ui.gameplay') }}</h3>
                            <div class="ps-grid-2">
                                <label class="ps-field">{{ __('pelican-simplify::dashboard.ui.maximum_players') }}<input type="number" min="1" max="1000" x-model.number="settings.max_players"></label>
                                <label class="ps-field">{{ __('pelican-simplify::dashboard.ui.difficulty') }}<select x-model="settings.difficulty"><option>peaceful</option><option>easy</option><option>normal</option><option>hard</option></select></label>
                                <label class="ps-field">{{ __('pelican-simplify::dashboard.ui.game_mode') }}<select x-model="settings.gamemode"><option>survival</option><option>creative</option><option>adventure</option><option>spectator</option></select></label>
                                <label class="ps-field">{{ __('pelican-simplify::dashboard.ui.view_distance') }}<input type="number" min="2" max="32" x-model.number="settings.view_distance"></label>
                                <label class="ps-field">{{ __('pelican-simplify::dashboard.ui.spawn_protection') }}<input type="number" min="0" max="1000" x-model.number="settings.spawn_protection"></label>
                            </div>
                            <h3 class="ps-section-title">{{ __('pelican-simplify::dashboard.ui.rules') }}</h3>
                            <div class="ps-checks">
                                <label class="ps-check"><input type="checkbox" role="switch" x-model="settings.pvp"> {{ __('pelican-simplify::dashboard.ui.pvp') }}</label>
                                <label class="ps-check"><input type="checkbox" role="switch" x-model="settings.white_list"> {{ __('pelican-simplify::dashboard.ui.whitelist') }}</label>
                                <label class="ps-check"><input type="checkbox" role="switch" x-model="settings.allow_flight"> {{ __('pelican-simplify::dashboard.ui.allow_flight') }}</label>
                            </div>
                        </section>
                        <section x-show="settingsTab==='motd' && server?.minecraft" class="ps-motd-editor">
                            <h3 class="ps-section-title">{{ __('pelican-simplify::dashboard.ui.message_of_the_day') }}</h3>
                            <div class="ps-motd" x-data="psMotdEditor({ name: () => server?.alias || server?.name, icon: () => safeImage(server?.server_icon) })" x-modelable="value" x-model="settings.motd">
                                @include('pelican-simplify::motd-editor')
                            </div>
                        </section>
                        <p x-show="server?.minecraft && !server?.properties_available" class="ps-alert is-warn">{{ __('pelican-simplify::dashboard.ui.settings_are_available_after_server_properties') }}</p>
                    </div>
                </div>

                {{-- Performance --}}
                <div x-show="modal==='performance' && !loading">
                    <p class="ps-help">{{ __('pelican-simplify::dashboard.ui.allocated_cpu_memory_and_storage_for') }}</p>
                    <dl class="ps-kv">
                        <dt>{{ __('pelican-simplify::dashboard.ui.cpu_limit') }}</dt><dd x-text="server?.cpu ? server.cpu+'%' : @js(__('pelican-simplify::dashboard.ui.unlimited'))"></dd>
                        <dt>{{ __('pelican-simplify::dashboard.ui.memory_limit') }}</dt><dd x-text="server?.memory ? bytes(server.memory*1048576) : @js(__('pelican-simplify::dashboard.ui.unlimited'))"></dd>
                        <dt>{{ __('pelican-simplify::dashboard.ui.storage_limit') }}</dt><dd x-text="server?.disk ? bytes(server.disk*1048576) : @js(__('pelican-simplify::dashboard.ui.unlimited'))"></dd>
                    </dl>
                    <section x-show="server?.can_resources">
                        <h3 class="ps-section-title">{{ __('pelican-simplify::dashboard.ui.change_resources') }}</h3>
                        <template x-for="group in resourceGroups" :key="group.key">
                            <div class="ps-resource-group">
                                <span class="ps-field" x-text="group.label"></span>
                                <div class="ps-choice-cards" role="radiogroup" :aria-label="group.label">
                                    <template x-for="option in [...group.presets, ['custom', @js(__('pelican-simplify::dashboard.ui.custom')), @js(__('pelican-simplify::dashboard.ui.choose_your_own'))]]" :key="option[0]">
                                        <button type="button" role="radio" class="ps-choice" :aria-checked="String(resourceChoice(group) === option[0])" x-on:click="pickResource(group, option[0])">
                                            <svg aria-hidden="true"><use :href="'#ps-' + group.icon"/></svg>
                                            <span class="ps-choice-title" x-text="option[1]"></span>
                                            <span class="ps-choice-description" x-text="option[2]"></span>
                                        </button>
                                    </template>
                                </div>
                                <label class="ps-field" x-show="resourceChoice(group) === 'custom'"><span x-text="group.custom + (resourceMin(group) === 0 ? @js(__('pelican-simplify::dashboard.ui.0_unlimited')) : '')"></span><input type="number" step="any" :min="resourceMin(group)" :max="group.max" x-model.number="settings[group.key]"></label>
                            </div>
                        </template>
                        <p class="ps-help" x-text="installFailed() ? @js(__('pelican-simplify::dashboard.ui.the_new_limits_are_used_when')) : @js(__('pelican-simplify::dashboard.ui.save_changes_then_apply_them_with'))"></p>
                    </section>
                    <p class="ps-help" x-show="!server?.can_resources">{{ __('pelican-simplify::dashboard.ui.ask_an_administrator_to_change_these') }}</p>
                </div>

                {{-- Files: fixed workspace, each iframe scrolls on its own. --}}
                <div x-show="modal==='files' && !loading" class="ps-files-workspace" :class="{'has-editor': !!fileEditorUrl}">
                    <div class="ps-files-list">
                        <iframe x-ref="filesFrame" data-ps-frame="files" :data-ps-accent="currentAccentHex()" :src="modal==='files' ? frameUrl(filesStartUrl()) : 'about:blank'" x-on:load="filesFrameLoaded($el)" title="{{ __('pelican-simplify::dashboard.ui.server_file_manager') }}"></iframe>
                    </div>
                    <template x-if="fileEditorUrl">
                        <section class="ps-file-editor">
                            <header>
                                <svg class="ps-file-editor-icon" aria-hidden="true"><use href="#ps-file"/></svg>
                                <strong class="ps-file-editor-path" :data-tip="fileEditorPath" x-text="fileEditorPath.split('/').pop() || fileEditorPath"></strong>
                                <span class="ps-badge is-warn" x-show="fileEditorDirty">{{ __('pelican-simplify::dashboard.ui.unsaved') }}</span>
                                <span class="ps-file-editor-actions">
                                    <button type="button" class="ps-btn is-sm is-primary" x-show="fileEditorExternal" :disabled="fileEditorChecking || fileEditorSaving" x-on:click="saveFileEditor()" data-tip="{{ __('pelican-simplify::dashboard.ui.save_and_close_ctrl_s') }}"><svg aria-hidden="true"><use href="#ps-save"/></svg><span>{{ __('pelican-simplify::dashboard.ui.save') }}</span></button>
                                    <button type="button" class="ps-btn is-icon is-sm is-subtle" x-on:click="closeFileEditor()" data-tip="{{ __('pelican-simplify::dashboard.ui.close_editor') }}" aria-label="{{ __('pelican-simplify::dashboard.ui.close_file_editor') }}"><svg aria-hidden="true"><use href="#ps-close"/></svg></button>
                                </span>
                            </header>
                            <iframe data-ps-frame="editor" :data-ps-accent="currentAccentHex()" :src="fileEditorUrl" x-on:load="fileEditorLoaded($el)" title="{{ __('pelican-simplify::dashboard.ui.file_editor') }}"></iframe>
                        </section>
                    </template>
                </div>

                {{-- Backups --}}
                <div x-show="modal==='backups' && !loading">
                    <div class="ps-panel ps-backup-policy">
                        <svg aria-hidden="true"><use href="#ps-backups"/></svg>
                        <div>
                            <strong>{{ __('pelican-simplify::dashboard.ui.automatic_backups') }}</strong>
                            <p x-text="backupPolicyLabel()"></p>
                            <small>{{ __('pelican-simplify::dashboard.ui.a_failed_backup_never_replaces_a') }}</small>
                        </div>
                    </div>
                    <div x-show="server?.root_admin" class="ps-admin-options">
                        <h3 class="ps-section-title">{{ __('pelican-simplify::dashboard.ui.admin_options') }}</h3>
                        <div class="ps-grid-2">
                            <label class="ps-field">{{ __('pelican-simplify::dashboard.ui.interval_minutes') }}<input type="number" min="30" x-model.number="backupSettings.interval_minutes"></label>
                            <label class="ps-field">{{ __('pelican-simplify::dashboard.ui.recovery_points') }}<input type="number" min="1" max="100" x-model.number="backupSettings.retention"></label>
                        </div>
                        <div class="ps-input-row">
                            <label class="ps-check"><input type="checkbox" role="switch" x-model="backupSettings.enabled"> {{ __('pelican-simplify::dashboard.ui.automatic_backups_enabled') }}</label>
                            <label class="ps-check"><input type="checkbox" role="switch" x-model="backupSettings.only_when_used"> {{ __('pelican-simplify::dashboard.ui.skip_while_the_server_has_not') }}</label>
                            <button type="button" class="ps-btn is-primary" x-on:click="saveBackups()" :disabled="busy">{{ __('pelican-simplify::dashboard.ui.save_backup_policy') }}</button>
                        </div>
                    </div>
                    <p class="ps-alert is-warn" x-show="!backups.configured">{{ __('pelican-simplify::dashboard.ui.backup_storage_is_not_configured_yet') }}</p>
                    <h3 class="ps-section-title">{{ __('pelican-simplify::dashboard.ui.recovery_points') }}</h3>
                    <div class="ps-rows">
                        <template x-for="b in backups.items" :key="b.uuid">
                            <div>
                                <span><strong x-text="b.name"></strong><small x-text="b.completed ? (b.successful ? bytes(b.bytes) : @js(__('pelican-simplify::dashboard.ui.failed'))) : @js(__('pelican-simplify::dashboard.ui.in_progress'))"></small></span>
                                <button type="button" class="ps-btn is-sm" x-on:click="downloadBackup(b.uuid)" :disabled="busy || !b.successful || !b.completed || !server?.permissions['backup.download']"><svg aria-hidden="true"><use href="#ps-download"/></svg>{{ __('pelican-simplify::dashboard.ui.download') }}</button>
                                <button type="button" class="ps-btn is-sm" x-on:click="restoreBackup(b.uuid)" :disabled="busy || status!=='offline' || !b.successful || !b.completed || !server?.permissions['backup.restore']"><svg aria-hidden="true"><use href="#ps-restart"/></svg>{{ __('pelican-simplify::dashboard.ui.restore') }}</button>
                            </div>
                        </template>
                    </div>
                    <p x-show="!backups.items.length" class="ps-empty">{{ __('pelican-simplify::dashboard.ui.your_first_automatic_backup_will_appear') }}</p>
                    <div x-show="server?.root_admin">
                        <h3 class="ps-section-title">{{ __('pelican-simplify::dashboard.ui.full_backup_management_admin') }}</h3>
                        <iframe class="ps-embedded-frame" x-ref="backupFrame" data-ps-frame="native" :data-ps-accent="currentAccentHex()" :src="modal==='backups' && server?.root_admin ? frameUrl(server?.backups_url) : 'about:blank'" x-on:load="styleFrame($el, 'native')" title="{{ __('pelican-simplify::dashboard.ui.admin_backup_management') }}"></iframe>
                    </div>
                </div>

                {{-- Players --}}
                <div x-show="modal==='players' && !loading">
                    <div class="ps-player-actions">
                        <label class="ps-field">{{ __('pelican-simplify::dashboard.ui.player_name') }}<input x-model="playerName" maxlength="16" placeholder="{{ __('pelican-simplify::dashboard.ui.minecraft_username') }}"></label>
                        <div class="ps-action-wrap">
                            <template x-for="action in ['Whitelist','Unwhitelist','OP','DeOP','Kick','Ban','Unban']">
                                <button type="button" class="ps-btn is-sm" :class="{'is-danger': ['Kick','Ban'].includes(action)}" x-on:click="playerAction(action)" :disabled="!connected || status!=='running' || !server?.permissions['control.console']" x-text="playerActionLabel(action)"></button>
                            </template>
                        </div>
                    </div>
                    <h3 class="ps-section-title">{{ __('pelican-simplify::dashboard.ui.online_players') }}</h3>
                    <p class="ps-help">{{ __('pelican-simplify::dashboard.ui.servers_may_hide_player_names_in') }}</p>
                    <div class="ps-player-list">
                        <template x-for="p in server?.player_names || []" :key="p.name">
                            <button type="button" x-on:click="playerName=p.name"><img :src="head(p)" :alt="p.name" loading="lazy"><span x-text="p.name"></span><span class="ps-badge is-ok">{{ __('pelican-simplify::dashboard.ui.online') }}</span></button>
                        </template>
                    </div>
                    <p x-show="!server?.player_names?.length" class="ps-empty">{{ __('pelican-simplify::dashboard.ui.no_player_names_reported') }}</p>
                    <template x-for="[key,title] in [['whitelist',@js(__('pelican-simplify::dashboard.ui.whitelist'))],['ops',@js(__('pelican-simplify::dashboard.ui.operators'))],['banned-players',@js(__('pelican-simplify::dashboard.ui.banned_players'))]]">
                        <section>
                            <h3 class="ps-section-title" x-text="title"></h3>
                            <div class="ps-player-list">
                                <template x-for="p in playerLists[key] || []" :key="p.name">
                                    <button type="button" x-on:click="playerName=p.name"><img :src="head(p)" :alt="p.name" loading="lazy"><span x-text="p.name"></span></button>
                                </template>
                            </div>
                            <p class="ps-help" x-show="!(playerLists[key] || []).length">{{ __('pelican-simplify::dashboard.ui.nobody_yet') }}</p>
                        </section>
                    </template>
                </div>

                {{-- Plugins and mods on one page: search on the left (Modrinth, Hangar, SpigotMC), what is installed on the right. --}}
                <div x-show="isPackageModal() && !loading" class="ps-packages">
                    <p class="ps-alert is-warn" x-show="status!=='offline'"><svg aria-hidden="true"><use href="#ps-info"/></svg><span x-text="modal==='plugins' ? @js(__('pelican-simplify::dashboard.ui.stop_to_change_plugins')) : @js(__('pelican-simplify::dashboard.ui.stop_to_change_mods'))"></span></p>
                    <div class="ps-packages-layout">
                        <section class="ps-packages-add" :aria-label="modal==='plugins' ? @js(__('pelican-simplify::dashboard.ui.add_plugin')) : @js(__('pelican-simplify::dashboard.ui.add_mod'))">
                            <form class="ps-input-row ps-package-search" x-on:submit.prevent="searchPackages()">
                                <input class="ps-input" type="search" x-model="packageQuery" :placeholder="modal==='plugins' ? @js(__('pelican-simplify::dashboard.ui.search_plugins')) : @js(__('pelican-simplify::dashboard.ui.search_mods'))" :aria-label="modal==='plugins' ? @js(__('pelican-simplify::dashboard.ui.search_plugins_2')) : @js(__('pelican-simplify::dashboard.ui.search_mods_2'))">
                                <input class="ps-input ps-version-input" x-show="!installed.context?.version" x-model="packageVersion" placeholder="{{ __('pelican-simplify::dashboard.ui.minecraft_version') }}" aria-label="{{ __('pelican-simplify::dashboard.ui.minecraft_version') }}">
                                <button type="submit" class="ps-btn is-primary" :disabled="busy"><svg aria-hidden="true"><use href="#ps-search"/></svg>{{ __('pelican-simplify::dashboard.ui.search') }}</button>
                            </form>
                            <div class="ps-chips" x-show="packageSites().length > 2" role="group" aria-label="{{ __('pelican-simplify::dashboard.ui.sites') }}">
                                <template x-for="site in packageSites()" :key="site.key">
                                    <button type="button" class="ps-chip" :aria-pressed="packageSite===site.key" x-on:click="packageSite=site.key" x-text="site.label"></button>
                                </template>
                            </div>
                            <p class="ps-help ps-curseforge-own" x-show="installed.context?.curseforge_own">
                                <span x-text="installed.context?.curseforge_mine ? @js(__('pelican-simplify::dashboard.ui.curseforge_uses_your_own_api_key')) : (installed.context?.curseforge ? @js(__('pelican-simplify::dashboard.ui.curseforge_uses_the_key_of_this')) : @js(__('pelican-simplify::dashboard.ui.add_a_curseforge_api_key_to')))"></span>
                                <button type="button" class="ps-link" x-on:click="setCurseForgeKey()" x-text="installed.context?.curseforge_mine ? @js(__('pelican-simplify::dashboard.ui.change_or_remove')) : @js(__('pelican-simplify::dashboard.ui.use_my_own_key'))"></button>
                            </p>
                            <h3 class="ps-section-title" x-text="packageHeading()"></h3>
                            <p class="ps-help" x-show="packageSearching">{{ __('pelican-simplify::dashboard.ui.searching') }}</p>
                            <div class="ps-package-list">
                                <template x-for="p in visiblePackages()" :key="p.key">
                                    <article class="ps-package">
                                        <img :src="p.icon ? safeImage(p.icon) : boot.favicon_url" alt="" loading="lazy" :class="{'is-placeholder': !p.icon}">
                                        <div>
                                            <strong x-text="p.title"></strong><small x-show="p.author" x-text="Simplify.t('dashboard.by_author', { author: p.author })"></small>
                                            <p x-text="p.description"></p>
                                            <div class="ps-package-meta">
                                                <a class="ps-source" :class="'is-' + p.source" :href="safeLink(p.page)" target="_blank" rel="noopener noreferrer" x-text="packageDetail(p)?.via ? Simplify.fill(@js(__('pelican-simplify::dashboard.ui.via_github')), { site: p.label }) : p.label"></a>
                                                <span x-show="packageDetail(p)?.version" x-text="versionLabel(packageDetail(p)?.version)"></span>
                                                <span x-show="packageDetail(p)?.for" x-text="Simplify.fill(@js(__('pelican-simplify::dashboard.ui.for_versions')), { versions: packageDetail(p)?.for })"></span>
                                                <span class="ps-badge is-warn" x-show="packageDetail(p) && !packageDetail(p).fits"><svg aria-hidden="true"><use href="#ps-alert"/></svg><span x-text="Simplify.fill(@js(__('pelican-simplify::dashboard.ui.not_made_for')), { version: packageVersion })"></span></span>
                                                <span class="ps-package-also" x-show="p.also.length">{{ __('pelican-simplify::dashboard.ui.also_on') }} <template x-for="(o, i) in p.also" :key="o.key"><span><button type="button" class="ps-link" x-on:click="useSource(p, o)" x-text="o.label"></button><span x-show="i < p.also.length - 1">, </span></span></template></span>
                                            </div>
                                        </div>
                                        <span class="ps-badge is-ok" x-show="installedKey(p.key)"><svg aria-hidden="true"><use href="#ps-check"/></svg>{{ __('pelican-simplify::dashboard.ui.installed') }}</span>
                                        <button type="button" class="ps-btn is-sm" x-show="!installedKey(p.key) && packageDetail(p)?.installable !== false" :class="{'is-primary': packageDetail(p)?.fits}" x-on:click="installPackage(p)" :disabled="busy || status!=='offline' || !packageDetail(p)" x-text="packageDetail(p) && !packageDetail(p).fits ? @js(__('pelican-simplify::dashboard.ui.install_anyway')) : @js(__('pelican-simplify::dashboard.ui.install'))"></button>
                                        <a class="ps-btn is-sm" x-show="!installedKey(p.key) && packageDetail(p)?.installable === false" :href="safeLink(p.page)" target="_blank" rel="noopener noreferrer" :data-tip="packageDetail(p)?.reason">{{ __('pelican-simplify::dashboard.ui.website') }}<svg aria-hidden="true"><use href="#ps-external"/></svg></a>
                                    </article>
                                </template>
                            </div>
                            <p class="ps-help" x-show="!packageSearching && packageSearched && !visiblePackages().length">{{ __('pelican-simplify::dashboard.ui.nothing_found_try_another_name_or') }}</p>
                            <p class="ps-help" x-show="packageOffline.length" x-text="Simplify.fill(@js(__('pelican-simplify::dashboard.ui.not_reachable')), { sites: packageOffline.join(', ') })"></p>
                        </section>

                        <aside class="ps-packages-installed" :aria-label="modal==='plugins' ? @js(__('pelican-simplify::dashboard.ui.installed_plugins')) : @js(__('pelican-simplify::dashboard.ui.installed_mods'))">
                            <header>
                                <h3 class="ps-section-title" x-text="(modal==='plugins' ? @js(__('pelican-simplify::dashboard.ui.installed_plugins')) : @js(__('pelican-simplify::dashboard.ui.installed_mods'))) + ' (' + (installed.items || []).length + ')'"></h3>
                                <span class="ps-badge" x-show="installed.context?.version" data-tip="{{ __('pelican-simplify::dashboard.ui.search_results_are_matched_to_this') }}"><span x-text="packageTarget()"></span></span>
                            </header>
                            <ul class="ps-installed-list">
                                <template x-for="f in installed.items || []" :key="f.file">
                                    <li :class="{'is-off': !f.enabled}">
                                        <img :src="f.icon ? safeImage(f.icon) : boot.favicon_url" alt="" loading="lazy" :class="{'is-placeholder': !f.icon}">
                                        <div>
                                            <strong x-text="f.title"></strong>
                                            <small><span x-text="[versionLabel(f.version), f.label, f.enabled ? '' : @js(__('pelican-simplify::dashboard.ui.off')), f.watch ? @js(__('pelican-simplify::dashboard.ui.checked_at_next_start')) : ''].filter(Boolean).join(' · ')"></span></small>
                                            <small class="ps-installed-failed" x-show="f.auto_off"><svg aria-hidden="true"><use href="#ps-alert"/></svg><span>{{ __('pelican-simplify::dashboard.ui.did_not_load_switched_off') }}</span><button type="button" class="ps-help-dot" x-show="canExplain()" x-on:click="explainPlugin(f.file)" :aria-label="Simplify.fill(@js(__('pelican-simplify::dashboard.ui.ask_why_not_loaded')), { name: f.title })" data-tip="{{ __('pelican-simplify::dashboard.ui.ask_the_ai_assistant_why') }}"><svg aria-hidden="true"><use href="#ps-question"/></svg></button></small>
                                            <small class="ps-installed-reason" x-show="f.auto_off" x-text="f.auto_off"></small>
                                        </div>
                                        <button type="button" class="ps-switch" role="switch" :class="{'is-on': f.enabled}" :aria-checked="f.enabled" :aria-label="(f.enabled ? @js(__('pelican-simplify::dashboard.ui.turn_off')) : @js(__('pelican-simplify::dashboard.ui.turn_on_2'))) + f.title" :data-tip="f.enabled ? @js(__('pelican-simplify::dashboard.ui.on')) : @js(__('pelican-simplify::dashboard.ui.off'))" x-on:click="togglePackage(f)" :disabled="busy || status!=='offline'"></button>
                                        <div class="ps-installed-more" x-data="{open: false}" x-on:click.outside="open=false">
                                            <button type="button" class="ps-btn is-sm is-icon is-subtle" x-on:click="open=!open" :aria-expanded="open" :aria-label="Simplify.fill(@js(__('pelican-simplify::dashboard.ui.more_for')), { name: f.title })"><svg aria-hidden="true"><use href="#ps-dots"/></svg></button>
                                            <div class="ps-dropdown is-end ps-installed-menu" role="menu" x-show="open" x-transition.opacity>
                                                <button type="button" class="ps-menu-item" role="menuitem" x-show="f.key" x-on:click="open=false; installPackage({key: f.key, title: f.title, icon: f.icon, page: f.page}, true)" :disabled="busy || status!=='offline'"><svg aria-hidden="true"><use href="#ps-download"/></svg><span>{{ __('pelican-simplify::dashboard.ui.update') }}</span></button>
                                                <a class="ps-menu-item" role="menuitem" x-show="safeLink(f.page) !== '#'" :href="safeLink(f.page)" target="_blank" rel="noopener noreferrer" x-on:click="open=false"><svg aria-hidden="true"><use href="#ps-external"/></svg><span x-text="Simplify.fill(@js(__('pelican-simplify::dashboard.ui.open_on')), { site: f.label })"></span></a>
                                                <button type="button" class="ps-menu-item is-danger" role="menuitem" x-on:click="open=false; removePackage(f.file)" :disabled="busy || status!=='offline'"><svg aria-hidden="true"><use href="#ps-close"/></svg><span>{{ __('pelican-simplify::dashboard.ui.remove') }}</span></button>
                                            </div>
                                        </div>
                                    </li>
                                </template>
                            </ul>
                            <p class="ps-help" x-show="!(installed.items || []).length" x-text="modal==='plugins' ? @js(__('pelican-simplify::dashboard.ui.no_plugins_installed_yet_add_one')) : @js(__('pelican-simplify::dashboard.ui.no_mods_installed_yet_add_one'))"></p>
                            <button type="button" class="ps-btn is-sm ps-folder-link" x-show="showWidget('files')" x-on:click="openPackageFolder()"><svg aria-hidden="true"><use href="#ps-files"/></svg><span x-text="modal==='plugins' ? @js(__('pelican-simplify::dashboard.ui.open_plugins_folder')) : @js(__('pelican-simplify::dashboard.ui.open_mods_folder'))"></span></button>
                        </aside>
                    </div>
                </div>

                @if(\Simplify\Panel\Services\Extensions::web()) @include('server-directory::simplify.workspace') @endif

                {{-- Network --}}
                <div x-show="modal==='connection' && !loading">
                    <h3 class="ps-section-title">{{ __('pelican-simplify::dashboard.ui.server_address') }}</h3>
                    <p class="ps-help">{{ __('pelican-simplify::dashboard.ui.use_this_address_to_connect_to') }}</p>
                    <button type="button" class="ps-btn ps-address" x-on:click="copyText(server.address)"><span x-text="server?.address || @js(__('pelican-simplify::dashboard.ui.no_address_assigned'))"></span><svg aria-hidden="true"><use href="#ps-copy"/></svg></button>
                    @if(\Simplify\Panel\Services\Subdomains::active())
                        <h3 class="ps-section-title">{{ __('pelican-simplify::dashboard.ui.subdomain') }}</h3>
                        <p class="ps-help">{{ __('pelican-simplify::dashboard.ui.players_can_join_with_this_name') }}</p>
                        <label class="ps-field">{{ __('pelican-simplify::dashboard.ui.name') }}
                            <span class="ps-input-row"><input x-model="requestedSubdomain" maxlength="63" placeholder="oak" x-on:keydown.enter.prevent="saveSubdomain()"><span class="ps-input-suffix" x-text="'.' + (advanced.subdomain?.domain || '')"></span><button type="button" class="ps-btn" x-on:click="saveSubdomain()" :disabled="busy || !server?.owner">{{ __('pelican-simplify::dashboard.ui.save') }}</button></span>
                        </label>
                        <template x-if="subdomainStatus()">
                            <p class="ps-help"><span class="ps-badge" :class="subdomainStatus().tone"><i></i><span x-text="subdomainStatus().label"></span></span> <span x-text="advanced.subdomain?.message"></span></p>
                        </template>
                    @elseif(\Simplify\Panel\Services\Extensions::web()) @include('server-directory::simplify.subdomain') @endif
                </div>

                {{-- Server software (Minecraft): reinstall with another type, version or modpack. --}}
                <div x-show="modal==='software' && server?.minecraft && !loading">
                    <p class="ps-alert is-warn">{{ __('pelican-simplify::dashboard.ui.changing_software_reinstalls_the_server_a') }}</p>
                    <div class="ps-grid-2">
                        <label class="ps-field">{{ __('pelican-simplify::dashboard.ui.server_type') }}<select x-model="software.server_type"><option value="vanilla">{{ __('pelican-simplify::dashboard.ui.vanilla') }}</option><option value="plus">{{ __('pelican-simplify::dashboard.ui.vanilla_2') }}</option><option value="modded">{{ __('pelican-simplify::dashboard.ui.modpack') }}</option></select></label>
                        <label x-show="software.server_type!=='modded'" class="ps-field">{{ __('pelican-simplify::dashboard.ui.installed_software') }}
                            <select x-model.number="software.egg_id"><option value="">{{ __('pelican-simplify::dashboard.ui.choose_software') }}</option><template x-for="e in advanced.eggs || []" :key="e.id"><option :value="e.id" x-text="e.name"></option></template></select>
                        </label>
                        <label x-show="software.server_type!=='modded'" class="ps-field">{{ __('pelican-simplify::dashboard.ui.minecraft_version') }}<input x-model="software.minecraft_version" placeholder="1.21.3"></label>
                    </div>
                    <div x-show="software.server_type==='modded'">
                        <div class="ps-input-row">
                            <input class="ps-input" x-model="packQuery" placeholder="{{ __('pelican-simplify::dashboard.ui.search_modrinth_ftb_technic') }}" x-on:keydown.enter="searchPacks()">
                            <button type="button" class="ps-btn" x-on:click="searchPacks()" :disabled="busy"><svg aria-hidden="true"><use href="#ps-search"/></svg>{{ __('pelican-simplify::dashboard.ui.search') }}</button>
                        </div>
                        <div class="ps-package-grid">
                            <template x-for="p in packs" :key="p.key">
                                <button type="button" class="ps-package" x-on:click="selectPack(p)">
                                    <img :src="safeImage(p.icon_url)" alt="" loading="lazy">
                                    <span><strong x-text="p.title"></strong><small x-text="p.source"></small><p x-text="p.description"></p></span>
                                </button>
                            </template>
                        </div>
                        <label class="ps-field">{{ __('pelican-simplify::dashboard.ui.selected_pack_release') }}
                            <select x-model="software.modpack_version"><option value="">{{ __('pelican-simplify::dashboard.ui.choose_a_release') }}</option><template x-for="[id,name] in Object.entries(packReleases)" :key="id"><option :value="id" x-text="name"></option></template></select>
                        </label>
                    </div>
                    <label class="ps-field">{{ __('pelican-simplify::dashboard.ui.type_the_server_name') }}<input x-model="softwareConfirm" :placeholder="server?.name"></label>
                </div>
                <template x-if="modal==='software' && !server?.minecraft && server?.native_sections?.startup">
                    <iframe data-ps-frame="native" :data-ps-accent="currentAccentHex()" :src="frameUrl(server.native_sections.startup)" x-on:load="styleFrame($el, 'native')" title="{{ __('pelican-simplify::dashboard.ui.game_startup_settings') }}"></iframe>
                </template>
                <template x-if="modal==='schedules' && server?.native_sections?.schedules">
                    <iframe data-ps-frame="native" :data-ps-accent="currentAccentHex()" :src="frameUrl(server.native_sections.schedules)" x-on:load="styleFrame($el, 'native')" title="{{ __('pelican-simplify::dashboard.ui.scheduled_tasks') }}"></iframe>
                </template>
                <template x-if="modal==='activity' && server?.native_sections?.activity">
                    <iframe data-ps-frame="native" :data-ps-accent="currentAccentHex()" :src="frameUrl(server.native_sections.activity)" x-on:load="styleFrame($el, 'native')" title="{{ __('pelican-simplify::dashboard.ui.activity_log') }}"></iframe>
                </template>

                {{-- Advanced --}}
                <div x-show="modal==='advanced' && !loading">
                    <div class="ps-action-wrap">
                        <a class="ps-btn" x-show="server?.permissions['database.read'] && server?.native_sections?.databases" :href="server?.native_sections?.databases" target="_blank" rel="noopener">{{ __('pelican-simplify::dashboard.ui.databases') }}<svg aria-hidden="true"><use href="#ps-link"/></svg></a>
                        <a class="ps-btn" x-show="server?.root_admin && server?.permissions['user.read'] && server?.native_sections?.access" :href="server?.native_sections?.access" target="_blank" rel="noopener">{{ __('pelican-simplify::dashboard.ui.server_access') }}<svg aria-hidden="true"><use href="#ps-link"/></svg></a>
                    </div>
                    <h3 class="ps-section-title">{{ __('pelican-simplify::dashboard.ui.automatic_runtime') }}</h3>
                    <dl class="ps-kv">
                        <dt>{{ __('pelican-simplify::dashboard.ui.container_image') }}</dt><dd x-text="server?.image"></dd>
                        <dt>{{ __('pelican-simplify::dashboard.ui.startup_command') }}</dt><dd x-text="server?.startup"></dd>
                    </dl>
                    <p class="ps-help">{{ __('pelican-simplify::dashboard.ui.selected_automatically_only_administrators_can_override') }}</p>
                    <section x-show="server?.owner" class="ps-danger-zone">
                        <h3 class="ps-section-title">{{ __('pelican-simplify::dashboard.ui.delete_server') }}</h3>
                        <p class="ps-help">{{ __('pelican-simplify::dashboard.ui.deletes_this_server_and_its_files') }}</p>
                        <label class="ps-field">{{ __('pelican-simplify::dashboard.ui.type_the_server_name') }}<input x-model="deleteConfirm" :placeholder="server?.name"></label>
                        <button type="button" class="ps-btn is-danger" x-on:click="deleteServer()" :disabled="busy || status!=='offline' || deleteConfirm!==server?.name">{{ __('pelican-simplify::dashboard.ui.delete_server') }}</button>
                    </section>
                </div>

                {{-- Scheduled restart --}}
                <div x-show="modal==='restart' && !loading">
                    <p class="ps-help">{{ __('pelican-simplify::dashboard.ui.choose_when_to_stop_apply_your') }}</p>
                    <label class="ps-field">{{ __('pelican-simplify::dashboard.ui.restart_in_minutes') }}<input type="number" min="1" max="10080" x-model.number="restartMinutes"></label>
                </div>
            </div>

            {{-- Footer actions belong to the open workspace. --}}
            <footer class="ps-modal-foot" x-show="['alias','background','settings','performance','software','restart'].includes(modal) && !loading && (modal!=='settings' || server?.minecraft) && (modal!=='performance' || server?.can_resources)">
                <span class="is-start" x-show="modal==='settings'" x-text="server?.pending ? @js(__('pelican-simplify::dashboard.ui.changes_are_waiting_for_a_restart')) : @js(__('pelican-simplify::dashboard.ui.saved_changes_apply_after_a_stop'))"></span>
                <button type="button" class="ps-btn" x-on:click="close()" :disabled="busy">{{ __('pelican-simplify::dashboard.ui.cancel') }}</button>
                <button type="button" class="ps-btn is-primary" x-show="modal==='alias'" x-on:click="saveAlias()" :disabled="busy">{{ __('pelican-simplify::dashboard.ui.save_alias') }}</button>
                <button type="button" class="ps-btn is-primary" x-show="modal==='background'" x-on:click="saveBackground()" :disabled="busy"><svg aria-hidden="true"><use href="#ps-check"/></svg><span x-text="busy ? @js(__('pelican-simplify::dashboard.ui.saving')) : @js(__('pelican-simplify::dashboard.ui.save_background'))"></span></button>
                <button type="button" class="ps-btn is-primary" x-show="modal==='settings'" x-on:click="saveSettings()" :disabled="busy || !server?.properties_available || !server?.permissions['file.update']">{{ __('pelican-simplify::dashboard.ui.save_changes') }}</button>
                <button type="button" class="ps-btn is-primary" x-show="modal==='performance'" x-on:click="saveSettings()" :disabled="busy">{{ __('pelican-simplify::dashboard.ui.save_limits') }}</button>
                <button type="button" class="ps-btn is-danger" x-show="modal==='software'" x-on:click="changeSoftware()" :disabled="busy || status!=='offline'">{{ __('pelican-simplify::dashboard.ui.install_selected_software') }}</button>
                <button type="button" class="ps-btn is-primary" x-show="modal==='restart'" x-on:click="restartChanges(restartMinutes)" :disabled="busy"><svg aria-hidden="true"><use href="#ps-clock"/></svg>{{ __('pelican-simplify::dashboard.ui.schedule_restart') }}</button>
            </footer>
        </section>
    </div>

    {{-- Explain this error: the last console lines, explained in plain words by the AI an admin picked (with their own API key). --}}
    <div class="ps-modal-layer ps-explain-layer" :data-open="!!explain" x-show="explain" x-cloak x-on:click.self="closeExplain()" x-on:keydown.escape.stop="closeExplain()">
        <section class="ps-modal ps-explain" role="dialog" aria-modal="true" aria-labelledby="ps-explain-title" x-trap.noscroll="!!explain">
            <header class="ps-modal-head">
                <h2 id="ps-explain-title">{{ __('pelican-simplify::dashboard.ui.log_check') }}</h2>
                <button type="button" class="ps-btn is-sm is-icon is-subtle ps-close" x-on:click="closeExplain()" aria-label="{{ __('pelican-simplify::dashboard.ui.close') }}"><svg aria-hidden="true"><use href="#ps-close"/></svg></button>
            </header>
            <div class="ps-modal-body" aria-live="polite">
                {{-- Root admins can also have the panel log and this node's Wings log explained. --}}
                <div class="ps-segmented ps-explain-sources" role="group" aria-label="{{ __('pelican-simplify::dashboard.ui.what_to_explain') }}" x-show="explainSources().length > 1">
                    <template x-for="source in explainSources()" :key="source">
                        <button type="button" :aria-pressed="explain?.source === source" :class="{'is-active': explain?.source === source}" :disabled="!!explain?.loading" x-on:click="explain?.source === source || explainError(source)" x-text="explainLabel(source)"></button>
                    </template>
                </div>
                <div x-show="explain?.loading" class="ps-modal-loading" role="status"><span class="ps-spinner"></span><span x-text="Simplify.fill(@js(__('pelican-simplify::dashboard.ui.reading_last_lines')), { log: explainNoun(explain?.source) })"></span></div>
                <div x-show="explain?.error" class="ps-explain-error" role="alert"><svg aria-hidden="true"><use href="#ps-alert"/></svg><p x-text="explain?.error"></p></div>
                {{-- First the check (everything seems in order, or what is wrong), then each problem or warning to open when you want to fix it. --}}
                <template x-if="explain?.answer">
                    <div class="ps-explain-answer" :class="{'is-fine': !explain.answer.found}">
                        <div class="ps-explain-verdict">
                            <svg aria-hidden="true"><use :href="explain.answer.found ? '#ps-alert' : '#ps-check'"/></svg>
                            <div><strong x-text="explain.answer.found ? @js(__('pelican-simplify::dashboard.ui.something_needs_attention')) : @js(__('pelican-simplify::dashboard.ui.everything_seems_in_order'))"></strong><p x-show="explain.answer.summary" x-text="explain.answer.summary"></p></div>
                        </div>
                        <section x-show="explain.answer.issues.length">
                            <h3 x-text="explain.answer.found ? @js(__('pelican-simplify::dashboard.ui.what_was_found')) : @js(__('pelican-simplify::dashboard.ui.noticed_something'))"></h3>
                            <template x-for="(issue, index) in explain.answer.issues" :key="index">
                                <details class="ps-explain-issue" :open="explain.answer.found && index === 0">
                                    <summary x-text="issue.title"></summary>
                                    <p x-show="issue.cause" x-text="issue.cause"></p>
                                    <ol x-show="issue.steps.length"><template x-for="(step, stepIndex) in issue.steps" :key="stepIndex"><li x-text="step"></li></template></ol>
                                </details>
                            </template>
                        </section>
                    </div>
                </template>
            </div>
            <footer class="ps-modal-foot">
                <span class="is-start" x-show="explain?.answer?.model" x-text="explain?.answer?.model ? Simplify.fill(@js(__('pelican-simplify::dashboard.ui.checked_by')), { model: explain.answer.model, lines: explain.answer.lines, log: explainNoun(explain.source) }) : ''"></span>
                <button type="button" class="ps-btn" x-show="explain?.answer || explain?.error" x-on:click="explainError()" :disabled="!!explain?.loading"><svg aria-hidden="true"><use href="#ps-restart"/></svg>{{ __('pelican-simplify::dashboard.ui.ask_again') }}</button>
                <button type="button" class="ps-btn is-primary" x-on:click="closeExplain()">{{ __('pelican-simplify::dashboard.ui.done') }}</button>
            </footer>
        </section>
    </div>
</div>
</x-filament-panels::page>

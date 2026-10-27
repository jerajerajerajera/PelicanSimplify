<x-filament-panels::page>
    @assets
    <link rel="stylesheet" href="{{ \Simplify\Panel\Services\BuildInfo::asset('wizard.css') }}">
    @endassets
    @php($rows = $this->announcements())
    @php($panel = $this->panelUpdates())
    <div class="ps-admin-page ps-announcements" x-data x-init="$wire.browserTimezone = window.Intl?.DateTimeFormat?.().resolvedOptions?.().timeZone || ''">
        <div class="ps-people-bar">
            <p class="ps-help ps-people-intro">{{ __('pelican-simplify::announcements.manage.announcements_show_as_a_bar_above') }}</p>
            <div class="ps-person-actions">
                <button type="button" class="ps-btn" wire:click="mountAction('ingameCommands')"><x-tabler-message-chatbot class="ps-ico" />{{ __('pelican-simplify::announcements.manage.in_game_commands') }}</button>
                <button type="button" class="ps-btn is-primary" wire:click="mountAction('addAnnouncement')"><x-tabler-speakerphone class="ps-ico" />{{ __('pelican-simplify::announcements.manage.new_announcement') }}</button>
            </div>
        </div>
        <div class="ps-announcement-list">
            @forelse($rows as $row)
                <article class="ps-announcement is-{{ ['info' => 'info', 'warning' => 'warn', 'success' => 'ok'][$row['category']] }}" wire:key="announcement-{{ $row['id'] }}">
                    @if($row['category'] === 'warning')<x-tabler-alert-triangle class="ps-ico" />@elseif($row['category'] === 'success')<x-tabler-circle-check class="ps-ico" />@else<x-tabler-info-circle class="ps-ico" />@endif
                    <div class="ps-announcement-main">
                        <strong>{{ $row['title'] }}</strong>
                        @if($row['text'] !== '')<p>{{ $row['text'] }}</p>@endif
                        <small>
                            {{ \Simplify\Panel\Services\Translations::labels('announcements.audiences', \Simplify\Panel\Services\Announcements::AUDIENCES)[$row['audience']] }}
                            @if($row['starts_at'] !== null) · {{ __('pelican-simplify::announcements.manage.from', ['date' => $this->date($row['starts_at'])]) }}@endif
                            @if($row['ends_at'] !== null) · {{ __('pelican-simplify::announcements.manage.until', ['date' => $this->date($row['ends_at'])]) }}@else · {{ __('pelican-simplify::announcements.manage.no_end_date') }} @endif
                            @if($row['target']) · {{ __('pelican-simplify::announcements.manage.button_target', ['label' => $row['button']['label'], 'target' => $row['target']]) }}@endif
                            @if($row['pinned']) · {{ __('pelican-simplify::announcements.manage.pinned_everyone') }} @endif
                            @if($row['push']) · {{ $row['pushed_at'] === null ? __('pelican-simplify::announcements.manage.push_when_starts') : ($row['pushed_to'] === null ? __('pelican-simplify::announcements.manage.push_sent') : trans_choice('pelican-simplify::announcements.manage.push_sent_to', $row['pushed_to'])) }}@endif
                            @if($row['created_by'] !== '') · {{ __('pelican-simplify::announcements.manage.by', ['name' => $row['created_by']]) }}@endif
                        </small>
                        @if($row['ingame_status'])
                            <small class="ps-announcement-ingame"><x-tabler-message-chatbot class="ps-ico" />
                                {{ $row['ingame']['targets'] === 'all' ? __('pelican-simplify::announcements.manage.ingame_all') : __('pelican-simplify::announcements.manage.ingame_chosen') }}{{ $row['ingame']['repeat'] ? ', '.mb_strtolower(\Simplify\Panel\Services\Translations::labels('announcements.repeats', \Simplify\Panel\Services\InGameAnnouncements::REPEATS)[$row['ingame']['repeat']]) : '' }}
                                @if($row['ingame_status']['last']) · {{ $row['ingame_status']['last'] }}@endif
                                @if($row['ingame_status']['next']) · {{ $row['ingame_status']['next'] }}@endif
                            </small>
                        @endif
                    </div>
                    <span class="ps-badge {{ ['live' => 'is-ok', 'scheduled' => 'is-info', 'ended' => 'is-muted'][$row['state']] }}"><span>{{ __('pelican-simplify::announcements.manage.states.'.$row['state']) }}</span></span>
                    <div class="ps-person-actions">
                        <button type="button" class="ps-btn is-sm" wire:click="mountAction('editAnnouncement', { id: '{{ $row['id'] }}' })"><x-tabler-edit class="ps-ico" />{{ __('pelican-simplify::announcements.manage.edit') }}</button>
                        @if($row['state'] === 'live')
                            <button type="button" class="ps-btn is-sm is-icon is-subtle" data-tip="{{ __('pelican-simplify::announcements.manage.end_now') }}" aria-label="{{ __('pelican-simplify::announcements.manage.end_name_now', ['name' => $row['title']]) }}" wire:click="mountAction('endAnnouncement', { id: '{{ $row['id'] }}' })"><x-tabler-player-stop class="ps-ico" /></button>
                        @endif
                        <button type="button" class="ps-btn is-sm is-icon is-subtle is-danger" data-tip="{{ __('pelican-simplify::announcements.manage.delete') }}" aria-label="{{ __('pelican-simplify::announcements.manage.delete_name', ['name' => $row['title']]) }}" wire:click="mountAction('deleteAnnouncement', { id: '{{ $row['id'] }}' })"><x-tabler-trash class="ps-ico" /></button>
                    </div>
                </article>
            @empty
                <p class="ps-empty">{{ __('pelican-simplify::announcements.manage.no_announcements_yet_use_new_announcement') }}</p>
            @endforelse
        </div>

        <section class="ps-announcement-section">
            <h2 class="ps-section-title">{{ __('pelican-simplify::announcements.manage.from_the_panel') }}</h2>
            <p class="ps-help">{{ __('pelican-simplify::announcements.manage.added_automatically_for_root_admins_when') }}</p>
            <div class="ps-announcement-list">
                @forelse($panel as $item)
                    <article class="ps-announcement is-info" wire:key="panel-{{ $item['key'] }}">
                        <x-tabler-server class="ps-ico" />
                        <div class="ps-announcement-main">
                            <strong>{{ $item['title'] }}</strong>
                            <p>{{ $item['text'] }}</p>
                            <small>{{ __('pelican-simplify::announcements.manage.panel_admins_only') }} · {{ $item['time'] }}</small>
                        </div>
                        <span class="ps-badge is-info"><span>{{ __('pelican-simplify::announcements.manage.live') }}</span></span>
                    </article>
                @empty
                    <p class="ps-empty">{{ __('pelican-simplify::announcements.manage.everything_is_up_to_date') }}</p>
                @endforelse
            </div>
        </section>

        @if($this->pluginInstalled())
            @php($external = $this->pluginAnnouncements())
            <section class="ps-announcement-section">
                <h2 class="ps-section-title">{{ __('pelican-simplify::announcements.manage.from_the_announcements_plugin') }}</h2>
                <p class="ps-help">{{ __('pelican-simplify::announcements.manage.the_announcements_plugin_is_installed_its') }}</p>
                <div class="ps-announcement-list">
                    @forelse($external as $item)
                        <article class="ps-announcement is-{{ $item['tone'] }}" wire:key="{{ $item['key'] }}">
                            <x-tabler-speakerphone class="ps-ico" />
                            <div class="ps-announcement-main"><strong>{{ $item['title'] }}</strong>@if($item['text'] !== '')<p>{{ $item['text'] }}</p>@endif</div>
                            <span class="ps-badge is-ok"><span>{{ __('pelican-simplify::announcements.manage.live') }}</span></span>
                        </article>
                    @empty
                        <p class="ps-empty">{{ __('pelican-simplify::announcements.manage.none_of_its_announcements_are_live') }}</p>
                    @endforelse
                </div>
            </section>
        @endif
    </div>
</x-filament-panels::page>

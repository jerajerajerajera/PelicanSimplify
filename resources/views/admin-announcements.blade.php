<x-filament-panels::page>
    @assets
    <link rel="stylesheet" href="{{ \Simplify\Panel\Services\BuildInfo::asset('wizard.css') }}">
    @endassets
    @php($rows = $this->announcements())
    @php($panel = $this->panelUpdates())
    <div class="ps-admin-page ps-announcements" x-data x-init="$wire.browserTimezone = window.Intl?.DateTimeFormat?.().resolvedOptions?.().timeZone || ''">
        <div class="ps-people-bar">
            <p class="ps-help ps-people-intro">Announcements show as a bar above the server overview and under the bell. Everyone can hide a bar for themselves; it then stays under the bell until it ends.</p>
            <div class="ps-person-actions">
                <button type="button" class="ps-btn" wire:click="mountAction('ingameCommands')"><x-tabler-message-chatbot class="ps-ico" />In-game commands</button>
                <button type="button" class="ps-btn is-primary" wire:click="mountAction('addAnnouncement')"><x-tabler-speakerphone class="ps-ico" />New announcement</button>
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
                            {{ \Simplify\Panel\Services\Announcements::AUDIENCES[$row['audience']] }}
                            @if($row['starts_at'] !== null) · from {{ $this->date($row['starts_at']) }}@endif
                            @if($row['ends_at'] !== null) · until {{ $this->date($row['ends_at']) }}@else · no end date @endif
                            @if($row['target']) · button “{{ $row['button']['label'] }}” → {{ $row['target'] }}@endif
                            @if($row['pinned']) · pinned to everyone's notifications @endif
                            @if($row['push']) · {{ $row['pushed_at'] === null ? 'app notification when it starts' : ($row['pushed_to'] === null ? 'sent as app notification' : 'app notification sent to '.$row['pushed_to'].' '.($row['pushed_to'] === 1 ? 'device' : 'devices')) }}@endif
                            @if($row['created_by'] !== '') · by {{ $row['created_by'] }}@endif
                        </small>
                        @if($row['ingame_status'])
                            <small class="ps-announcement-ingame"><x-tabler-message-chatbot class="ps-ico" />
                                {{ $row['ingame']['targets'] === 'all' ? 'In game on all servers' : 'In game on chosen servers' }}{{ $row['ingame']['repeat'] ? ', '.strtolower(\Simplify\Panel\Services\InGameAnnouncements::REPEATS[$row['ingame']['repeat']]) : '' }}
                                @if($row['ingame_status']['last']) · {{ $row['ingame_status']['last'] }}@endif
                                @if($row['ingame_status']['next']) · {{ $row['ingame_status']['next'] }}@endif
                            </small>
                        @endif
                    </div>
                    <span class="ps-badge {{ ['live' => 'is-ok', 'scheduled' => 'is-info', 'ended' => 'is-muted'][$row['state']] }}"><span>{{ ucfirst($row['state']) }}</span></span>
                    <div class="ps-person-actions">
                        <button type="button" class="ps-btn is-sm" wire:click="mountAction('editAnnouncement', { id: '{{ $row['id'] }}' })"><x-tabler-edit class="ps-ico" />Edit</button>
                        @if($row['state'] === 'live')
                            <button type="button" class="ps-btn is-sm is-icon is-subtle" data-tip="End now" aria-label="End {{ $row['title'] }} now" wire:click="mountAction('endAnnouncement', { id: '{{ $row['id'] }}' })"><x-tabler-player-stop class="ps-ico" /></button>
                        @endif
                        <button type="button" class="ps-btn is-sm is-icon is-subtle is-danger" data-tip="Delete" aria-label="Delete {{ $row['title'] }}" wire:click="mountAction('deleteAnnouncement', { id: '{{ $row['id'] }}' })"><x-tabler-trash class="ps-ico" /></button>
                    </div>
                </article>
            @empty
                <p class="ps-empty">No announcements yet. Use New announcement to tell everyone about maintenance, an event or a new server.</p>
            @endforelse
        </div>

        <section class="ps-announcement-section">
            <h2 class="ps-section-title">From the Panel</h2>
            <p class="ps-help">Added automatically for root admins when a new version of Simplify, another plugin, Wings or Pelican is available, and removed once it is installed. Checked every ten minutes.</p>
            <div class="ps-announcement-list">
                @forelse($panel as $item)
                    <article class="ps-announcement is-info" wire:key="panel-{{ $item['key'] }}">
                        <x-tabler-server class="ps-ico" />
                        <div class="ps-announcement-main">
                            <strong>{{ $item['title'] }}</strong>
                            <p>{{ $item['text'] }}</p>
                            <small>Panel · admins only · {{ $item['time'] }}</small>
                        </div>
                        <span class="ps-badge is-info"><span>Live</span></span>
                    </article>
                @empty
                    <p class="ps-empty">Everything is up to date.</p>
                @endforelse
            </div>
        </section>

        @if($this->pluginInstalled())
            @php($external = $this->pluginAnnouncements())
            <section class="ps-announcement-section">
                <h2 class="ps-section-title">From the Announcements plugin</h2>
                <p class="ps-help">The Announcements plugin is installed: its live announcements show in Simplify as well. Change them on that plugin's own page.</p>
                <div class="ps-announcement-list">
                    @forelse($external as $item)
                        <article class="ps-announcement is-{{ $item['tone'] }}" wire:key="{{ $item['key'] }}">
                            <x-tabler-speakerphone class="ps-ico" />
                            <div class="ps-announcement-main"><strong>{{ $item['title'] }}</strong>@if($item['text'] !== '')<p>{{ $item['text'] }}</p>@endif</div>
                            <span class="ps-badge is-ok"><span>Live</span></span>
                        </article>
                    @empty
                        <p class="ps-empty">None of its announcements are live right now.</p>
                    @endforelse
                </div>
            </section>
        @endif
    </div>
</x-filament-panels::page>

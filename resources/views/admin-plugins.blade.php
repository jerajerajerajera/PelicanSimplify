<x-filament-panels::page>
    @assets
    <link rel="stylesheet" href="{{ \Simplify\Panel\Services\BuildInfo::asset('wizard.css') }}">
    <script src="{{ \Simplify\Panel\Services\BuildInfo::asset('wizard.js') }}"></script>
    @endassets
    <div class="ps-admin-page ps-plugins">
        <nav class="ps-segmented ps-plugins-tabs" role="tablist" aria-label="Plugins">
            <button type="button" role="tab" aria-pressed="{{ $tab === 'installed' ? 'true' : 'false' }}" wire:click="$set('tab', 'installed')"><x-tabler-puzzle class="ps-ico" />Installed</button>
            <button type="button" role="tab" aria-pressed="{{ $tab === 'hub' ? 'true' : 'false' }}" wire:click="$set('tab', 'hub')"><x-tabler-world class="ps-ico" />Browse Hub</button>
        </nav>
        @if($tab === 'hub')
            @include('pelican-simplify::admin-plugins-hub')
        @else
        @php($plugins = $this->plugins())
        <header class="ps-plugins-head">
            @include('pelican-simplify::partials-conflict-notice')
            <div class="ps-alert is-info ps-plugin-channel" role="note">
                <x-tabler-versions class="ps-ico" aria-hidden="true" />
                <div>
                    <strong>Pelican {{ \Simplify\Panel\Services\PluginUpdater::panelVersion() }} · Simplify {{ \Simplify\Panel\Services\PluginUpdater::channel() }} channel</strong>
                    <small>Updates are always the build made for your Pelican version, so you never get a version that does not fit your panel. {{ ['beta' => 'You also get beta versions.', 'snapshot' => 'You also get beta versions and snapshots.'][\Simplify\Panel\Services\PluginUpdater::channel()] ?? 'You get stable releases only.' }} Change this in Simplify settings → Plugin manager.</small>
                </div>
            </div>
            <p class="ps-help">
                @if(\Simplify\Panel\Services\FeatureSettings::enabled('auto_update_plugins'))
                    New versions are installed automatically within about a minute, with a backup and automatic rollback.
                @else
                    Automatic updates are off: you get a notification for each new version; install it here. A backup is kept and restored if anything fails.
                @endif
            </p>
            @php($age = $this->lastCheckAge())
            @if($age === null || $age > 300)
                <p class="ps-plugin-error">The automatic update check {{ $age === null ? 'has not run yet' : 'last ran '.\Carbon\CarbonInterval::seconds($age)->cascade()->forHumans(['parts' => 1]).' ago' }}. Pelican's scheduler is probably not running: add the cron job <code>* * * * * php {{ base_path('artisan') }} schedule:run</code> for the web server user. Updates from this page still work.</p>
            @endif
        </header>
        <div class="ps-plugin-list" @if($this->busy) wire:poll.3s @else wire:poll.15s @endif>
            @forelse($plugins as $plugin)
                <article class="ps-plugin" wire:key="plugin-{{ $plugin['id'] }}">
                    <div class="ps-plugin-main">
                        <div class="ps-plugin-title">
                            <strong>{{ $plugin['name'] }}</strong>
                            <span class="ps-badge ps-status {{ ['enabled' => 'running', 'disabled' => 'unavailable', 'not_installed' => 'unavailable', 'errored' => 'offline', 'crashed' => 'offline', 'incompatible' => 'offline'][$plugin['status']] ?? '' }}"><i></i><span>{{ str_replace('_', ' ', $plugin['status']) }}</span></span>
                            @if($plugin['latest'])<span class="ps-badge is-info"><span>Update {{ $plugin['latest'] }}</span></span>@endif
                        </div>
                        <small>{{ $plugin['author'] }} · version {{ $plugin['version'] }}</small>
                        @if($plugin['description'])<p>{{ $plugin['description'] }}</p>@endif
                        @if($plugin['busy_since'])
                            <p class="ps-help">Working on it… you get a notification when it is done.
                                @if(now()->timestamp - (int) $plugin['busy_since'] > 300)<span class="ps-plugin-error">This is taking long: reload the page; if it stays, check the panel log.</span>@endif
                            </p>
                        @endif
                        @if($plugin['message'] && in_array($plugin['status'], ['errored', 'crashed', 'incompatible', 'not_installed'], true))<p class="ps-plugin-error">{{ $plugin['message'] }}</p>@endif
                    </div>
                    @if($plugin['can_change'])
                        <div class="ps-plugin-actions">
                            @if($plugin['latest'])
                                <button type="button" class="ps-btn is-sm is-primary" wire:click="updatePlugin('{{ $plugin['id'] }}')" wire:loading.attr="disabled" @disabled($plugin['updating'])>
                                    <x-tabler-download class="ps-ico" />{{ $plugin['updating'] ? 'Updating…' : 'Update' }}
                                </button>
                            @endif
                            @if($plugin['status'] === 'not_installed')
                                <button type="button" class="ps-btn is-sm is-primary" wire:click="installPlugin('{{ $plugin['id'] }}')">Install</button>
                            @endif
                            @if($plugin['status'] === 'enabled')
                                <button type="button" class="ps-btn is-sm" wire:click="setEnabled('{{ $plugin['id'] }}', false)">Disable</button>
                            @elseif(in_array($plugin['status'], ['disabled', 'crashed'], true))
                                <button type="button" class="ps-btn is-sm" wire:click="setEnabled('{{ $plugin['id'] }}', true)">Enable</button>
                            @endif
                            @if(in_array($plugin['status'], ['not_installed', 'errored', 'crashed'], true))
                                @if($plugin['can_delete'])
                                    <button type="button" class="ps-btn is-sm is-danger" wire:click="deletePlugin('{{ $plugin['id'] }}')" wire:confirm="Delete {{ $plugin['name'] }} and its files?">Delete</button>
                                @endif
                            @else
                                <button type="button" class="ps-btn is-sm is-danger" wire:click="uninstallPlugin('{{ $plugin['id'] }}')" wire:confirm="Uninstall {{ $plugin['name'] }}? Its data may be removed.">Uninstall</button>
                            @endif
                        </div>
                    @endif
                </article>
            @empty
                <p class="ps-empty">No plugins installed.</p>
            @endforelse
        </div>
        @endif
    </div>
</x-filament-panels::page>

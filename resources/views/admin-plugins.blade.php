<x-filament-panels::page>
    @assets
    <link rel="stylesheet" href="{{ \Simplify\Panel\Services\BuildInfo::asset('wizard.css') }}">
    <script src="{{ \Simplify\Panel\Services\BuildInfo::asset('wizard.js') }}"></script>
    @endassets
    <div class="ps-admin-page ps-plugins">
        <nav class="ps-segmented ps-plugins-tabs" role="tablist" aria-label="{{ __('pelican-simplify::plugins.page.plugins') }}">
            <button type="button" role="tab" aria-pressed="{{ $tab === 'installed' ? 'true' : 'false' }}" wire:click="$set('tab', 'installed')"><x-tabler-puzzle class="ps-ico" />{{ __('pelican-simplify::plugins.page.installed') }}</button>
            <button type="button" role="tab" aria-pressed="{{ $tab === 'hub' ? 'true' : 'false' }}" wire:click="$set('tab', 'hub')"><x-tabler-world class="ps-ico" />{{ __('pelican-simplify::plugins.page.browse_hub') }}</button>
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
                    <strong>{{ __('pelican-simplify::plugins.page.channel_title', ['pelican' => \Simplify\Panel\Services\PluginUpdater::panelVersion(), 'channel' => \Simplify\Panel\Services\PluginUpdater::channel()]) }}</strong>
                    <small>{{ __('pelican-simplify::plugins.page.channel_build') }} {{ ['beta' => __('pelican-simplify::plugins.page.channel_beta'), 'snapshot' => __('pelican-simplify::plugins.page.channel_snapshot')][\Simplify\Panel\Services\PluginUpdater::channel()] ?? __('pelican-simplify::plugins.page.channel_stable') }} {{ __('pelican-simplify::plugins.page.channel_change') }}</small>
                </div>
            </div>
            <p class="ps-help">
                @if(\Simplify\Panel\Services\FeatureSettings::enabled('auto_update_plugins'))
                    {{ __('pelican-simplify::plugins.page.auto_on') }}
                @else
                    {{ __('pelican-simplify::plugins.page.auto_off') }}
                @endif
            </p>
            {{-- Any of Simplify's minute commands counts: the update check itself can be busy for a while installing updates. --}}
            @if(\Simplify\Panel\Services\PushNotifications::schedulerStalled())
                <p class="ps-plugin-error">{{ __('pelican-simplify::plugins.page.scheduler_stalled') }} <code>* * * * * php {{ base_path('artisan') }} schedule:run</code> {{ __('pelican-simplify::plugins.page.for_the_web_server_user_updates') }}</p>
            @endif
        </header>
        <div class="ps-plugin-list" @if($this->busy) wire:poll.3s @else wire:poll.15s @endif>
            @forelse($plugins as $plugin)
                <article class="ps-plugin" wire:key="plugin-{{ $plugin['id'] }}">
                    <div class="ps-plugin-main">
                        <div class="ps-plugin-title">
                            <strong>{{ $plugin['name'] }}</strong>
                            <span class="ps-badge ps-status {{ ['enabled' => 'running', 'disabled' => 'unavailable', 'not_installed' => 'unavailable', 'errored' => 'offline', 'crashed' => 'offline', 'incompatible' => 'offline'][$plugin['status']] ?? '' }}"><i></i><span>{{ __('pelican-simplify::plugins.page.status.'.$plugin['status']) }}</span></span>
                            @if($plugin['latest'])<span class="ps-badge is-info"><span>{{ __('pelican-simplify::plugins.page.update_version', ['version' => $plugin['latest']]) }}</span></span>@endif
                        </div>
                        <small>{{ $plugin['author'] }} · {{ __('pelican-simplify::plugins.page.version', ['version' => $plugin['version']]) }}</small>
                        @if($plugin['description'])<p>{{ $plugin['description'] }}</p>@endif
                        @if($plugin['busy_since'])
                            <p class="ps-help">{{ __('pelican-simplify::plugins.page.working') }}
                                @if(now()->timestamp - (int) $plugin['busy_since'] > 300)<span class="ps-plugin-error">{{ __('pelican-simplify::plugins.page.this_is_taking_long_reload_the') }}</span>@endif
                            </p>
                        @endif
                        @if($plugin['message'] && in_array($plugin['status'], ['errored', 'crashed', 'incompatible', 'not_installed'], true))<p class="ps-plugin-error">{{ $plugin['message'] }}</p>@endif
                    </div>
                    @if($plugin['can_change'])
                        <div class="ps-plugin-actions">
                            @if($plugin['latest'])
                                <button type="button" class="ps-btn is-sm is-primary" wire:click="updatePlugin('{{ $plugin['id'] }}')" wire:loading.attr="disabled" @disabled($plugin['updating'])>
                                    <x-tabler-download class="ps-ico" />{{ $plugin['updating'] ? __('pelican-simplify::plugins.page.updating') : __('pelican-simplify::plugins.page.update') }}
                                </button>
                            @endif
                            @if($plugin['status'] === 'not_installed')
                                <button type="button" class="ps-btn is-sm is-primary" wire:click="installPlugin('{{ $plugin['id'] }}')">{{ __('pelican-simplify::plugins.page.install') }}</button>
                            @endif
                            @if($plugin['status'] === 'enabled')
                                <button type="button" class="ps-btn is-sm" wire:click="setEnabled('{{ $plugin['id'] }}', false)">{{ __('pelican-simplify::plugins.page.disable') }}</button>
                            @elseif(in_array($plugin['status'], ['disabled', 'crashed'], true))
                                <button type="button" class="ps-btn is-sm" wire:click="setEnabled('{{ $plugin['id'] }}', true)">{{ __('pelican-simplify::plugins.page.enable') }}</button>
                            @endif
                            @if(in_array($plugin['status'], ['not_installed', 'errored', 'crashed'], true))
                                @if($plugin['can_delete'])
                                    <button type="button" class="ps-btn is-sm is-danger" wire:click="deletePlugin('{{ $plugin['id'] }}')" wire:confirm="{{ __('pelican-simplify::plugins.page.delete_confirm', ['name' => $plugin['name']]) }}">{{ __('pelican-simplify::plugins.page.delete') }}</button>
                                @endif
                            @else
                                <button type="button" class="ps-btn is-sm is-danger" wire:click="uninstallPlugin('{{ $plugin['id'] }}')" wire:confirm="{{ __('pelican-simplify::plugins.page.uninstall_confirm', ['name' => $plugin['name']]) }}">{{ __('pelican-simplify::plugins.page.uninstall') }}</button>
                            @endif
                        </div>
                    @endif
                </article>
            @empty
                <p class="ps-empty">{{ __('pelican-simplify::plugins.page.no_plugins_installed') }}</p>
            @endforelse
        </div>
        @endif
    </div>
</x-filament-panels::page>

<x-filament-panels::page>
    @assets
    <link rel="stylesheet" href="{{ \Simplify\Panel\Services\BuildInfo::asset('wizard.css') }}">
    <script src="{{ \Simplify\Panel\Services\BuildInfo::asset('wizard.js') }}"></script>
    @endassets
    <div class="ps-settings-page {{ $this->embedded ? 'is-embedded' : 'is-standalone' }}" x-data x-init="
        const fit = () => { const tabs = $el.querySelector('.ps-settings-tabs.fi-vertical'); if (!tabs) return; const top = tabs.getBoundingClientRect().top + window.scrollY; tabs.style.setProperty('--ps-settings-height', Math.max(420, window.innerHeight - top - 16) + 'px'); };
        fit(); window.addEventListener('resize', fit); new MutationObserver(() => requestAnimationFrame(fit)).observe($el, { childList: true, subtree: true });
    ">
        @if($this->pelicanTabs > 0)
            <style>.ps-settings-tabs .fi-tabs-item:nth-last-child({{ (int) $this->pelicanTabs }}) { margin-top: 30px; position: relative; } .ps-settings-tabs .fi-tabs-item:nth-last-child({{ (int) $this->pelicanTabs }})::before { content: "Pelican"; position: absolute; top: -22px; left: 10px; color: var(--ps-muted); font-size: 11px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }</style>
        @endif
        @if(!$this->needsSetup())
            <header class="ps-settings-hero">
                <span class="ps-settings-logo">@include('pelican-simplify::simplify-icon')</span>
                <div class="ps-settings-hero-text">
                    <h1>{{ __('pelican-simplify::settings.page.simplify_settings') }}</h1>
                    <p>{{ __('pelican-simplify::settings.page.version_line', ['version' => \Simplify\Panel\Services\BuildInfo::version() ?? __('pelican-simplify::settings.page.unknown')]) }}</p>
                </div>
                @if(!$this->embedded && !$showDiagnostics)
                    <div class="ps-settings-hero-actions">
                        <button type="button" class="ps-btn is-sm" wire:click="toggleDiagnostics" wire:loading.attr="disabled"><x-tabler-shield-check class="ps-ico" />{{ __('pelican-simplify::settings.page.system_checks') }}</button>
                        <button type="button" class="ps-btn is-sm" wire:loading.attr="disabled" x-on:click="Simplify.confirm({title:@js(__('pelican-simplify::settings.page.run_the_setup_wizard_again')), message:@js(__('pelican-simplify::settings.page.you_walk_through_the_system_checks')), confirmLabel:@js(__('pelican-simplify::settings.page.start_setup'))}).then((ok) => ok && $wire.startSetupAgain())"><x-tabler-wand class="ps-ico" />{{ __('pelican-simplify::settings.page.setup_wizard') }}</button>
                    </div>
                @endif
            </header>
        @endif
        @include('pelican-simplify::partials-conflict-notice')
        @php($update = $this->needsSetup() ? null : $this->updateStatus())
        @if($update && $update['available'])
            <div class="ps-alert is-info ps-update-card" role="status">
                <x-tabler-sparkles class="ps-ico" aria-hidden="true" />
                <div><strong>{{ __('pelican-simplify::settings.page.update_available', ['version' => $update['version']]) }}</strong><small>{{ __('pelican-simplify::settings.page.update_running', ['version' => $update['current']]) }}</small></div>
                @if($update['url'])<a class="ps-btn is-sm" href="{{ $update['url'] }}" target="_top">{{ __('pelican-simplify::settings.page.open_plugins') }}</a>@endif
            </div>
        @endif
        @if($rerunSetup)
            <div class="ps-alert is-info ps-rerun-banner" role="status"><x-tabler-wand class="ps-ico" aria-hidden="true" /><div><strong>{{ __('pelican-simplify::settings.page.running_the_setup_wizard_again') }}</strong><small>{{ __('pelican-simplify::settings.page.your_current_settings_stay_active_until') }}</small></div><button type="button" class="ps-btn is-sm" wire:click="cancelSetupAgain">{{ __('pelican-simplify::settings.page.cancel') }}</button></div>
        @endif
        @if($this->needsSetup() && !$setupStarted)
            @include('pelican-simplify::system-checks')
        @endif
        @if(!$this->needsSetup() || $setupStarted)
            @if($this->needsSetup())
                <header class="ps-setup-heading ps-setup-welcome"><span class="ps-setup-mark"><x-tabler-adjustments /></span><div><span class="ps-setup-eyebrow">{{ __('pelican-simplify::settings.page.first_setup_preferences') }}</span><h2>{{ __('pelican-simplify::settings.page.make_simplify_yours') }}</h2><p>{{ __('pelican-simplify::settings.page.a_few_short_steps_what_simplify') }}</p></div></header>
            @endif
            <div class="ssc-wizard ps-settings-form" @if($this->needsSetup()) x-data="simplifySetupProgress()" @endif>
                @if($this->needsSetup())
                    {{-- Re-rendered by Livewire: an add-on switched on in the first step adds its own step here. --}}
                    <nav class="ssc-progress" style="--ssc-steps: {{ count($this->setupSteps()) }}" :style="{'--ssc-step': step}" aria-label="{{ __('pelican-simplify::settings.page.simplify_setup_progress') }}">
                        @foreach($this->setupSteps() as $index => $label)
                            <button type="button" :class="{'is-current':step==={{ $index+1 }},'is-complete':furthestStep>{{ $index+1 }}}" :disabled="{{ $index+1 }}>furthestStep" :aria-current="step==={{ $index+1 }}?'step':null" x-on:click="goToStep({{ $index+1 }})"><span>{{ $index+1 }}</span>{{ $label }}</button>
                            @unless($loop->last)<span class="ssc-step-arrow" aria-hidden="true">&gt;</span>@endunless
                        @endforeach
                        <span class="ssc-progress-count" x-text="Simplify.fill(@js(__('pelican-simplify::settings.page.step_of')), { step, total: {{ count($this->setupSteps()) }} })"></span>
                    </nav>
                @endif
                <form wire:submit="save">
                    {{ $this->form }}
                    @if(!$this->needsSetup())
                        {{-- Shared save bar (core.js): appears on changes, confirms the save, then slides away. --}}
                        <div wire:ignore x-data x-init="Simplify.saveBar({ mount: document.body, fixed: true, scope: $el.closest('form'), label: @js(__('pelican-simplify::settings.page.save_settings')), onSave: (api) => { api.expectSave(); $wire.save(); } })"></div>
                    @endif
                </form>
            </div>
        @endif
        {{-- System checks live on the legacy settings page only, not in the panel popup. --}}
        @if(!$this->needsSetup() && !$this->embedded)
            @if($showDiagnostics)
                @include('pelican-simplify::system-checks')
            @endif
        @endif
    </div>
</x-filament-panels::page>

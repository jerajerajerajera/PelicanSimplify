<x-filament-panels::page>
    @assets
    <link rel="stylesheet" href="{{ \Simplify\Panel\Services\BuildInfo::asset('wizard.css') }}">
    <script src="{{ \Simplify\Panel\Services\BuildInfo::asset('wizard.js') }}"></script>
    @endassets
    <div class="ps-settings-page {{ $this->embedded ? 'is-embedded' : 'is-standalone' }}" x-data x-init="
        const fit = () => { const tabs = $el.querySelector('.ps-settings-tabs.fi-vertical'); if (!tabs) return; const top = tabs.getBoundingClientRect().top + window.scrollY; tabs.style.setProperty('--ps-settings-height', Math.max(420, window.innerHeight - top - 16) + 'px'); };
        fit(); window.addEventListener('resize', fit); new MutationObserver(() => requestAnimationFrame(fit)).observe($el, { childList: true, subtree: true });
    ">
        @if(!$this->needsSetup())
            <header class="ps-settings-hero">
                <span class="ps-settings-logo">@include('pelican-simplify::simplify-icon')</span>
                <div class="ps-settings-hero-text">
                    <h1>Simplify settings</h1>
                    <p>Version {{ \Simplify\Panel\Services\BuildInfo::version() ?? 'unknown' }} · Changes apply to everyone on this panel after saving.</p>
                </div>
                @if(!$this->embedded && !$showDiagnostics)
                    <div class="ps-settings-hero-actions">
                        <button type="button" class="ps-btn is-sm" wire:click="toggleDiagnostics" wire:loading.attr="disabled"><x-tabler-shield-check class="ps-ico" />System checks</button>
                        <button type="button" class="ps-btn is-sm" wire:loading.attr="disabled" x-on:click="Simplify.confirm({title:'Run the setup wizard again?', message:'You walk through the system checks and every settings page again, starting from your current settings. Nothing changes until you finish the wizard.', confirmLabel:'Start setup'}).then((ok) => ok && $wire.startSetupAgain())"><x-tabler-wand class="ps-ico" />Setup wizard</button>
                    </div>
                @endif
            </header>
        @endif
        @include('pelican-simplify::partials-conflict-notice')
        @php($update = $this->needsSetup() ? null : $this->updateStatus())
        @if($update && $update['available'])
            <div class="ps-alert is-info ps-update-card" role="status">
                <x-tabler-sparkles class="ps-ico" aria-hidden="true" />
                <div><strong>Simplify {{ $update['version'] }} is available</strong><small>You are running {{ $update['current'] }}. Install the update from Pelican’s plugin page; your settings stay in place.</small></div>
                @if($update['url'])<a class="ps-btn is-sm" href="{{ $update['url'] }}" target="_top">Open plugins</a>@endif
            </div>
        @endif
        @if($rerunSetup)
            <div class="ps-alert is-info ps-rerun-banner" role="status"><x-tabler-wand class="ps-ico" aria-hidden="true" /><div><strong>Running the setup wizard again</strong><small>Your current settings stay active until you finish.</small></div><button type="button" class="ps-btn is-sm" wire:click="cancelSetupAgain">Cancel</button></div>
        @endif
        @if($this->needsSetup() && !$setupStarted)
            @include('pelican-simplify::system-checks')
        @endif
        @if(!$this->needsSetup() || $setupStarted)
            @if($this->needsSetup())
                <header class="ps-setup-heading ps-setup-welcome"><span class="ps-setup-mark"><x-tabler-adjustments /></span><div><span class="ps-setup-eyebrow">First setup · Preferences</span><h2>Make Simplify yours</h2><p>Choose your features, access rules and resource limits. You can change these later.</p></div></header>
            @endif
            <div class="ssc-wizard ps-settings-form" @if($this->needsSetup()) x-data="simplifySetupProgress()" @endif>
                @if($this->needsSetup())
                    <nav class="ssc-progress" wire:ignore style="--ssc-steps: {{ count($this->setupSteps()) }}" :style="{'--ssc-step': step}" aria-label="Simplify setup progress">
                        @foreach($this->setupSteps() as $index => $label)
                            <button type="button" :class="{'is-current':step==={{ $index+1 }},'is-complete':furthestStep>{{ $index+1 }}}" :disabled="{{ $index+1 }}>furthestStep" :aria-current="step==={{ $index+1 }}?'step':null" x-on:click="goToStep({{ $index+1 }})"><span>{{ $index+1 }}</span>{{ $label }}</button>
                            @unless($loop->last)<span class="ssc-step-arrow" aria-hidden="true">&gt;</span>@endunless
                        @endforeach
                        <span class="ssc-progress-count" x-text="'Step '+step+' of {{ count($this->setupSteps()) }}'"></span>
                    </nav>
                @endif
                <form wire:submit="save">
                    {{ $this->form }}
                    @if(!$this->needsSetup())
                        {{-- Shared save bar (core.js): appears on changes, confirms the save, then slides away. --}}
                        <div wire:ignore x-data x-init="Simplify.saveBar({ mount: document.body, fixed: true, scope: $el.closest('form'), label: 'Save settings', onSave: (api) => { api.expectSave(); $wire.save(); } })"></div>
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

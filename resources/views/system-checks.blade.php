            <section class="ps-setup-checks {{ $this->needsSetup() ? '' : 'ps-diagnostics-panel' }}" @if($this->needsSetup() && !$setupStarted && !$requirements) wire:init="refreshRequirements" @endif>
                <header class="ps-setup-heading">
                    @if(!$this->needsSetup())<button type="button" class="ps-btn is-icon is-subtle ps-diagnostics-close" wire:click="toggleDiagnostics" aria-label="{{ __('pelican-simplify::checks.system.close_system_checks') }}"><x-tabler-x class="ps-ico" /></button>@endif
                    <span class="ps-setup-mark"><x-tabler-shield-check /></span>
                    <div><span class="ps-setup-eyebrow">{{ $this->needsSetup() ? __('pelican-simplify::checks.system.first_setup_eyebrow') : __('pelican-simplify::checks.system.diagnostics') }}</span><h2>{{ __('pelican-simplify::checks.system.let_s_get_simplify_ready') }}</h2><p>{{ __('pelican-simplify::checks.system.a_quick_check_of_your_panel') }}</p></div>
                </header>
                <div class="ps-check-loading" wire:loading.flex wire:target="refreshRequirements,beginSetup"><x-tabler-loader-2 class="ps-check-spinner" /><span>{{ __('pelican-simplify::checks.system.checking_your_panel') }}</span></div>
                <div wire:loading.remove wire:target="refreshRequirements,beginSetup" aria-live="polite">
                    @if($requirements)
                        @php($passed = count(array_filter($requirements, fn($check) => $check['ok'])))
                        <div class="ps-check-summary {{ $this->requirementsReady() ? 'is-ready' : 'is-blocked' }}">
                            @if($this->requirementsReady())<x-tabler-circle-check />@else<x-tabler-alert-circle />@endif
                            <div><strong>{{ $this->requirementsReady() ? __('pelican-simplify::checks.system.panel_ready') : __('pelican-simplify::checks.system.needs_attention_some') }}</strong><span>{{ __('pelican-simplify::checks.system.passed', ['passed' => $passed, 'total' => count($requirements)]) }}</span></div>
                        </div>
                        <div class="ps-check-grid">
                            @foreach($this->requirementGroups() as $group => $checks)
                                @php($failed = array_filter($checks, fn($check) => !$check['ok']))
                                <article class="ps-check-card {{ $failed ? (array_filter($failed, fn($check) => $check['blocking'] ?? true) ? 'is-blocked' : 'is-warning') : 'is-ready' }}">
                                    <div class="ps-check-card-heading">@if($failed)<x-tabler-alert-circle />@else<x-tabler-circle-check />@endif<h3>{{ $group }}</h3><span>{{ count($checks) - count($failed) }}/{{ count($checks) }}</span></div>
                                    @foreach($failed as $check)<div class="ps-check-fix"><strong>{{ $check['name'] }}</strong><p>{{ $check['fix'] }}</p>@if(!empty($check['detail']))<p>{{ $check['detail'] }}</p>@endif</div>@endforeach
                                    @foreach($checks as $check)@if($check['ok'] && !empty($check['detail']))<p class="ps-help">{{ $check['detail'] }}</p>@endif@endforeach
                                    <details><summary>{{ __('pelican-simplify::checks.system.view_checks') }}</summary><ul>@foreach($checks as $check)<li><span class="ps-check-dot {{ $check['ok'] ? 'is-ready' : 'is-blocked' }}"></span><span>{{ $check['name'] }}</span><small>{{ $check['ok'] ? __('pelican-simplify::checks.system.ready') : (($check['blocking'] ?? true) ? __('pelican-simplify::checks.system.needs_attention') : __('pelican-simplify::checks.system.warning_optional')) }}</small></li>@endforeach</ul></details>
                                </article>
                            @endforeach
                        </div>
                        <p class="ps-check-note"><x-tabler-info-circle /><span>{{ __('pelican-simplify::checks.system.keep_your_queue_worker_and_scheduler') }}</span></p>
                        <footer class="ps-check-actions">
                            <x-filament::button type="button" color="gray" wire:click="refreshRequirements" wire:loading.attr="disabled">{{ __('pelican-simplify::checks.system.check_again') }}</x-filament::button>
                            @if($this->needsSetup() && !$setupStarted)<x-filament::button type="button" wire:click="beginSetup" wire:loading.attr="disabled" :disabled="!$this->requirementsReady()" icon="tabler-arrow-right" icon-position="after">{{ __('pelican-simplify::checks.system.go_to_first_setup') }}</x-filament::button>@endif
                        </footer>
                    @else
                        <p class="ps-check-placeholder">{{ __('pelican-simplify::checks.system.preparing_your_system_checks') }}</p>
                    @endif
                </div>
            </section>

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    @php
        $path = $getStatePath();
        $searchPath = preg_replace('/modpack_project$/', 'modpack_search', $path);
        $packs = collect(\Simplify\Panel\Services\ModpackCatalog::results($get('modpack_search'), $getState()))->keyBy('key');
    @endphp
    <div class="ssc-pack-search">
        <x-filament::input.wrapper>
            <x-filament::input type="search" placeholder="{{ __('pelican-simplify::maker.modpack.search_modpacks') }}" aria-label="{{ __('pelican-simplify::maker.modpack.search_modpacks_2') }}"
                wire:model.live.debounce.800ms="{{ $searchPath }}" />
        </x-filament::input.wrapper>
        <p class="ssc-search-help">{{ trim((string) $get('modpack_search')) === '' ? __('pelican-simplify::maker.modpack.popular_picks') : __('pelican-simplify::maker.modpack.choose_from_results') }}</p>
        <p wire:loading wire:target="{{ $searchPath }}" class="ssc-search-help" role="status">{{ __('pelican-simplify::maker.modpack.searching') }}</p>
    </div>
    <div class="ssc-cards ssc-featured-options" role="radiogroup" aria-label="{{ __('pelican-simplify::maker.modpack.choose_a_modpack') }}">
        @foreach ($getOptions() as $value => $label)
            @php($pack = $packs->get((string) $value))
            @if ($pack)
                <label class="ssc-card" wire:key="ssc-pack-{{ $value }}">
                    <input class="ssc-card-input" type="radio" name="{{ $path }}" value="{{ $value }}"
                        {{ $applyStateBindingModifiers('wire:model') }}="{{ $path }}"
                        @checked((string) $getState() === (string) $value) @disabled($isDisabled()) />
                    <span class="ssc-card-content">
                        <span class="ssc-pack-image" x-data="{ failed: false }">
                            @if ($pack['icon_url'])
                                <img src="{{ $pack['icon_url'] }}" alt="" width="64" height="64" loading="lazy" referrerpolicy="no-referrer" x-show="!failed" x-on:error="failed = true" />
                            @endif
                            <span @if($pack['icon_url']) x-show="failed" style="display:none" @endif>
                                @include('pelican-simplify::filament.admin.outline-icon', ['icon' => 'modded'])
                            </span>
                        </span>
                        <span class="ps-badge">{{ $pack['source'] }}</span>
                        <span class="ssc-card-title">{{ $label }}</span>
                        <span class="ssc-card-description">{{ $pack['description'] }}</span>
                        @if ($pack['url'])
                            <a class="ssc-card-link" href="{{ $pack['url'] }}" target="_blank" rel="noopener noreferrer" x-on:click.stop
                               aria-label="{{ __('pelican-simplify::maker.modpack.read_more_label', ['name' => $label, 'site' => $pack['source']]) }}">{{ __('pelican-simplify::maker.modpack.read_more') }}</a>
                        @endif
                    </span>
                </label>
            @endif
        @endforeach
    </div>
    @if (count($getOptions()) === 0)
        <p class="ssc-search-help">{{ __('pelican-simplify::maker.modpack.no_packs_found_try_another_name') }}</p>
    @endif
    @if ($getState() && !\Simplify\Panel\Services\ModpackCatalog::egg($getState()))
        <p class="ssc-summary-notice">{{ __('pelican-simplify::maker.modpack.the_matching_egg_will_be_imported') }}</p>
    @endif
</x-dynamic-component>

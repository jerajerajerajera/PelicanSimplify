@php $addonsOnly = \Simplify\Panel\Services\FeatureSettings::addonsOnly(); @endphp
@if(\Simplify\Panel\Services\LegacyNavigation::topbarVisible() || ($addonsOnly && user()))
    {{-- Pelican's server list: "Create server" asks which creator to use, also with only the add-ons. --}}
    @if(request()->routeIs(\Simplify\Panel\Services\LegacyNavigation::serverListRoute()))
        @php
            $nativeCreate = \App\Filament\Admin\Resources\Servers\ServerResource::canCreate();
            $simplifyCreate = \Simplify\Panel\Services\FeatureSettings::enabled('easy_creator') && \Simplify\Panel\Services\CreatorAccess::canCreate();
        @endphp
        @if($nativeCreate || $simplifyCreate)
            <x-filament::dropdown placement="bottom-end" width="xs">
                <x-slot name="trigger"><x-filament::icon-button icon="tabler-plus" color="gray" label="{{ __('pelican-simplify::settings.legacy.create_server') }}" /></x-slot>
                <x-filament::dropdown.list>
                    @if($nativeCreate)<x-filament::dropdown.list.item tag="a" icon="tabler-server" :href="\App\Filament\Admin\Resources\Servers\ServerResource::getUrl('create', panel: 'admin')">{{ __('pelican-simplify::settings.legacy.legacy_creator') }}</x-filament::dropdown.list.item>@endif
                    @if($simplifyCreate)<x-filament::dropdown.list.item tag="a" icon="tabler-layout-dashboard" :href="\Simplify\Panel\Filament\App\Pages\CreateServer::getUrl(panel: 'app')"><span class="ps-creator-choice">{{ __('pelican-simplify::settings.legacy.simplify_creator') }} <x-tabler-sparkles aria-hidden="true" /></span></x-filament::dropdown.list.item>@endif
                </x-filament::dropdown.list>
            </x-filament::dropdown>
        @endif
    @endif
    @unless($addonsOnly)
    <a href="{{ \Simplify\Panel\Services\LegacyNavigation::url() }}" class="ps-btn ps-legacy-return" aria-label="{{ __('pelican-simplify::settings.legacy.go_to_simplify_panel') }}">
        <x-tabler-layout-dashboard class="ps-ico" aria-hidden="true" />
        <span>{{ __('pelican-simplify::settings.legacy.go_to_simplify_panel') }}</span>
        <x-tabler-sparkles class="ps-ico ps-simplify-sparkles" aria-hidden="true" />
    </a>
    @endunless
@endif

@if(\Simplify\Panel\Services\LegacyNavigation::topbarVisible())
    @if(request()->routeIs(\Simplify\Panel\Services\LegacyNavigation::serverListRoute()))
        @php
            $nativeCreate = \App\Filament\Admin\Resources\Servers\ServerResource::canCreate();
            $simplifyCreate = \Simplify\Panel\Services\FeatureSettings::enabled('easy_creator') && \Simplify\Panel\Services\CreatorAccess::canCreate();
        @endphp
        @if($nativeCreate || $simplifyCreate)
            <x-filament::dropdown placement="bottom-end" width="xs">
                <x-slot name="trigger"><x-filament::icon-button icon="tabler-plus" color="gray" label="Create server" /></x-slot>
                <x-filament::dropdown.list>
                    @if($nativeCreate)<x-filament::dropdown.list.item tag="a" icon="tabler-server" :href="\App\Filament\Admin\Resources\Servers\ServerResource::getUrl('create', panel: 'admin')">Legacy creator</x-filament::dropdown.list.item>@endif
                    @if($simplifyCreate)<x-filament::dropdown.list.item tag="a" icon="tabler-layout-dashboard" :href="\Simplify\Panel\Filament\App\Pages\CreateServer::getUrl(panel: 'app')"><span class="ps-creator-choice">Simplify creator <x-tabler-sparkles aria-hidden="true" /></span></x-filament::dropdown.list.item>@endif
                </x-filament::dropdown.list>
            </x-filament::dropdown>
        @endif
    @endif
    <a href="{{ \Simplify\Panel\Services\LegacyNavigation::url() }}" class="ps-btn ps-legacy-return" aria-label="Go to Simplify Panel">
        <x-tabler-layout-dashboard class="ps-ico" aria-hidden="true" />
        <span>Go to Simplify Panel</span>
        <x-tabler-sparkles class="ps-ico ps-simplify-sparkles" aria-hidden="true" />
    </a>
@endif

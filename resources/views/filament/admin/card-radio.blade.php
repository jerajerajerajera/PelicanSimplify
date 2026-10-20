<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    @php
        $path = $getStatePath();
        $name = $field->getName();
        $resource = in_array($name, ['ram_gb','cpu_choice','disk_choice']);
        $packs = collect();
    @endphp
    <div {{ $getExtraAttributeBag()->class(['ssc-cards', 'ssc-cards--ram' => in_array($name, ['ram_gb','cpu_choice','disk_choice'])]) }}
         role="radiogroup" aria-label="{{ $getLabel() }}">
        @foreach ($getOptions() as $value => $label)
            @php
                $pack = $packs->get((string) $value);
                $description = $field->getDescription((string) $value);
                if ($resource && !$description) $description = $value === 'custom' ? 'Choose your own' : ($name === 'cpu_choice' ? ($value === 0 || (string)$value === '0' ? 'Unlimited CPU' : round((float)$value / 100, 2).' CPU cores') : round((float)$value / 1024, 2).' GB storage');
            @endphp
            <label class="ssc-card" wire:key="ssc-{{ $path }}-{{ $value }}">
                <input class="ssc-card-input" type="radio" name="{{ $path }}" value="{{ $value }}"
                       {{ $applyStateBindingModifiers('wire:model') }}="{{ $path }}"
                       @checked((string) $getState() === (string) $value)
                       @disabled($isDisabled()) />
                <span class="ssc-card-content">
                    @if ($name === 'game')
                        @include('pelican-simplify::filament.admin.outline-icon', ['icon' => $value === 'minecraft' ? 'vanilla' : 'game'])
                    @elseif ($name === 'server_type')
                        @include('pelican-simplify::filament.admin.outline-icon', ['icon' => $value])
                    @elseif ($name === 'backup_frequency')
                        @include('pelican-simplify::filament.admin.outline-icon', ['icon' => 'clock'])
                    @elseif (in_array($name, ['cpu_choice','disk_choice']))
                        @include('pelican-simplify::filament.admin.outline-icon', ['icon' => $name === 'cpu_choice' ? 'cpu' : 'disk'])
                    @elseif ($name === 'icon_source' && $value === 'modpack' && ($modpackIcon = $field->getLivewire()->modpackIcon()))
                        <span class="ssc-pack-image ssc-server-icon-preview">
                            <img src="data:image/png;base64,{{ $modpackIcon }}" alt="" width="64" height="64" />
                        </span>
                    @elseif ($name === 'icon_source')
                        @include('pelican-simplify::filament.admin.outline-icon', ['icon' => 'upload'])
                    @elseif ($name === 'ram_gb')
                        @include('pelican-simplify::filament.admin.outline-icon', ['icon' => 'ram'])
                    @elseif ($pack && !empty($pack['icon_url']))
                        <span class="ssc-pack-image" x-data="{ failed: false }">
                            <img src="{{ $pack['icon_url'] }}" alt="" width="64" height="64" loading="lazy" referrerpolicy="no-referrer"
                                 x-show="!failed" x-on:error="failed = true" />
                            <span x-show="failed" style="display:none">
                                @include('pelican-simplify::filament.admin.outline-icon', ['icon' => 'modded'])
                            </span>
                        </span>
                    @else
                        @include('pelican-simplify::filament.admin.outline-icon', ['icon' => 'modded'])
                    @endif
                    <span class="ssc-card-title">{{ $label }}</span>
                    @if ($description)
                        <span class="ssc-card-description">{{ $description }}</span>
                    @endif
                    @if ($pack)
                        <a class="ssc-card-link" href="https://modrinth.com/modpack/{{ rawurlencode($pack['slug']) }}"
                           target="_blank" rel="noopener noreferrer" x-on:click.stop
                           aria-label="Read more about {{ $label }} on Modrinth (opens in a new tab)">Read more ↗</a>
                    @endif
                </span>
            </label>
        @endforeach
    </div>
</x-dynamic-component>

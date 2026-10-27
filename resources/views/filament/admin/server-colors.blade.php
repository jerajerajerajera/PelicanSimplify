<div class="ssc-accent-field" wire:key="ssc-server-accent" wire:ignore x-data="{color:$wire.entangle('data.server_color')}">
    <span class="ssc-accent-label" id="ssc-accent-label">{{ __('pelican-simplify::settings.appearance.server_accent_color') }}</span>
    <div class="ps-swatches" role="group" aria-labelledby="ssc-accent-label">
        @foreach ($this->availableServerColors() as $choice => $name)
            <button type="button" class="ps-swatch" style="background:{{ $choice }}" data-tip="{{ $name }}" aria-label="{{ __('pelican-simplify::settings.appearance.select_server_color', ['name' => $name]) }}" :aria-pressed="color==='{{ $choice }}'" x-on:click="color='{{ $choice }}';$dispatch('change')"></button>
        @endforeach
        <label class="ps-btn is-sm"><span class="ps-swatch" style="width:16px;height:16px" :style="{'background-color':color}"></span>{{ __('pelican-simplify::settings.appearance.custom') }}<input x-ref="customColor" type="color" x-model="color" aria-label="{{ __('pelican-simplify::settings.appearance.custom_server_accent_color') }}" class="ssc-accent-native"></label>
    </div>
</div>

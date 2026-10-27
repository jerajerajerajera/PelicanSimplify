@php($info = $this->brandInfo())
<div class="ps-brand-editor" x-data="{ info: @js($info), get b() { return $wire.data?.appearance?.brand || {} }, get accent() { return $wire.data?.appearance?.accent_color } }">
    <div class="ps-brand-preview" aria-label="{{ __('pelican-simplify::settings.appearance.preview') }}">
        <span class="ps-help">{{ __('pelican-simplify::settings.appearance.preview') }}</span>
        <div class="ps-brand-stage">
            <template x-if="b.mode === 'logo' && info.logo_url">
                <img :src="info.logo_url" alt="" :style="{ height: (Number(b.logo_height) || 32) + 'px' }">
            </template>
            <span class="ps-brand-name" x-show="b.mode !== 'logo' || !info.logo_url || b.logo_text" :style="Simplify.brandTextStyle(b, info.fonts[b.font], accent)" x-text="(b.text || '').trim() || info.panel_name"></span>
            <span class="ps-brand-suffix" x-show="b.suffix">{{ __('pelican-simplify::settings.appearance.pelican') }}</span>
        </div>
    </div>
    <div class="ps-brand-upload">
        @if($info['logo_url'])
            <img src="{{ $info['logo_url'] }}" alt="{{ __('pelican-simplify::settings.appearance.current_logo') }}">
            <button type="button" class="ps-btn is-sm is-subtle is-danger" wire:click="removeLogo" wire:confirm="Remove the logo?"><x-tabler-trash class="ps-ico" />{{ __('pelican-simplify::settings.appearance.remove_logo') }}</button>
        @endif
        <label class="ps-btn is-sm"><x-tabler-upload class="ps-ico" />{{ $info['logo_url'] ? 'Replace logo' : 'Upload logo' }}
            <input type="file" class="ps-visually-hidden" accept="image/png,image/jpeg,image/webp" wire:model="logoUpload" x-on:livewire-upload-finish="$wire.uploadLogo()">
        </label>
        <span class="ps-help" wire:loading wire:target="logoUpload">{{ __('pelican-simplify::settings.appearance.uploading') }}</span>
        <span class="ps-help">PNG, JPEG or WebP, up to {{ \Simplify\Panel\Services\BrandSettings::LOGO_MAX_KB }} KB. A transparent background looks best.</span>
        @error('logoUpload')<span class="ps-error">{{ $message }}</span>@enderror
    </div>
</div>

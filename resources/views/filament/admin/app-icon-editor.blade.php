@php($current = \Simplify\Panel\Services\AppIcon::current())
@php($name = \Simplify\Panel\Services\WebApp::name())
<div class="ps-app-icon-editor">
    <div class="ps-brand-preview" aria-label="{{ __('pelican-simplify::settings.appearance.preview') }}">
        <span class="ps-help">{{ __('pelican-simplify::settings.appearance.preview') }}</span>
        <div class="ps-brand-stage ps-app-icon-stage">
            <figure class="ps-app-icon-home">
                <img src="{{ \Simplify\Panel\Services\WebApp::icon('maskable') }}" alt="">
                <figcaption>{{ $name }}</figcaption>
            </figure>
            <div class="ps-app-icon-note" role="img" aria-label="{{ __('pelican-simplify::settings.app_icon.notification_preview') }}">
                <div class="ps-app-icon-note-head"><img src="{{ \Simplify\Panel\Services\WebApp::badge() }}" alt="">{{ $name }}</div>
                <div class="ps-app-icon-note-text"><strong>{{ __('pelican-simplify::notifications.push.test_notification') }}</strong><span>{{ __('pelican-simplify::notifications.push.notifications_work_on_this_device') }}</span></div>
                <img class="ps-app-icon-note-icon" src="{{ \Simplify\Panel\Services\WebApp::icon(192) }}" alt="">
            </div>
        </div>
    </div>
    <div class="ps-app-icon-row">
        <strong>{{ __('pelican-simplify::settings.app_icon.icon') }}</strong>
        <div class="ps-brand-upload">
            @if($current['icon'])
                <button type="button" class="ps-btn is-sm is-subtle is-danger" wire:click="removeAppIcon('icon')" wire:confirm="{{ __('pelican-simplify::settings.app_icon.remove_icon_confirm') }}"><x-tabler-trash class="ps-ico" />{{ __('pelican-simplify::settings.app_icon.remove') }}</button>
            @endif
            <label class="ps-btn is-sm"><x-tabler-upload class="ps-ico" />{{ $current['icon'] ? __('pelican-simplify::settings.app_icon.replace') : __('pelican-simplify::settings.app_icon.upload') }}
                <input type="file" class="ps-visually-hidden" accept="image/png,image/jpeg,image/webp" wire:model="appIconUpload" x-on:livewire-upload-finish="$wire.uploadAppIcon()">
            </label>
            <span class="ps-help" wire:loading wire:target="appIconUpload">{{ __('pelican-simplify::settings.appearance.uploading') }}</span>
        </div>
        <span class="ps-help">{{ __('pelican-simplify::settings.app_icon.icon_help', ['min' => \Simplify\Panel\Services\AppIcon::MIN_ICON, 'size' => \Simplify\Panel\Services\AppIcon::MAX_KB / 1024]) }}</span>
        @error('appIconUpload')<span class="ps-error">{{ $message }}</span>@enderror
    </div>
    <div class="ps-app-icon-row">
        <strong>{{ __('pelican-simplify::settings.app_icon.badge') }}</strong>
        <div class="ps-brand-upload">
            @if($current['badge'] && !$current['badge_from_icon'])
                <button type="button" class="ps-btn is-sm is-subtle is-danger" wire:click="removeAppIcon('badge')" wire:confirm="{{ __('pelican-simplify::settings.app_icon.remove_badge_confirm') }}"><x-tabler-trash class="ps-ico" />{{ __('pelican-simplify::settings.app_icon.remove') }}</button>
            @endif
            <label class="ps-btn is-sm"><x-tabler-upload class="ps-ico" />{{ $current['badge'] && !$current['badge_from_icon'] ? __('pelican-simplify::settings.app_icon.replace') : __('pelican-simplify::settings.app_icon.upload') }}
                <input type="file" class="ps-visually-hidden" accept="image/png,image/webp" wire:model="appBadgeUpload" x-on:livewire-upload-finish="$wire.uploadAppBadge()">
            </label>
            <span class="ps-help" wire:loading wire:target="appBadgeUpload">{{ __('pelican-simplify::settings.appearance.uploading') }}</span>
        </div>
        <span class="ps-help">{{ $current['badge_from_icon'] ? __('pelican-simplify::settings.app_icon.badge_from_icon') : __('pelican-simplify::settings.app_icon.badge_help', ['min' => \Simplify\Panel\Services\AppIcon::MIN_BADGE]) }}</span>
        @error('appBadgeUpload')<span class="ps-error">{{ $message }}</span>@enderror
    </div>
    <span class="ps-help">{{ __('pelican-simplify::settings.app_icon.reinstall_note') }}</span>
</div>

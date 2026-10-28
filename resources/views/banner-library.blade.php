<section class="ps-banner-library">
    <header><h2>{{ __('pelican-simplify::dashboard.banners.server_banners') }}</h2><p>{{ __('pelican-simplify::dashboard.banners.upload_and_manage_server_banners_here') }}</p></header>
    <div class="ps-banner-upload">
        <label class="ps-field">{{ __('pelican-simplify::dashboard.banners.server') }}<select wire:model="bannerServer"><option value="">{{ __('pelican-simplify::dashboard.banners.choose_a_server') }}</option>@foreach($this->bannerServers() as $uuid => $name)<option value="{{ $uuid }}">{{ $name }}</option>@endforeach</select></label>
        @error('bannerServer')<p class="ps-alert is-danger" role="alert">{{ $message }}</p>@enderror
        <label class="ps-field">{{ __('pelican-simplify::dashboard.banners.banner') }}<input type="file" accept="image/png,image/jpeg,image/webp" wire:model="bannerUpload"></label>
        @error('bannerUpload')<p class="ps-alert is-danger" role="alert">{{ $message }}</p>@enderror
        <small class="ps-help">{{ __('pelican-simplify::dashboard.banners.upload_help', ['limit' => \Simplify\Panel\Services\UploadLimits::label(\Simplify\Panel\Services\UploadLimits::bannerBytes())]) }}</small>
        <label class="ps-check"><input type="checkbox" role="switch" wire:model="useUploadedBanner"> {{ __('pelican-simplify::dashboard.banners.use_this_banner_on_the_selected') }}</label>
        <button type="button" class="ps-btn is-primary" wire:click="uploadBanner" wire:loading.attr="disabled" wire:target="bannerUpload,uploadBanner"><span wire:loading.remove wire:target="bannerUpload,uploadBanner">{{ __('pelican-simplify::dashboard.banners.upload_banner') }}</span><span wire:loading wire:target="bannerUpload,uploadBanner">{{ __('pelican-simplify::dashboard.banners.uploading') }}</span></button>
    </div>
    {{-- The pictures only load once this tab is on screen (every tab is in the page from the start, so loading="lazy" alone is not enough). --}}
    <div x-data="{ shown: false }" x-init="const io = new IntersectionObserver((entries) => { if (entries.some((e) => e.isIntersecting)) { shown = true; io.disconnect(); } }); io.observe($el)">
    <h3>{{ __('pelican-simplify::dashboard.banners.standard_banners') }}</h3>
    <div class="ps-banner-library-grid">
        @foreach(\Simplify\Panel\Services\ServerArtwork::BACKGROUNDS as $id => $file)
            <article><img :src="shown ? @js(\Simplify\Panel\Services\ServerArtwork::backgroundUrl($id)) : null" alt="{{ ucfirst($id) }}"><div><strong>{{ ucfirst($id) }}</strong><small>{{ __('pelican-simplify::dashboard.banners.included_always_available') }}</small></div></article>
        @endforeach
    </div>
    <h3>{{ __('pelican-simplify::dashboard.banners.uploaded_banners') }}</h3>
    <div class="ps-banner-library-grid">
        @php($library = $this->bannerLibrary())
        @php($serverNames = \App\Models\Server::query()->whereIn('uuid', array_column($library, 'server_uuid'))->pluck('name', 'uuid')->all())
        @forelse($library as $banner)
            <article><img :src="shown ? @js($banner['url']) : null" alt="{{ $banner['name'] }}"><div><strong>{{ $banner['name'] }}</strong><small>{{ $serverNames[$banner['server_uuid']] ?? __('pelican-simplify::dashboard.banners.deleted_server') }}</small><button type="button" class="ps-btn is-sm is-danger" x-on:click="Simplify.confirm({title:@js(__('pelican-simplify::dashboard.banners.remove_this_uploaded_banner')),message:@js(__('pelican-simplify::dashboard.banners.servers_using_it_return_to_their')),confirmLabel:@js(__('pelican-simplify::dashboard.banners.remove_banner')),tone:'danger'}).then(ok => ok && $wire.deleteBanner(@js($banner['id'])))" wire:loading.attr="disabled">{{ __('pelican-simplify::dashboard.banners.remove_upload') }}</button></div></article>
        @empty
            <p class="ps-banner-empty">{{ __('pelican-simplify::dashboard.banners.no_uploaded_banners_yet') }}</p>
        @endforelse
    </div>
    </div>
</section>

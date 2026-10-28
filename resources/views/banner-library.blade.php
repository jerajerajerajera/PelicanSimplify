<section class="ps-banner-library">
    <header><h2>Server banners</h2><p>Upload and manage server banners here. The standard collection always stays available.</p></header>
    <div class="ps-banner-upload">
        <label class="ps-field">Server<select wire:model="bannerServer"><option value="">Choose a server</option>@foreach($this->bannerServers() as $uuid => $name)<option value="{{ $uuid }}">{{ $name }}</option>@endforeach</select></label>
        @error('bannerServer')<p class="ps-alert is-danger" role="alert">{{ $message }}</p>@enderror
        <label class="ps-field">Banner<input type="file" accept="image/png,image/jpeg,image/webp" wire:model="bannerUpload"></label>
        @error('bannerUpload')<p class="ps-alert is-danger" role="alert">{{ $message }}</p>@enderror
        <small class="ps-help">JPG, PNG or WebP · up to {{ \Simplify\Panel\Services\UploadLimits::label(\Simplify\Panel\Services\UploadLimits::bannerBytes()) }}</small>
        <label class="ps-check"><input type="checkbox" role="switch" wire:model="useUploadedBanner"> Use this banner on the selected server</label>
        <button type="button" class="ps-btn is-primary" wire:click="uploadBanner" wire:loading.attr="disabled" wire:target="bannerUpload,uploadBanner"><span wire:loading.remove wire:target="bannerUpload,uploadBanner">Upload banner</span><span wire:loading wire:target="bannerUpload,uploadBanner">Uploading…</span></button>
    </div>
    {{-- The pictures only load once this tab is on screen (every tab is in the page from the start, so loading="lazy" alone is not enough). --}}
    <div x-data="{ shown: false }" x-init="const io = new IntersectionObserver((entries) => { if (entries.some((e) => e.isIntersecting)) { shown = true; io.disconnect(); } }); io.observe($el)">
    <h3>Standard banners</h3>
    <div class="ps-banner-library-grid">
        @foreach(\Simplify\Panel\Services\ServerArtwork::BACKGROUNDS as $id => $file)
            <article><img :src="shown ? @js(\Simplify\Panel\Services\ServerArtwork::backgroundUrl($id)) : null" alt="{{ ucfirst($id) }}"><div><strong>{{ ucfirst($id) }}</strong><small>Included · always available</small></div></article>
        @endforeach
    </div>
    <h3>Uploaded banners</h3>
    <div class="ps-banner-library-grid">
        @php($library = $this->bannerLibrary())
        @php($serverNames = \App\Models\Server::query()->whereIn('uuid', array_column($library, 'server_uuid'))->pluck('name', 'uuid')->all())
        @forelse($library as $banner)
            <article><img :src="shown ? @js($banner['url']) : null" alt="{{ $banner['name'] }}"><div><strong>{{ $banner['name'] }}</strong><small>{{ $serverNames[$banner['server_uuid']] ?? 'Deleted server' }}</small><button type="button" class="ps-btn is-sm is-danger" x-on:click="Simplify.confirm({title:'Remove this uploaded banner?',message:'Servers using it return to their automatic background.',confirmLabel:'Remove banner',tone:'danger'}).then(ok => ok && $wire.deleteBanner(@js($banner['id'])))" wire:loading.attr="disabled">Remove upload</button></div></article>
        @empty
            <p class="ps-banner-empty">No uploaded banners yet.</p>
        @endforelse
    </div>
    </div>
</section>

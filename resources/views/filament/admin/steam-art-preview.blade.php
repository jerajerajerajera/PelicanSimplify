{{-- Preview of the Steam artwork the creator will copy. Images load from Steam's CDN in the browser;
     if Steam cannot be reached the preview stays hidden (the copy after creation simply skips it). --}}
@if($art)
    <div class="ssc-steam-art" x-data="{ ready: false, failed: false }" x-show="ready && !failed" x-cloak>
        <img class="ssc-steam-banner" src="{{ $art['header'] }}" alt="{{ __('pelican-simplify::maker.game.steam_banner', ['name' => $art['name']]) }}" loading="lazy" referrerpolicy="no-referrer" x-on:load="ready = true" x-on:error="failed = true">
        <span class="ssc-steam-icon"><img src="{{ $art['capsule'] }}" alt="" loading="lazy" referrerpolicy="no-referrer" x-on:error="$el.src = @js($art['header'])"></span>
    </div>
@endif

{{-- One tab of Pelican's own settings page, shown inside Simplify settings. Pelican keeps its form and its save button. --}}
{{-- The frame only loads once its tab is opened (and stays loaded after that), so the settings page itself is ready at once. --}}
{{-- While a Pelican tab is open, Simplify's own save bar steps aside (body.ps-pelican-tab); `tab` comes from the tabs component. --}}
<div class="ps-pelican-frame" x-data="{ shown: false }" x-effect="const open = String(typeof tab === 'undefined' ? '' : tab); document.body.classList.toggle('ps-pelican-tab', open.startsWith('pelican-')); if (open.startsWith(@js($key))) shown = true">
    <p>{{ __('pelican-simplify::settings.page.pelican_frame', ['label' => $label]) }}</p>
    <iframe data-ps-frame="native" data-ps-tab="{{ $label }}" :data-ps-accent="getComputedStyle(document.documentElement).getPropertyValue('--ps-accent').trim()" :src="shown ? @js($url) : null" title="{{ __('pelican-simplify::settings.page.pelican_frame_title', ['label' => $label]) }}"></iframe>
</div>

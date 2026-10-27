{{-- One tab of Pelican's own settings page, shown inside Simplify settings. Pelican keeps its form and its save button. --}}
{{-- While a Pelican tab is open, Simplify's own save bar steps aside (body.ps-pelican-tab); `tab` comes from the tabs component. --}}
<div class="ps-pelican-frame" x-data x-effect="document.body.classList.toggle('ps-pelican-tab', String(typeof tab === 'undefined' ? '' : tab).startsWith('pelican-'))">
    <p>Pelican's own {{ $label }} settings. Save them with Pelican's save button at the top right of this panel.</p>
    <iframe data-ps-frame="native" data-ps-tab="{{ $label }}" :data-ps-accent="getComputedStyle(document.documentElement).getPropertyValue('--ps-accent').trim()" src="{{ $url }}" title="Pelican settings: {{ $label }}" loading="lazy"></iframe>
</div>

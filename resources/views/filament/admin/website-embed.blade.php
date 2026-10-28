{{-- Website embed add-on: the code to paste into another website, with copy buttons and a live preview of the saved list. --}}
@if ($waiting ?? false)
    <p class="ps-embed-wait">Pick what this code shows above. A group needs at least one of its servers ticked under "Servers on your website".</p>
@else
@php($snippet = \Simplify\Panel\Services\WebsiteEmbed::snippet(url('/'), $show ?? []))
@php($query = parse_url($snippet['page'], PHP_URL_QUERY))
<div class="ps-embed" x-data="{ copied: null, async take(key, text) { try { await navigator.clipboard.writeText(text); this.copied = key; } catch (e) { const range = document.createRange(); range.selectNodeContents(this.$refs[key]); const s = getSelection(); s.removeAllRanges(); s.addRange(range); this.copied = key + '-selected'; } setTimeout(() => this.copied = null, 1800); } }">
    @foreach (['script' => ['Script (recommended)', 'Grows with the number of servers and takes the website\'s font. Options go in data-* attributes, such as data-style="dark" or data-server="1a2b3c4d".'], 'iframe' => ['Iframe', 'For website builders that do not allow scripts. Options go in the address, such as ?style=dark.']] as $key => [$label, $help])
        <div class="ps-embed-code">
            <div class="ps-embed-head">
                <strong>{{ $label }}</strong>
                <button type="button" class="ps-embed-copy" x-on:click="take(@js($key), @js($snippet[$key]))" x-text="copied === @js($key) ? 'Copied' : (copied === @js($key.'-selected') ? 'Selected: press Ctrl+C' : 'Copy code')">Copy code</button>
            </div>
            <pre x-ref="{{ $key }}">{{ $snippet[$key] }}</pre>
            <p>{{ $help }}</p>
        </div>
    @endforeach
    <div class="ps-embed-code">
        <div class="ps-embed-head">
            <strong>Page of its own</strong>
            <button type="button" class="ps-embed-copy" x-on:click="take('page', @js($snippet['page']))" x-text="copied === 'page' ? 'Copied' : (copied === 'page-selected' ? 'Selected: press Ctrl+C' : 'Copy link')">Copy link</button>
        </div>
        <pre x-ref="page">{{ $snippet['page'] }}</pre>
        <p>A link to the same list as a page, for example a status page for one server. <a href="{{ $snippet['page'] }}" target="_blank" rel="noopener" class="ps-embed-open">Open</a></p>
    </div>
    @if (\Simplify\Panel\Services\WebsiteEmbed::enabled())
        {{-- The preview (a page, a script and a server list: three requests) only loads once this tab is on screen, so the settings page itself is ready at once. --}}
        <div class="ps-embed-preview" x-data="{ shown: false }" x-init="const io = new IntersectionObserver((entries) => { if (entries.some((e) => e.isIntersecting)) { shown = true; io.disconnect(); } }); io.observe($el)">
            <strong>Preview of the saved list</strong>
            <iframe :src="shown ? @js(url('/simplify/embed').'?'.str_replace('page=1', 'page=0', (string) $query)) : null" title="Preview of the website embed"></iframe>
        </div>
    @endif
</div>
@endif
<style>
    .ps-embed{display:grid;gap:16px}
    .ps-embed-wait{margin:0;font-size:14px;color:rgb(113 113 122)}
    .ps-embed-open{font-weight:600;text-decoration:underline}
    .ps-embed-code{display:grid;gap:6px}
    .ps-embed-head{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap}
    .ps-embed-code pre{margin:0;padding:10px 12px;border-radius:8px;background:#0b0b0e;color:#e4e4e7;font:12.5px/1.55 ui-monospace,Menlo,Consolas,monospace;white-space:pre-wrap;overflow-wrap:anywhere}
    .ps-embed-code p{margin:0;font-size:13px;color:rgb(113 113 122)}
    .ps-embed-copy{border:1px solid rgb(228 228 231);border-radius:8px;padding:5px 12px;font-size:13px;font-weight:600;background:transparent;cursor:pointer}
    .dark .ps-embed-copy{border-color:rgb(63 63 70)}
    .ps-embed-preview{display:grid;gap:8px}
    .ps-embed-preview iframe{width:100%;height:420px;border:1px dashed rgb(212 212 216);border-radius:10px;background:transparent}
</style>

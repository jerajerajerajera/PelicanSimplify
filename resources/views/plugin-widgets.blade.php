{{-- Only the widgets: Simplify shows this page as a tile and sizes it to .simplify-plugin-widgets. --}}
<div>
    <style>.fi-topbar, .fi-sidebar, .fi-header, .fi-footer, .fi-sidebar-close-overlay { display: none !important; } .fi-main-ctn, .fi-main { padding: 0 !important; max-width: none !important; } .fi-page, .fi-page > section { gap: 0 !important; padding: 0 !important; } html, body { background: transparent !important; }</style>
    <div class="simplify-plugin-widgets" style="display: grid; gap: 12px;">
        @foreach($this->visibleWidgets() as $widget)
            {{-- A widget that fails leaves out only itself, never the tile or the page. --}}
            {!! $this->renderWidget($widget) !!}
        @endforeach
    </div>
</div>

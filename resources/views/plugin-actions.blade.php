{{-- No page of its own: a transparent layer over Simplify that runs one plugin button and shows only its dialog.
     The dashboard reveals the layer while a dialog is open ("modal") and removes it when the button is done. --}}
<div x-data x-init="
    const report = (state) => window.parent !== window && window.parent.postMessage({ type: 'simplify:plugin-action', state }, location.origin);
    const open = () => ($wire.get('mountedActions') || []).length > 0;
    if (!@js($signed)) report('done');
    else {
        Livewire.hook('commit', ({ component, succeed }) => {
            if (component.id === $wire.$id) succeed(() => setTimeout(() => report(open() ? 'modal' : 'done'), 0));
        });
        $wire.mountAction(@js($run));
    }
">
    <style>
        html.ps-frame.ps-frame, html.ps-frame.ps-frame :is(body, .fi-body, .fi-layout, .fi-main-ctn, .fi-main, .fi-page) { background: transparent !important; }
        .fi-topbar, .fi-sidebar, .fi-header, .fi-footer, .fi-sidebar-close-overlay { display: none !important; }
    </style>
    <x-filament-actions::modals />
</div>

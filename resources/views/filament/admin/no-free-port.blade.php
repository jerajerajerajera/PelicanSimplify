{{-- Server step: the chosen node has no free port (allocation), so a server cannot be made there yet. --}}
<div class="ssc-port-notice" role="alert">
    <strong>{{ __('pelican-simplify::maker.ports.no_free_port', ['node' => $node]) }}</strong>
    <span>
        @if($url)
            {{ __('pelican-simplify::maker.ports.add_port') }} <a href="{{ $url }}" target="_top">{{ __('pelican-simplify::maker.ports.allocations_path', ['node' => $node]) }}</a>{{ __('pelican-simplify::maker.ports.then_next') }}
        @else
            {{ __('pelican-simplify::maker.ports.ask_admin') }}
        @endif
    </span>
</div>

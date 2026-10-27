{{-- Server step: the chosen node has no free port (allocation), so a server cannot be made there yet. --}}
<div class="ssc-port-notice" role="alert">
    <strong>No free port on {{ $node }}</strong>
    <span>
        @if($url)
            Every port on this node is in use. Add a port under <a href="{{ $url }}" target="_top">Nodes → {{ $node }} → Allocations</a>, then click Next again.
        @else
            Every port on this node is in use. Ask an admin to add a port, then click Next again.
        @endif
    </span>
</div>

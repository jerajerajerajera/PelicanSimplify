@php($conflicts = \Simplify\Panel\Services\ExternalPlugins::conflicts())
@if($conflicts)
    <div class="ps-alert is-warn ps-conflict-card" role="alert">
        <x-tabler-alert-triangle class="ps-ico" aria-hidden="true" />
        <div>
            <strong>Simplify's dashboard is switched off: {{ collect($conflicts)->pluck('name')->join(', ', ' and ') }} {{ count($conflicts) === 1 ? 'is' : 'are' }} installed</strong>
            @foreach($conflicts as $conflict)<small>{{ $conflict['name'] }}: {{ $conflict['reason'] }}</small>@endforeach
            <small>Everyone gets Pelican's own panel for now. Disable or remove {{ count($conflicts) === 1 ? 'that plugin' : 'those plugins' }} to turn Simplify back on; your Simplify settings are kept.</small>
        </div>
    </div>
@endif
@php($overlaps = \Simplify\Panel\Services\ExternalPlugins::overlaps())
@if($overlaps)
    <div class="ps-alert is-info ps-conflict-card" role="status">
        <x-tabler-puzzle class="ps-ico" aria-hidden="true" />
        <div>
            <strong>{{ collect($overlaps)->pluck('name')->join(', ', ' and ') }} {{ count($overlaps) === 1 ? 'does' : 'do' }} part of what Simplify does</strong>
            @foreach($overlaps as $overlap)<small>{{ $overlap['name'] }}: {{ $overlap['reason'] }}</small>@endforeach
        </div>
    </div>
@endif

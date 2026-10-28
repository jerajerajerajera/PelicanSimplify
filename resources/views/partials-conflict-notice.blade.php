@php($conflicts = \Simplify\Panel\Services\ExternalPlugins::conflicts())
@if($conflicts)
    <div class="ps-alert is-warn ps-conflict-card" role="alert">
        <x-tabler-alert-triangle class="ps-ico" aria-hidden="true" />
        <div>
            <strong>{{ trans_choice('pelican-simplify::plugins.conflicts.switched_off', count($conflicts), ['plugins' => collect($conflicts)->pluck('name')->join(', ', ' '.__('pelican-simplify::plugins.conflicts.and').' ')]) }}</strong>
            @foreach($conflicts as $conflict)<small>{{ $conflict['name'] }}: {{ $conflict['reason'] }}</small>@endforeach
            <small>{{ trans_choice('pelican-simplify::plugins.conflicts.switched_off_help', count($conflicts)) }}</small>
        </div>
    </div>
@endif
@php($overlaps = \Simplify\Panel\Services\ExternalPlugins::overlaps())
@if($overlaps)
    <div class="ps-alert is-info ps-conflict-card" role="status">
        <x-tabler-puzzle class="ps-ico" aria-hidden="true" />
        <div>
            <strong>{{ trans_choice('pelican-simplify::plugins.conflicts.overlap', count($overlaps), ['plugins' => collect($overlaps)->pluck('name')->join(', ', ' '.__('pelican-simplify::plugins.conflicts.and').' ')]) }}</strong>
            @foreach($overlaps as $overlap)<small>{{ $overlap['name'] }}: {{ $overlap['reason'] }}</small>@endforeach
        </div>
    </div>
@endif

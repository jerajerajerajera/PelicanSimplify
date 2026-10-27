@php($eggs = $this->eggUpdates())
<section class="ps-egg-updates">
    <header class="ps-egg-updates-head">
        <h3>Available updates</h3>
        <small class="ps-help">{{ $eggs['checked'] ? 'Checked '.\Carbon\Carbon::createFromTimestamp($eggs['checked'])->diffForHumans() : 'Not checked yet' }}</small>
        <button type="button" class="ps-btn is-sm" wire:click="checkEggUpdates" wire:loading.attr="disabled" wire:target="checkEggUpdates"><x-tabler-refresh class="ps-ico" /><span wire:loading.remove wire:target="checkEggUpdates">Check now</span><span wire:loading wire:target="checkEggUpdates">Checking…</span></button>
        @if(count($eggs['available']) > 1)
            <button type="button" class="ps-btn is-sm is-primary" x-on:click="Simplify.confirm({title:'Update all {{ count($eggs['available']) }} eggs?',message:'Running servers keep running. Each egg keeps its previous version, so it can be put back.',confirmLabel:'Update all'}).then(ok => ok && $wire.updateEggs(null))" wire:loading.attr="disabled"><x-tabler-download class="ps-ico" />Update all</button>
        @endif
    </header>
    <div class="ps-plugin-list">
        @forelse($eggs['available'] as $egg)
            <article class="ps-plugin" wire:key="egg-update-{{ $egg['id'] }}">
                <div class="ps-plugin-main">
                    <div class="ps-plugin-title"><strong>{{ $egg['name'] }}</strong>@if($egg['held'])<span class="ps-badge"><span>Put back</span></span>@endif</div>
                    <small>{{ $egg['servers'] === 0 ? 'No servers' : ($egg['servers'] === 1 ? '1 server' : $egg['servers'].' servers') }} · found {{ \Carbon\Carbon::createFromTimestamp($egg['found'])->diffForHumans() }}</small>
                </div>
                <div class="ps-plugin-actions">
                    <button type="button" class="ps-btn is-sm is-primary" x-on:click="Simplify.confirm({title:@js('Update '.$egg['name'].'?'),message:@js(($egg['servers'] > 0 ? 'Its servers keep running and get the new egg at their next start or reinstall. ' : '').'The previous version is kept, so it can be put back.'),confirmLabel:'Update'}).then(ok => ok && $wire.updateEggs({{ $egg['id'] }}))" wire:loading.attr="disabled"><x-tabler-download class="ps-ico" />Update</button>
                </div>
            </article>
        @empty
            <p class="ps-help">All eggs with an update address are up to date.</p>
        @endforelse
    </div>
    @if($eggs['updated'] !== [])
        <h3>Updated by Simplify</h3>
        <div class="ps-plugin-list">
            @foreach($eggs['updated'] as $egg)
                <article class="ps-plugin" wire:key="egg-updated-{{ $egg['id'] }}">
                    <div class="ps-plugin-main">
                        <div class="ps-plugin-title"><strong>{{ $egg['name'] }}</strong></div>
                        <small>Updated {{ \Carbon\Carbon::createFromTimestamp($egg['at'])->diffForHumans() }} · {{ $egg['servers'] === 0 ? 'No servers' : ($egg['servers'] === 1 ? '1 server' : $egg['servers'].' servers') }}</small>
                        @if($egg['moves'] > 0)<p class="ps-help">{{ $egg['moves'] === 1 ? '1 server still uses' : $egg['moves'].' servers still use' }} the old startup command or Docker image. Servers keep their own; move them to get the new one at their next start.</p>@endif
                    </div>
                    <div class="ps-plugin-actions">
                        @if($egg['moves'] > 0)
                            <button type="button" class="ps-btn is-sm is-primary" x-on:click="Simplify.confirm({title:@js('Move servers to the new '.$egg['name'].'?'),message:'They get the new startup command or Docker image at their next start. Servers with a command or image of their own are left alone.',confirmLabel:'Move servers'}).then(ok => ok && $wire.moveEggServers({{ $egg['id'] }}))" wire:loading.attr="disabled"><x-tabler-arrows-exchange class="ps-ico" />Move servers</button>
                        @endif
                        <button type="button" class="ps-btn is-sm" x-on:click="Simplify.confirm({title:@js('Put back the previous '.$egg['name'].'?'),message:'The egg returns to the version it had before this update. Automatic updates leave it alone until you update it again.',confirmLabel:'Put back'}).then(ok => ok && $wire.restoreEgg({{ $egg['id'] }}))" wire:loading.attr="disabled"><x-tabler-arrow-back-up class="ps-ico" />Put back</button>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</section>

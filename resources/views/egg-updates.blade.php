@php($eggs = $this->eggUpdates())
<section class="ps-egg-updates">
    <header class="ps-egg-updates-head">
        <h3>{{ __('pelican-simplify::eggs.updates.available_updates') }}</h3>
        <small class="ps-help">{{ $eggs['checked'] ? __('pelican-simplify::eggs.updates.checked', ['time' => \Carbon\Carbon::createFromTimestamp($eggs['checked'])->diffForHumans()]) : __('pelican-simplify::eggs.updates.not_checked') }}</small>
        <button type="button" class="ps-btn is-sm" wire:click="checkEggUpdates" wire:loading.attr="disabled" wire:target="checkEggUpdates"><x-tabler-refresh class="ps-ico" /><span wire:loading.remove wire:target="checkEggUpdates">{{ __('pelican-simplify::eggs.updates.check_now') }}</span><span wire:loading wire:target="checkEggUpdates">{{ __('pelican-simplify::eggs.updates.checking') }}</span></button>
        @if(count($eggs['available']) > 1)
            <button type="button" class="ps-btn is-sm is-primary" x-on:click="Simplify.confirm({title:@js(__('pelican-simplify::eggs.updates.update_all_title', ['count' => count($eggs['available'])])),message:@js(__('pelican-simplify::eggs.updates.running_servers_keep_running_each_egg')),confirmLabel:@js(__('pelican-simplify::eggs.updates.update_all'))}).then(ok => ok && $wire.updateEggs(null))" wire:loading.attr="disabled"><x-tabler-download class="ps-ico" />{{ __('pelican-simplify::eggs.updates.update_all') }}</button>
        @endif
    </header>
    <div class="ps-plugin-list" @if(array_filter(array_column($eggs['available'], 'testing'))) wire:poll.10s @endif>
        @forelse($eggs['available'] as $egg)
            <article class="ps-plugin" wire:key="egg-update-{{ $egg['id'] }}">
                <div class="ps-plugin-main">
                    <div class="ps-plugin-title"><strong>{{ $egg['name'] }}</strong>@if($egg['held'])<span class="ps-badge"><span>{{ __('pelican-simplify::eggs.updates.put_back') }}</span></span>@endif</div>
                    <small>{{ trans_choice('pelican-simplify::eggs.updates.servers', $egg['servers']) }} · {{ __('pelican-simplify::eggs.updates.found', ['time' => \Carbon\Carbon::createFromTimestamp($egg['found'])->diffForHumans()]) }}</small>
                    @if($egg['testing'])
                        <p class="ps-help">{{ ['waiting' => __('pelican-simplify::eggs.updates.test_waiting'), 'installing' => __('pelican-simplify::eggs.updates.test_installing'), 'starting' => __('pelican-simplify::eggs.updates.test_starting')][$egg['testing']] ?? __('pelican-simplify::eggs.updates.testing') }}</p>
                    @elseif($egg['result'] && !$egg['result']['ok'])
                        <p class="ps-plugin-error">{{ __('pelican-simplify::eggs.updates.test_failed', ['time' => \Carbon\Carbon::createFromTimestamp($egg['result']['at'])->diffForHumans(), 'reason' => \Simplify\Panel\Services\EggUpdateTest::reasonText($egg['result'])]) }}</p>
                    @endif
                </div>
                <div class="ps-plugin-actions">
                    @unless($egg['testing'])
                    <button type="button" class="ps-btn is-sm" x-on:click="Simplify.confirm({title:@js(__('pelican-simplify::eggs.updates.test_title', ['name' => $egg['name']])),message:@js(__('pelican-simplify::eggs.updates.simplify_makes_a_test_server_with')),confirmLabel:@js(__('pelican-simplify::eggs.updates.test_and_update'))}).then(ok => ok && $wire.testEgg({{ $egg['id'] }}))" wire:loading.attr="disabled"><x-tabler-flask class="ps-ico" />{{ __('pelican-simplify::eggs.updates.test_first') }}</button>
                    <button type="button" class="ps-btn is-sm is-primary" x-on:click="Simplify.confirm({title:@js(__('pelican-simplify::eggs.updates.update_title', ['name' => $egg['name']])),message:@js(($egg['servers'] > 0 ? __('pelican-simplify::eggs.updates.update_servers_keep').' ' : '').__('pelican-simplify::eggs.updates.previous_kept')),confirmLabel:@js(__('pelican-simplify::eggs.updates.update'))}).then(ok => ok && $wire.updateEggs({{ $egg['id'] }}))" wire:loading.attr="disabled"><x-tabler-download class="ps-ico" />{{ __('pelican-simplify::eggs.updates.update') }}</button>
                    @endunless
                </div>
            </article>
        @empty
            <p class="ps-help">{{ __('pelican-simplify::eggs.updates.all_eggs_with_an_update_address') }}</p>
        @endforelse
    </div>
    @if($eggs['updated'] !== [])
        <h3>{{ __('pelican-simplify::eggs.updates.updated_by_simplify') }}</h3>
        <div class="ps-plugin-list">
            @foreach($eggs['updated'] as $egg)
                <article class="ps-plugin" wire:key="egg-updated-{{ $egg['id'] }}">
                    <div class="ps-plugin-main">
                        <div class="ps-plugin-title"><strong>{{ $egg['name'] }}</strong></div>
                        <small>{{ __('pelican-simplify::eggs.updates.updated_time', ['time' => \Carbon\Carbon::createFromTimestamp($egg['at'])->diffForHumans()]) }} · {{ trans_choice('pelican-simplify::eggs.updates.servers', $egg['servers']) }}</small>
                        @if($egg['moves'] > 0)<p class="ps-help">{{ trans_choice('pelican-simplify::eggs.updates.moves', $egg['moves']) }}</p>@endif
                    </div>
                    <div class="ps-plugin-actions">
                        @if($egg['moves'] > 0)
                            <button type="button" class="ps-btn is-sm is-primary" x-on:click="Simplify.confirm({title:@js(__('pelican-simplify::eggs.updates.move_title', ['name' => $egg['name']])),message:@js(__('pelican-simplify::eggs.updates.they_get_the_new_startup_command')),confirmLabel:@js(__('pelican-simplify::eggs.updates.move_servers'))}).then(ok => ok && $wire.moveEggServers({{ $egg['id'] }}))" wire:loading.attr="disabled"><x-tabler-arrows-exchange class="ps-ico" />{{ __('pelican-simplify::eggs.updates.move_servers') }}</button>
                        @endif
                        <button type="button" class="ps-btn is-sm" x-on:click="Simplify.confirm({title:@js(__('pelican-simplify::eggs.updates.put_back_title', ['name' => $egg['name']])),message:@js(__('pelican-simplify::eggs.updates.the_egg_returns_to_the_version')),confirmLabel:@js(__('pelican-simplify::eggs.updates.put_back'))}).then(ok => ok && $wire.restoreEgg({{ $egg['id'] }}))" wire:loading.attr="disabled"><x-tabler-arrow-back-up class="ps-ico" />{{ __('pelican-simplify::eggs.updates.put_back') }}</button>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</section>

{{-- Host mode: pinned announcements folded into small chips on the top row of the workspace; a chip opens its bar. --}}
<div class="ps-host-pins" x-show="announcementBars().length" x-cloak x-on:click.outside="hostPin=null" x-on:keydown.escape="hostPin=null">
    <template x-for="item in announcementBars()" :key="'chip-'+item.key">
        <button type="button" class="ps-host-pin" :class="'is-'+item.tone" :aria-expanded="hostPin===item.key" aria-haspopup="dialog" :aria-label="item.title" x-on:click="hostPin = hostPin===item.key ? null : item.key" :data-tip="hostPin===item.key ? null : item.title">
            <svg aria-hidden="true"><use :href="'#ps-'+(item.tone==='ok'?'check':item.tone==='info'?'info':'alert')"/></svg>
            <span x-text="item.title"></span>
        </button>
    </template>
    <div class="ps-dropdown ps-host-pin-open" x-show="hostPin && announcementBars().some((i) => i.key===hostPin)" x-cloak role="dialog" aria-label="{{ __('pelican-simplify::announcements.bar.announcement') }}">
        <template x-for="item in announcementBars().filter((i) => i.key===hostPin)" :key="'open-'+item.key">
            @include('pelican-simplify::announcement-bar')
        </template>
    </div>
</div>

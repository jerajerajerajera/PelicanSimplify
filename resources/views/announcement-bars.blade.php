{{-- Announcements above the overview and server pages (Admin tools → Announcements, the Panel's update notices).
     Live ones stay pinned: collapsing one leaves a one-line pin that opens it again, until it ends. --}}
<div class="ps-announcements-pinned" x-show="announcements.length" x-cloak>
    <template x-for="item in announcementBars()" :key="item.key">
        <div class="ps-alert ps-announcement-bar" :class="'is-'+item.tone" role="status">
            <svg aria-hidden="true"><use :href="'#ps-'+(item.tone==='ok'?'check':item.tone==='info'?'info':'alert')"/></svg>
            <div class="ps-announcement-copy">
                <strong x-text="item.title"></strong>
                <span x-show="item.text" x-text="item.text"></span>
                <small x-show="announcementMeta(item)" x-text="announcementMeta(item)"></small>
            </div>
            <template x-if="item.action">
                <a class="ps-btn is-sm ps-announcement-action" :href="item.action.url" :target="externalLink(item.action.url) ? '_blank' : null" rel="noopener" x-on:click="followNotice($event, item)" x-text="item.action.label"></a>
            </template>
            <button type="button" class="ps-btn is-sm is-icon is-subtle ps-announcement-hide" data-tip="Collapse" :aria-label="'Collapse '+item.title" aria-expanded="true" x-on:click="dismissAnnouncement(item.key)"><svg aria-hidden="true"><use href="#ps-chevron"/></svg></button>
        </div>
    </template>
    <div class="ps-announcement-pins" x-show="announcementPins().length" role="list" aria-label="Collapsed announcements">
        <template x-for="item in announcementPins()" :key="item.key">
            <button type="button" class="ps-announcement-pin" :class="'is-'+item.tone" role="listitem" data-tip="Show" :aria-label="'Show '+item.title" aria-expanded="false" x-on:click="restoreAnnouncement(item.key)">
                <svg aria-hidden="true"><use :href="'#ps-'+(item.tone==='ok'?'check':item.tone==='info'?'info':'alert')"/></svg>
                <span x-text="item.title"></span>
                <svg class="ps-announcement-pin-open" aria-hidden="true"><use href="#ps-chevron"/></svg>
            </button>
        </template>
    </div>
</div>

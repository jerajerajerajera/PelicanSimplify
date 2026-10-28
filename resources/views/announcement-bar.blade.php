{{-- One announcement bar (item): the pinned bars above pages, and the open chip in host mode. --}}
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
    <button type="button" class="ps-btn is-sm is-icon ps-announcement-unpin" aria-pressed="true" data-tip="Unpin" :aria-label="'Unpin '+item.title" x-on:click="dismissAnnouncement(item.key)"><svg aria-hidden="true"><use href="#ps-pin"/></svg></button>
</div>

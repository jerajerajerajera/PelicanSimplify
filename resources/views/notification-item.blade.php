<div class="ps-notify-item" :class="['is-'+item.tone, item.unread === false ? 'is-read' : '']">
    <svg aria-hidden="true"><use :href="'#ps-'+(item.tone==='ok'?'check':item.tone==='info'?'info':'alert')"/></svg>
    <strong x-text="item.title"></strong>
    <small x-text="item.text" x-show="item.text"></small>
    <small class="ps-notify-time" x-show="item.time" x-text="item.time"></small>
    <span class="ps-notify-actions" x-show="item.action || (item.pelican && item.unread) || item.announcement">
        <template x-if="item.action">
            <a class="ps-btn is-sm" :href="item.action.url" :target="externalLink(item.action.url) ? '_blank' : null" rel="noopener" x-on:click="followNotice($event, item)" x-text="item.action.label"></a>
        </template>
        <button type="button" class="ps-btn is-sm is-subtle" x-show="item.pelican && item.unread" x-on:click="markPanelNoticesRead([item.key])">Mark read</button>
        <button type="button" class="ps-btn is-sm is-subtle ps-notify-pin" x-show="item.announcement" :aria-pressed="item.announcement && !item.dismissed ? 'true' : 'false'" :aria-label="(item.dismissed ? 'Pin ' : 'Unpin ')+item.title" x-on:click="item.dismissed ? restoreAnnouncement(item.key) : dismissAnnouncement(item.key)"><svg aria-hidden="true"><use href="#ps-pin"/></svg><span x-text="item.dismissed ? 'Pin' : 'Unpin'"></span></button>
    </span>
</div>

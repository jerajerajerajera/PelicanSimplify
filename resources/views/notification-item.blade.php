<div class="ps-notify-item" :class="['is-'+item.tone, item.unread === false ? 'is-read' : '']">
    <svg aria-hidden="true"><use :href="'#ps-'+(item.tone==='ok'?'check':item.tone==='info'?'info':'alert')"/></svg>
    <strong x-text="item.title"></strong>
    <button type="button" class="ps-notify-close" x-show="canArchive(item)" x-on:click.stop="archiveNotification(item)" :aria-label="'Clear '+item.title" data-tip="Clear"><svg aria-hidden="true"><use href="#ps-close"/></svg></button>
    <small x-text="item.text" x-show="item.text"></small>
    <template x-if="item.notes?.length">
        <div class="ps-notify-notes">
            <span class="ps-label">What's new</span>
            <ul><template x-for="(line, index) in noteLines(item, {{ ($archive ?? false) ? 'true' : 'false' }})" :key="index"><li x-text="line"></li></template></ul>
            <span class="ps-notify-notes-more">
                <button type="button" class="ps-text-button" x-show="{{ ($archive ?? false) ? 'false' : 'item.notes.length > 1' }}" x-on:click="toggleNotes(item)" :aria-expanded="!!openNotes[item.id]" x-text="openNotes[item.id] ? 'Show less' : 'Show all '+item.notes.length"></button>
                <a class="ps-text-button" x-show="item.notesUrl" :href="item.notesUrl" target="_blank" rel="noopener">Full release notes</a>
            </span>
        </div>
    </template>
    <small class="ps-notify-time" x-show="item.time" x-text="item.time"></small>
    <span class="ps-notify-actions" x-show="item.action || item.pelican || item.announcement">
        <template x-if="item.action">
            <a class="ps-btn is-sm" :href="item.action.url" :target="externalLink(item.action.url) ? '_blank' : null" rel="noopener" x-on:click="followNotice($event, item)" x-text="item.action.label"></a>
        </template>
        <button type="button" class="ps-btn is-sm is-subtle" x-show="(item.pelican || item.announcement) && item.unread" x-on:click="markNotificationRead(item)">Mark read</button>
        <button type="button" class="ps-btn is-sm is-subtle" x-show="(item.pelican || item.announcement) && item.unread === false" x-on:click="markNotificationUnread(item)">Mark as unread</button>
        {{-- Archived is cleared away: its bar pin does nothing visible there, so it shows again once the announcement is back. --}}
        <button type="button" class="ps-btn is-sm is-subtle ps-notify-pin" x-show="item.announcement && !item.archived" :aria-pressed="item.announcement && !item.dismissed ? 'true' : 'false'" :aria-label="(item.dismissed ? 'Pin ' : 'Unpin ')+item.title" x-on:click="item.dismissed ? restoreAnnouncement(item.key) : dismissAnnouncement(item.key)"><svg aria-hidden="true"><use href="#ps-pin"/></svg><span x-text="item.dismissed ? 'Pin' : 'Unpin'"></span></button>
    </span>
</div>

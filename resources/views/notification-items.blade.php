{{-- Notifications in the bell and in the full list: the block an admin pinned to everyone's notifications on top,
     then everything else, newest first, with "Show more" for older Pelican notifications. The full list ($archive)
     ends with a closed Archived section: what "Clear read" moved away. --}}
<div class="ps-notify-pinned" x-show="pinnedNotifications().length">
    <span class="ps-label"><svg aria-hidden="true"><use href="#ps-pin"/></svg>Pinned</span>
    <div class="ps-notify-list">
        <template x-for="item in pinnedNotifications()" :key="item.id">
            @include('pelican-simplify::notification-item')
        </template>
    </div>
</div>
<div class="ps-notify-list">
    <template x-for="item in otherNotifications()" :key="item.id">
        @include('pelican-simplify::notification-item')
    </template>
    <p class="ps-empty" x-show="!pinnedNotifications().length && !otherNotifications().length">You are all caught up.</p>
</div>
<button type="button" class="ps-btn is-sm is-subtle ps-notify-more" x-show="feed.more" x-on:click="loadNotificationPage()" :disabled="feed.loading" x-text="feed.loading ? 'Loading…' : 'Show more'"></button>
@if($archive ?? false)
    <div class="ps-notify-archive" x-show="hasArchive()">
        <button type="button" class="ps-notify-archive-toggle" x-on:click="toggleArchive()" :aria-expanded="archive.open" aria-controls="ps-notify-archived">
            <svg aria-hidden="true"><use href="#ps-chevron"/></svg><span>Archived</span>
        </button>
        <div id="ps-notify-archived" x-show="archive.open" x-cloak>
            <div class="ps-notify-list">
                <template x-for="item in archivedNotifications()" :key="'archived-'+item.id">
                    @include('pelican-simplify::notification-item')
                </template>
                <p class="ps-empty" x-show="archive.loaded && !archivedNotifications().length">Nothing archived.</p>
            </div>
            <button type="button" class="ps-btn is-sm is-subtle ps-notify-more" x-show="archive.more || archive.loading" x-on:click="loadArchivePage()" :disabled="archive.loading" x-text="archive.loading ? 'Loading…' : 'Show more'"></button>
        </div>
    </div>
@endif

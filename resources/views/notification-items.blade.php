{{-- Notifications in the bell and in the full list: the block an admin pinned to everyone's notifications on top,
     then everything else, newest first, with "Show more" for older Pelican notifications. --}}
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
    <p class="ps-empty" x-show="!notifications().length">You are all caught up.</p>
</div>
<button type="button" class="ps-btn is-sm is-subtle ps-notify-more" x-show="feed.more" x-on:click="loadNotificationPage()" :disabled="feed.loading" x-text="feed.loading ? 'Loading…' : 'Show more'"></button>

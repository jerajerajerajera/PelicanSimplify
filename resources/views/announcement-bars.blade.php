{{-- Announcements above the overview and server pages (Admin tools → Announcements, the Panel's update notices).
     Live ones are pinned here until they end. The pin button unpins one for this account; the bell pins it again. --}}
<div class="ps-announcements-pinned" x-show="announcementBars().length" x-cloak>
    <template x-for="item in announcementBars()" :key="item.key">
        @include('pelican-simplify::announcement-bar')
    </template>
</div>

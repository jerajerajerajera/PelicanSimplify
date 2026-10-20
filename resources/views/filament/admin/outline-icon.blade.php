<svg class="ssc-outline-icon" xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24"
     fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"
     aria-hidden="true" focusable="false">
    @switch ($icon)
        @case('vanilla')
            <path d="m12 3 9 5v8l-9 5-9-5V8z M3 8l9 5 9-5 M12 13v8" />
            @break
        @case('plus')
            <path d="M4 7h5a3 3 0 1 1 6 0h5v5a3 3 0 1 0 0 6v3h-6a3 3 0 1 0-6 0H4v-6a3 3 0 1 0 0-6z" />
            @break
        @case('game')
            <rect x="3" y="7" width="18" height="12" rx="4" /><path d="M7 11v4 M5 13h4 M15 12h.01 M18 14h.01" />
            @break
        @case('upload')
            <path d="M12 15V4 M7 9l5-5 5 5 M4 15v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3" />
            @break
        @case('clock')
            <circle cx="12" cy="12" r="9" />
            <path d="M12 7v5l3 2" />
            @break
        @case('cpu')
            <rect x="5" y="5" width="14" height="14" rx="2"/><rect x="9" y="9" width="6" height="6"/><path d="M9 2v3m6-3v3M9 19v3m6-3v3M2 9h3m-3 6h3M19 9h3m-3 6h3"/>
            @break
        @case('disk')
            <rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="11" cy="11" r="4"/><path d="m11 11 6 5M6 17h.01"/>
            @break
        @case('ram')
            <rect x="3" y="5" width="18" height="13" rx="2" />
            <path d="M6 18v3 M10 18v3 M14 18v3 M18 18v3" />
            <rect x="6" y="8" width="4" height="6" rx=".5" />
            <rect x="14" y="8" width="4" height="6" rx=".5" />
            @break
        @default
            <path d="m12 2 6 3.5v5L12 14l-6-3.5v-5z M6 5.5l6 3.5 6-3.5 M12 9v5" />
            <path d="m6 10 6 3.5v5L6 22l-5-3v-5.5z M1 13.5 6 17l6-3.5 M6 17v5" />
            <path d="m18 10 5 3.5V19l-5 3-6-3.5v-5z M12 13.5 18 17l5-3.5 M18 17v5" />
    @endswitch
</svg>

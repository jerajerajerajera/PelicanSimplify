{{-- Dashboard icon sprite. Icons are referenced with <use href="#ps-name">.
     Most are Tabler Icons (MIT, https://tabler.io/icons), the set Pelican itself uses; creeper, offline, ram and simplify are Simplify's own. --}}
<svg class="ps-symbols" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
    <defs>
        <symbol id="ps-offline" viewBox="0 0 24 24"><path d="M11.1 5.1 10.4 4.4A5.5 5.5 0 0 0 2.6 12.2L10.8 20.4 10 16.5 12.2 13.1 8.9 10.4Z M13 5.1 13.7 4.4A5.5 5.5 0 0 1 21.5 12.2L13.2 20.4 12.1 16.8 14.9 12.5 11.3 9.8Z"/></symbol>
        <symbol id="ps-traffic" viewBox="0 0 24 24"><path d="M17 3l0 18"/> <path d="M10 18l-3 3l-3 -3"/> <path d="M7 21l0 -18"/> <path d="M20 6l-3 -3l-3 3"/></symbol>
        <symbol id="ps-gamepad" viewBox="0 0 24 24"><path d="M12 5h3.5a5 5 0 0 1 0 10h-5.5l-4.015 4.227a2.3 2.3 0 0 1 -3.923 -2.035l1.634 -8.173a5 5 0 0 1 4.904 -4.019h3.4z"/> <path d="M14 15l4.07 4.284a2.3 2.3 0 0 0 3.925 -2.023l-1.6 -8.232"/> <path d="M8 9v2"/> <path d="M7 10h2"/> <path d="M14 10h2"/></symbol>
        <symbol id="ps-cube" viewBox="0 0 24 24"><path d="M12 3l8 4.5l0 9l-8 4.5l-8 -4.5l0 -9l8 -4.5"/> <path d="M12 12l8 -4.5"/> <path d="M12 12l0 9"/> <path d="M12 12l-8 -4.5"/></symbol>
        <symbol id="ps-menu" viewBox="0 0 24 24"><path d="M4 6l16 0"/> <path d="M4 12l16 0"/> <path d="M4 18l16 0"/></symbol>
        <symbol id="ps-creeper" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="1"/><path fill="currentColor" stroke="none" d="M6 7h4v4H6zm8 0h4v4h-4zm-4 4h4v3h3v5h-3v-3h-4v3H7v-5h3z"/></symbol>
        <symbol id="ps-simplify" viewBox="0 0 24 24"><path d="M5 20h11l3-4-5-6V6l-3-3-3 2v5l-4 5 1 5Z"/><path d="m14 6 8 3h-8M5 17l7-5 4 5M11 6h.01"/></symbol>
        <symbol id="ps-steam" viewBox="0 0 24 24"><path d="M16.5 5a4.5 4.5 0 1 1 -.653 8.953l-4.347 3.009l0 .038a3 3 0 0 1 -2.824 3l-.176 0a3 3 0 0 1 -2.94 -2.402l-2.56 -1.098v-3.5l3.51 1.755a2.989 2.989 0 0 1 2.834 -.635l2.727 -3.818a4.5 4.5 0 0 1 4.429 -5.302z"/> <circle cx="16.5" cy="9.5" r="1" fill="currentColor"/></symbol>
        <symbol id="ps-gear" viewBox="0 0 24 24"><path d="M10.325 4.317c.426 -1.756 2.924 -1.756 3.35 0a1.724 1.724 0 0 0 2.573 1.066c1.543 -.94 3.31 .826 2.37 2.37a1.724 1.724 0 0 0 1.065 2.572c1.756 .426 1.756 2.924 0 3.35a1.724 1.724 0 0 0 -1.066 2.573c.94 1.543 -.826 3.31 -2.37 2.37a1.724 1.724 0 0 0 -2.572 1.065c-.426 1.756 -2.924 1.756 -3.35 0a1.724 1.724 0 0 0 -2.573 -1.066c-1.543 .94 -3.31 -.826 -2.37 -2.37a1.724 1.724 0 0 0 -1.065 -2.572c-1.756 -.426 -1.756 -2.924 0 -3.35a1.724 1.724 0 0 0 1.066 -2.573c-.94 -1.543 .826 -3.31 2.37 -2.37c1 .608 2.296 .07 2.572 -1.065z"/> <path d="M9 12a3 3 0 1 0 6 0a3 3 0 0 0 -6 0"/></symbol>
        <symbol id="ps-wand" viewBox="0 0 24 24"><path d="M5 4h4a1 1 0 0 1 1 1v6a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1v-6a1 1 0 0 1 1 -1"/> <path d="M5 16h4a1 1 0 0 1 1 1v2a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1v-2a1 1 0 0 1 1 -1"/> <path d="M15 12h4a1 1 0 0 1 1 1v6a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1v-6a1 1 0 0 1 1 -1"/> <path d="M15 4h4a1 1 0 0 1 1 1v2a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1v-2a1 1 0 0 1 1 -1"/></symbol>
        <symbol id="ps-settings" viewBox="0 0 24 24"><path d="M14 6m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0"/> <path d="M4 6l8 0"/> <path d="M16 6l4 0"/> <path d="M8 12m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0"/> <path d="M4 12l2 0"/> <path d="M10 12l10 0"/> <path d="M17 18m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0"/> <path d="M4 18l11 0"/> <path d="M19 18l1 0"/></symbol>
        <symbol id="ps-edit" viewBox="0 0 24 24"><path d="M4 20h4l10.5 -10.5a2.828 2.828 0 1 0 -4 -4l-10.5 10.5v4"/> <path d="M13.5 6.5l4 4"/></symbol>
        <symbol id="ps-image" viewBox="0 0 24 24"><path d="M15 8h.01"/> <path d="M3 6a3 3 0 0 1 3 -3h12a3 3 0 0 1 3 3v12a3 3 0 0 1 -3 3h-12a3 3 0 0 1 -3 -3v-12z"/> <path d="M3 16l5 -5c.928 -.893 2.072 -.893 3 0l5 5"/> <path d="M14 14l1 -1c.928 -.893 2.072 -.893 3 0l3 3"/></symbol>
        <symbol id="ps-files" viewBox="0 0 24 24"><path d="M5 4h4l3 3h7a2 2 0 0 1 2 2v8a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2v-11a2 2 0 0 1 2 -2"/></symbol>
        <symbol id="ps-backups" viewBox="0 0 24 24"><path d="M12 8l0 4l2 2"/> <path d="M3.05 11a9 9 0 1 1 .5 4m-.5 5v-5h5"/></symbol>
        <symbol id="ps-players" viewBox="0 0 24 24"><path d="M9 7m-4 0a4 4 0 1 0 8 0a4 4 0 1 0 -8 0"/> <path d="M3 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2"/> <path d="M16 3.13a4 4 0 0 1 0 7.75"/> <path d="M21 21v-2a4 4 0 0 0 -3 -3.85"/></symbol>
        <symbol id="ps-packages" viewBox="0 0 24 24"><path d="M12 4l-8 4l8 4l8 -4l-8 -4"/> <path d="M4 12l8 4l8 -4"/> <path d="M4 16l8 4l8 -4"/></symbol>
        <symbol id="ps-web" viewBox="0 0 24 24"><path d="M3 12a9 9 0 1 0 18 0a9 9 0 0 0 -18 0"/> <path d="M3.6 9h16.8"/> <path d="M3.6 15h16.8"/> <path d="M11.5 3a17 17 0 0 0 0 18"/> <path d="M12.5 3a17 17 0 0 1 0 18"/></symbol>
        <symbol id="ps-mods" viewBox="0 0 24 24"><path d="M4 7h3a1 1 0 0 0 1 -1v-1a2 2 0 0 1 4 0v1a1 1 0 0 0 1 1h3a1 1 0 0 1 1 1v3a1 1 0 0 0 1 1h1a2 2 0 0 1 0 4h-1a1 1 0 0 0 -1 1v3a1 1 0 0 1 -1 1h-3a1 1 0 0 1 -1 -1v-1a2 2 0 0 0 -4 0v1a1 1 0 0 1 -1 1h-3a1 1 0 0 1 -1 -1v-3a1 1 0 0 1 1 -1h1a2 2 0 0 0 0 -4h-1a1 1 0 0 1 -1 -1v-3a1 1 0 0 1 1 -1"/></symbol>
        <symbol id="ps-plugins" viewBox="0 0 24 24"><path d="M9.785 6l8.215 8.215l-2.054 2.054a5.81 5.81 0 1 1 -8.215 -8.215l2.054 -2.054z"/> <path d="M4 20l3.5 -3.5"/> <path d="M15 4l-3.5 3.5"/> <path d="M20 9l-3.5 3.5"/></symbol>
        <symbol id="ps-activity" viewBox="0 0 24 24"><path d="M13 5h8"/> <path d="M13 9h5"/> <path d="M13 15h8"/> <path d="M13 19h5"/> <path d="M3 4m0 1a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v4a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1z"/> <path d="M3 14m0 1a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v4a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1z"/></symbol>
        <symbol id="ps-advanced" viewBox="0 0 24 24"><path d="M7 10h3v-3l-3.5 -3.5a6 6 0 0 1 8 8l6 6a2 2 0 0 1 -3 3l-6 -6a6 6 0 0 1 -8 -8l3.5 3.5"/></symbol>
        <symbol id="ps-copy" viewBox="0 0 24 24"><path d="M7 7m0 2.667a2.667 2.667 0 0 1 2.667 -2.667h8.666a2.667 2.667 0 0 1 2.667 2.667v8.666a2.667 2.667 0 0 1 -2.667 2.667h-8.666a2.667 2.667 0 0 1 -2.667 -2.667z"/> <path d="M4.012 16.737a2.005 2.005 0 0 1 -1.012 -1.737v-10c0 -1.1 .9 -2 2 -2h10c.75 0 1.158 .385 1.5 1"/></symbol>
        <symbol id="ps-link" viewBox="0 0 24 24"><path d="M9 15l6 -6"/> <path d="M11 6l.463 -.536a5 5 0 0 1 7.071 7.072l-.534 .464"/> <path d="M13 18l-.397 .534a5.068 5.068 0 0 1 -7.127 0a4.972 4.972 0 0 1 0 -7.071l.524 -.463"/></symbol>
        <symbol id="ps-heart" viewBox="0 0 24 24"><path d="M19.5 12.572l-7.5 7.428l-7.5 -7.428a5 5 0 1 1 7.5 -6.566a5 5 0 1 1 7.5 6.572"/></symbol>
        <symbol id="ps-host" viewBox="0 0 24 24"><path d="M3 4m0 3a3 3 0 0 1 3 -3h12a3 3 0 0 1 3 3v2a3 3 0 0 1 -3 3h-12a3 3 0 0 1 -3 -3z"/> <path d="M3 12m0 3a3 3 0 0 1 3 -3h12a3 3 0 0 1 3 3v2a3 3 0 0 1 -3 3h-12a3 3 0 0 1 -3 -3z"/> <path d="M7 8l0 .01"/> <path d="M7 16l0 .01"/> <path d="M11 8h6"/> <path d="M11 16h6"/></symbol>
        <symbol id="ps-cpu" viewBox="0 0 24 24"><path d="M5 5m0 1a1 1 0 0 1 1 -1h12a1 1 0 0 1 1 1v12a1 1 0 0 1 -1 1h-12a1 1 0 0 1 -1 -1z"/> <path d="M9 9h6v6h-6z"/> <path d="M3 10h2"/> <path d="M3 14h2"/> <path d="M10 3v2"/> <path d="M14 3v2"/> <path d="M21 10h-2"/> <path d="M21 14h-2"/> <path d="M14 21v-2"/> <path d="M10 21v-2"/></symbol>
        <symbol id="ps-ram" viewBox="0 0 24 24"><path d="M3 6h18v11H3zM6 10h3v3H6zm9 0h3v3h-3zM6 17v3m4-3v3m4-3v3m4-3v3"/></symbol>
        <symbol id="ps-disk" viewBox="0 0 24 24"><path d="M12 6m-8 0a8 3 0 1 0 16 0a8 3 0 1 0 -16 0"/> <path d="M4 6v6a8 3 0 0 0 16 0v-6"/> <path d="M4 12v6a8 3 0 0 0 16 0v-6"/></symbol>
        <symbol id="ps-sun" viewBox="0 0 24 24"><path d="M12 12m-4 0a4 4 0 1 0 8 0a4 4 0 1 0 -8 0"/> <path d="M3 12h1m8 -9v1m8 8h1m-9 8v1m-6.4 -15.4l.7 .7m12.1 -.7l-.7 .7m0 11.4l.7 .7m-12.1 -.7l-.7 .7"/></symbol>
        <symbol id="ps-moon" viewBox="0 0 24 24"><path d="M12 3c.132 0 .263 0 .393 0a7.5 7.5 0 0 0 7.92 12.446a9 9 0 1 1 -8.313 -12.454z"/></symbol>
        <symbol id="ps-grid" viewBox="0 0 24 24"><path d="M4 4m0 1a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v4a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1z"/> <path d="M14 4m0 1a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v4a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1z"/> <path d="M4 14m0 1a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v4a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1z"/> <path d="M14 14m0 1a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v4a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1z"/></symbol>
        <symbol id="ps-grid-small" viewBox="0 0 24 24"><path d="M3 6h18"/> <path d="M3 12h18"/> <path d="M3 18h18"/> <path d="M6 3v18"/> <path d="M12 3v18"/> <path d="M18 3v18"/></symbol>
        <symbol id="ps-list" viewBox="0 0 24 24"><path d="M9 6l11 0"/> <path d="M9 12l11 0"/> <path d="M9 18l11 0"/> <path d="M5 6l0 .01"/> <path d="M5 12l0 .01"/> <path d="M5 18l0 .01"/></symbol>
        <symbol id="ps-chevron" viewBox="0 0 24 24"><path d="M9 6l6 6l-6 6"/></symbol>
        <symbol id="ps-back" viewBox="0 0 24 24"><path d="M5 12l14 0"/> <path d="M5 12l6 6"/> <path d="M5 12l6 -6"/></symbol>
        <symbol id="ps-plus" viewBox="0 0 24 24"><path d="M12 5l0 14"/> <path d="M5 12l14 0"/></symbol>
        <symbol id="ps-terminal" viewBox="0 0 24 24"><path d="M8 9l3 3l-3 3"/> <path d="M13 15l3 0"/> <path d="M3 4m0 2a2 2 0 0 1 2 -2h14a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2z"/></symbol>
        <symbol id="ps-legacy" viewBox="0 0 24 24"><path d="M4 8h16"/> <path d="M4 4m0 2a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2z"/> <path d="M8 4v4"/></symbol>
        <symbol id="ps-account" viewBox="0 0 24 24"><path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0"/> <path d="M6 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2"/></symbol>
        <symbol id="ps-monitor" viewBox="0 0 24 24"><path d="M3 5a1 1 0 0 1 1 -1h16a1 1 0 0 1 1 1v10a1 1 0 0 1 -1 1h-16a1 1 0 0 1 -1 -1v-10z"/> <path d="M7 20h10"/> <path d="M9 16v4"/> <path d="M15 16v4"/></symbol>
        <symbol id="ps-logout" viewBox="0 0 24 24"><path d="M14 8v-2a2 2 0 0 0 -2 -2h-7a2 2 0 0 0 -2 2v12a2 2 0 0 0 2 2h7a2 2 0 0 0 2 -2v-2"/> <path d="M9 12h12l-3 -3"/> <path d="M18 15l3 -3"/></symbol>
        <symbol id="ps-save" viewBox="0 0 24 24"><path d="M6 4h10l4 4v10a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2v-12a2 2 0 0 1 2 -2"/> <path d="M12 14m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0"/> <path d="M14 4l0 4l-6 0l0 -4"/></symbol>
        <symbol id="ps-file" viewBox="0 0 24 24"><path d="M14 3v4a1 1 0 0 0 1 1h4"/> <path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z"/></symbol>
        <symbol id="ps-shield" viewBox="0 0 24 24"><path d="M12 3a12 12 0 0 0 8.5 3a12 12 0 0 1 -8.5 15a12 12 0 0 1 -8.5 -15a12 12 0 0 0 8.5 -3"/></symbol>
        <symbol id="ps-egg" viewBox="0 0 24 24"><path d="M19 14.083c0 4.154 -2.966 6.74 -7 6.917c-4.2 0 -7 -2.763 -7 -6.917c0 -5.538 3.5 -11.09 7 -11.083c3.5 .007 7 5.545 7 11.083z"/></symbol>
        <symbol id="ps-database" viewBox="0 0 24 24"><path d="M12 6m-8 0a8 3 0 1 0 16 0a8 3 0 1 0 -16 0"/> <path d="M4 6v6a8 3 0 0 0 16 0v-6"/> <path d="M4 12v6a8 3 0 0 0 16 0v-6"/></symbol>
        <symbol id="ps-key" viewBox="0 0 24 24"><path d="M16.555 3.843l3.602 3.602a2.877 2.877 0 0 1 0 4.069l-2.643 2.643a2.877 2.877 0 0 1 -4.069 0l-.301 -.301l-6.558 6.558a2 2 0 0 1 -1.239 .578l-.175 .008h-1.172a1 1 0 0 1 -.993 -.883l-.007 -.117v-1.172a2 2 0 0 1 .467 -1.284l.119 -.13l.414 -.414h2v-2h2v-2l2.144 -2.144l-.301 -.301a2.877 2.877 0 0 1 0 -4.069l2.643 -2.643a2.877 2.877 0 0 1 4.069 0z"/> <path d="M15 9h.01"/></symbol>
        <symbol id="ps-lock" viewBox="0 0 24 24"><path d="M5 13a2 2 0 0 1 2 -2h10a2 2 0 0 1 2 2v6a2 2 0 0 1 -2 2h-10a2 2 0 0 1 -2 -2v-6z"/> <path d="M11 16a1 1 0 1 0 2 0a1 1 0 0 0 -2 0"/> <path d="M8 11v-4a4 4 0 1 1 8 0v4"/></symbol>
        <symbol id="ps-play" viewBox="0 0 24 24"><path d="M7 4v16l13 -8z"/></symbol>
        <symbol id="ps-stop" viewBox="0 0 24 24"><path d="M5 5m0 2a2 2 0 0 1 2 -2h10a2 2 0 0 1 2 2v10a2 2 0 0 1 -2 2h-10a2 2 0 0 1 -2 -2z"/></symbol>
        <symbol id="ps-restart" viewBox="0 0 24 24"><path d="M20 11a8.1 8.1 0 0 0 -15.5 -2m-.5 -4v4h4"/> <path d="M4 13a8.1 8.1 0 0 0 15.5 2m.5 4v-4h-4"/></symbol>
        <symbol id="ps-kill" viewBox="0 0 24 24"><path d="M18 6l-12 12"/> <path d="M6 6l12 12"/></symbol>
        <symbol id="ps-clock" viewBox="0 0 24 24"><path d="M3 12a9 9 0 1 0 18 0a9 9 0 0 0 -18 0"/> <path d="M12 7v5l3 3"/></symbol>
        <symbol id="ps-undo" viewBox="0 0 24 24"><path d="M9 14l-4 -4l4 -4"/> <path d="M5 10h11a4 4 0 1 1 0 8h-1"/></symbol>
        <symbol id="ps-bell" viewBox="0 0 24 24"><path d="M10 5a2 2 0 1 1 4 0a7 7 0 0 1 4 6v3a4 4 0 0 0 2 3h-16a4 4 0 0 0 2 -3v-3a7 7 0 0 1 4 -6"/> <path d="M9 17v1a3 3 0 0 0 6 0v-1"/></symbol>
        <symbol id="ps-check" viewBox="0 0 24 24"><path d="M5 12l5 5l10 -10"/></symbol>
        <symbol id="ps-info" viewBox="0 0 24 24"><path d="M3 12a9 9 0 1 0 18 0a9 9 0 0 0 -18 0"/> <path d="M12 9h.01"/> <path d="M11 12h1v4h1"/></symbol>
        <symbol id="ps-alert" viewBox="0 0 24 24"><path d="M12 9v4"/> <path d="M10.363 3.591l-8.106 13.534a1.914 1.914 0 0 0 1.636 2.871h16.214a1.914 1.914 0 0 0 1.636 -2.87l-8.106 -13.536a1.914 1.914 0 0 0 -3.274 0z"/> <path d="M12 16h.01"/></symbol>
        <symbol id="ps-close" viewBox="0 0 24 24"><path d="M18 6l-12 12"/> <path d="M6 6l12 12"/></symbol>
        <symbol id="ps-dots" viewBox="0 0 24 24"><path d="M5 12m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0"/><path d="M12 12m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0"/><path d="M19 12m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0"/></symbol>
        <symbol id="ps-expand" viewBox="0 0 24 24"><path d="M16 4l4 0l0 4"/><path d="M14 10l6 -6"/><path d="M8 20l-4 0l0 -4"/><path d="M4 20l6 -6"/><path d="M16 20l4 0l0 -4"/><path d="M14 14l6 6"/><path d="M8 4l-4 0l0 4"/><path d="M4 4l6 6"/></symbol>
        <symbol id="ps-external" viewBox="0 0 24 24"><path d="M12 6h-6a2 2 0 0 0 -2 2v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-6"/> <path d="M11 13l9 -9"/> <path d="M15 4h5v5"/></symbol>
        <symbol id="ps-arrow-right" viewBox="0 0 24 24"><path d="M5 12l14 0"/> <path d="M13 18l6 -6"/> <path d="M13 6l6 6"/></symbol>
        <symbol id="ps-server" viewBox="0 0 24 24"><path d="M3 4m0 3a3 3 0 0 1 3 -3h12a3 3 0 0 1 3 3v2a3 3 0 0 1 -3 3h-12a3 3 0 0 1 -3 -3z"/> <path d="M3 12m0 3a3 3 0 0 1 3 -3h12a3 3 0 0 1 3 3v2a3 3 0 0 1 -3 3h-12a3 3 0 0 1 -3 -3z"/> <path d="M7 8l0 .01"/> <path d="M7 16l0 .01"/></symbol>
        <symbol id="ps-download" viewBox="0 0 24 24"><path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2 -2v-2"/> <path d="M7 11l5 5l5 -5"/> <path d="M12 4l0 12"/></symbol>
        <symbol id="ps-search" viewBox="0 0 24 24"><path d="M10 10m-7 0a7 7 0 1 0 14 0a7 7 0 1 0 -14 0"/> <path d="M21 21l-6 -6"/></symbol>
    </defs>
</svg>

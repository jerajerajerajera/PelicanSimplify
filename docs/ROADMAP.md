# Roadmap

Planned work after the 0.3 betas. Scope and order may change.

## Easy server creator
A separate add-on next to the **easy Minecraft server creator**. Done since 0.3.0-beta.20: a searchable index of Pelican's egg sources, automatic egg installation with the *Install new games* ability, and typed game settings with an Advanced block. Still open:
- Accept a game's EULA or license up front (checkbox and link), so Simplify sets the right variable or file and the server does not restart in a loop.
- Recommended resources per game (small, normal, large), from the egg's recommendation or an admin default per game.
- An optional own index URL, for example a fork or an own eggs repository.
- "Popular" based on what is created most on the panel, with a small fixed starter list while there are few servers.
- The progress card after Create (see Server installation overhaul), with the last install-log lines and *Reinstall* and *Console* buttons on failure.

## Server installation overhaul
- Show one clear state ("Egg initialising…", "Server initialising…") with progress until the server is ready, instead of the start/stop flicker during installation.
- Accept the Minecraft EULA before the first start (write `eula.txt` as part of installation) so the server does not stop several times first.

## Simplify as the whole panel
- Setup wizard option: Simplify replaces the panel. The native (legacy) panel stays reachable under `/legacy`.
- Optional: switch the legacy panel off completely. Only offered when no other plugins are installed, because other plugins add their pages to Pelican's panels.
- Depends on the Minecraft plugin installer and mod installers being finished first.

## Host mode
- Larger hosts (several nodes) can switch to host mode: servers in a compact list per node on the left, Pelican's admin pages as the main workspace.

## Other plugins
- Done in the 0.5.0 snapshots: account pages from plugins in the account menu, widgets from plugins as tiles on the overview and the server page, and overlapping plugins (such as Subdomains) switching off only the matching Simplify feature. Every page and widget can be switched off in the settings.
- Still open: buttons that plugins add to Pelican's pages (for example Create server from User Creatable Servers on the server list), and tabs or form fields plugins add to Pelican's own forms.

## Phone experience
- Done in the 0.4.7 snapshots: the overview (one page scroll, one filter row, compact rows with one power button, a one-line health summary and a bottom tab bar), the server page (four tabs, a full-height console with quick commands), popups as sheets with a drag handle, and the creator ("Step 1 of 4", choice rows, Back / Next at the bottom).
- Bottom tab bar (Servers, Create, Alerts, Account) and a top bar that collapses on scroll.
- Full-screen sheets with a drag handle instead of centred popups; the save bar sits above the tab bar.
- Server page on phones: a status header with large power buttons, swipeable sections (Console, Players, Files, Settings), and a console with a command bar above the keyboard plus quick-command chips.
- Done in 0.5.0-snapshot.5: embedded Pelican pages (files, admin) restyled for narrow screens: one column and 44 px touch targets.
- Host mode on phones: a node and server switcher instead of three columns.
- Done in 0.5.0-snapshot.4: installable web app (manifest, icons, theme colour, offline page) and safe-area support for notched phones.
- Checked with automated phone screenshots (360, 390 and 430 px wide) in the browser tests.

## Server page
- Sticky status bar: name, status, address, players, uptime and the power buttons always in view.
- Layout per game: console first for modded servers, players first for busy servers; users can reorder or hide tiles.
- Workspaces (files, backups, settings, players) open as tabs inside the page instead of popups, with a back button and deep links.
- Console: filter by errors/warnings, search, pause scrolling, and highlighted player joins and crashes.
- One "needs attention" card: pending restarts, failed backups, outdated software, crash on last start.
- Activity timeline: recent starts, crashes, backups and changes in one list.
- Resource graphs with a 1 h / 24 h choice and clear limits.


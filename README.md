# PelicanSimplify

A cleaner, simpler interface for the [Pelican](https://github.com/pelican/panel) game server panel. You manage your servers from one overview and create new ones with a guided wizard. Everything still runs on Pelican and [Wings](https://github.com/pelican/wings), and the original panel stays available.

**Stable 0.4.7 and beta 0.5.0-beta.8 for Pelican 1.0.0-beta38, 1.0.0-rc.1 and 1.0.0-rc.2 · PHP 8.3 to 8.5 (8.4+ on rc.1 and rc.2)** · [Compatibility](#compatibility)

*Screenshots use example servers.*

<a href="docs/screenshots/server-overview.png"><img src="docs/screenshots/server-overview.png" alt="Server overview" width="280"></a>
<a href="docs/screenshots/server-dashboard.png"><img src="docs/screenshots/server-dashboard.png" alt="Server dashboard" width="280"></a>
<a href="docs/screenshots/server-creator.png"><img src="docs/screenshots/server-creator.png" alt="Guided server creation" width="280"></a>
<a href="docs/screenshots/server-list.png"><img src="docs/screenshots/server-list.png" alt="List view grouped by node" width="280"></a>

## What it does

Simplify puts all your servers on one page, with their status, players, CPU and RAM, as cards or a list. New servers are made with a step-by-step creator: pick Minecraft (Vanilla, Paper or a modpack) or one of 170+ other games, and Simplify installs the right egg.

For a few servers the simple mode is enough. Larger hosts can switch to host mode, with a server list per node and the admin pages next to it. You can choose a light or dark theme, give servers their own color and banner, and use your own logo or panel name. In friends mode everyone sees every server but only manages their own, and roles decide who may do what.

## Now in beta (0.5.0-beta.8)

The next release is in beta. It adds Simplify on phones, support for other plugins and a more flexible overview on top of stable 0.4.7. Tested on Pelican 1.0.0-beta38, 1.0.0-rc.1 and 1.0.0-rc.2 with PHP 8.3 to 8.5 (see Compatibility):

- Simplify on your phone. The overview, the server page, popups and the server creator are made for small screens: one page that scrolls, compact server rows with one power button, a tab bar at the bottom, a full-height console with quick commands, and popups that slide up as sheets. Pelican's own pages inside Simplify (admin pages, account, server settings) get one column and larger touch targets, and host mode becomes one column with a server switcher. Wider screens do not change.
- The panel as an app. Add the panel to your phone's home screen ("Install app" in the menu): its own icon, full screen without the browser bar, and a clear page when there is no connection. New in 0.5.0-beta.2.
- Support for other plugins. Pages that plugins add for every account (such as Legal Pages or User Creatable Servers) are in the account menu. Widgets that plugins add (such as Player Counter on the server page, or the resource limits of User Creatable Servers above your servers) show up as tiles. A plugin that does part of what Simplify does switches off only that part: with the Subdomains plugin enabled, Simplify's own subdomains step aside instead of the whole dashboard. Buttons that plugins add to Pelican's server list and console (such as Create server from User Creatable Servers, or Upload logs from Mclogs Uploader) show and work in Simplify too. Every plugin page, widget and button can be switched on or off in Simplify settings.
- Your overview, your way. Make your own server groups from the new Server groups button, saved with your account so every device shows them. Choose the server order (Name, Created, Port, or your own order per group), the layout (Cards, the new Compact cards, or List) with 2 to 6 columns and three card widths, quick actions Off, On or on Hover, and a full screen width overview. Phones can pick their own layout. New in 0.5.0-beta.5 to beta.8.
- Normal size on every screen. The interface no longer shrinks on screens below 1440p; it only scales down, never below 75%, when the page would not fit. Long server names use two lines.

**Try it:** in Simplify settings → Plugin manager, choose **Beta** under "Simplify versions"; the plugin manager installs it with a backup and automatic rollback. Or import `https://raw.githubusercontent.com/jerajerajerajera/PelicanSimplify/beta/dist/pelican-simplify-beta-latest.zip`. Betas are less tested than stable releases; see the [beta changelog](https://github.com/jerajerajerajera/PelicanSimplify/blob/beta/CHANGELOG.md) for every change.

## Before you install

Simplify is a large change to how your panel looks and works. Please read this first.

- It replaces a lot of the panel. The server overview, the server pages and server creation become Simplify's. Optionally it also takes over the Users, Roles and Plugins pages, and admins can use host mode.
- Everything can be switched off. Each part has its own switch in Simplify settings, and you can turn off the whole Simplify dashboard. Pelican's original panel always stays available: the Legacy links in Admin tools lead to it, and even if you hide those links the pages keep working.
- It does not touch your running servers. Servers, eggs, nodes and permissions stay Pelican's own. Simplify is a different way to look at and manage them.
- Other plugins are only partly supported inside stable Simplify. They keep working in Pelican's original panel. Simplify shows their admin and server pages under "External". Their account pages, widgets and buttons are supported in the beta (see Now in beta).
- **Make a backup before installing**, as with any plugin that changes this much.

## Getting started

1. In Pelican, go to **Admin → Plugins → Import from URL**.
2. Paste the link to the [latest stable plugin ZIP](https://github.com/jerajerajerajera/PelicanSimplify/releases/download/v0.4.7/pelican-simplify-v0.4.7.zip) and click **Install**. All versions are on the [Releases page](https://github.com/jerajerajerajera/PelicanSimplify/releases).

   **Beta and snapshot builds:** the newest beta is always at `https://raw.githubusercontent.com/jerajerajerajera/PelicanSimplify/beta/dist/pelican-simplify-beta-latest.zip`, the newest snapshot at `https://raw.githubusercontent.com/jerajerajerajera/PelicanSimplify/snapshot/dist/pelican-simplify-snapshot-latest.zip`. Betas test the next stable release with all its new features; snapshots are development builds with the latest changes. Both are less tested; see the channel table below.
3. Follow the setup checks. Anything missing is shown with how to fix it.

| Channel | Current version | What it has | Changelog |
|---|---|---|---|
| **Stable** (default) | 0.4.7 | Everything under All features below | [CHANGELOG](CHANGELOG.md) |
| **Beta** | 0.5.0-beta.8 | Stable plus Simplify on phones, the panel as an app, support for other plugins and a more flexible overview (see Now in beta) | [beta changelog](https://github.com/jerajerajerajera/PelicanSimplify/blob/beta/CHANGELOG.md) |
| **Snapshot** | 0.5.0-snapshot.18 | The newest development build; at the moment the same features as the beta | [snapshot changelog](https://github.com/jerajerajerajera/PelicanSimplify/blob/snapshot/CHANGELOG.md) |

### Compatibility

| Pelican | PHP | Stable 0.4.7 | Beta 0.5.0-beta.8 |
|---|---|---|---|
| 1.0.0-beta38 | 8.3 | ✅ Works | ✅ Tested, works |
| 1.0.0-beta38 | 8.4 | Not tested | ✅ Tested, works |
| 1.0.0-beta38 | 8.5 | Not tested | ✅ Tested, works |
| 1.0.0-rc.1 | 8.4 | ✅ Works | ✅ Tested, works |
| 1.0.0-rc.1 | 8.5 | Not tested | ✅ Tested, works |
| 1.0.0-rc.2 | 8.4 | ✅ Supported | ✅ Tested, works |
| 1.0.0-rc.2 | 8.5 | ✅ Supported | ✅ Tested, works |

Pelican 1.0.0-rc.1 and rc.2 need PHP 8.4 or newer. "Tested" means a fresh panel with that Pelican and PHP version: Simplify installed, the panel started and every Simplify page opened without errors. The Pelican and PHP combinations were tested with 0.5.0-beta.3; later betas add overview features and support the same versions. Stable 0.4.7 has the same rc.2 changes as that beta. Snapshot 0.5.0-snapshot.18 supports the same versions as the beta. On a Pelican release that is not in this table (a newer one, or a panel installed from git that reports "canary"), the setup check "Tested Pelican release" shows a warning and installing continues.

**Requirements:** Node.js 22.12+ or 24+, Yarn, and a running queue worker and scheduler.

**Updates:** Simplify updates itself through its plugin manager, with a backup and automatic rollback. You always get the build made for your Pelican version. Stable releases are the default; choose Beta or Snapshot under "Simplify versions" in Simplify settings → Plugin manager to try new features earlier.

Coming from the standalone creator? See the [installation notes](docs/INSTALLATION.md). Found a problem? Open an [issue](https://github.com/jerajerajerajera/PelicanSimplify/issues).

## Coming features

The order and scope may still change. Everything Simplify does not cover yet is still available in the original Pelican panel.

### Top priority: plugin support

Simplify should work together with other Pelican plugins instead of beside them:

- In beta: pages from other plugins in the account menu, admin tools and server pages, widgets from plugins as tiles, buttons plugins add to Pelican's server list and console, and overlapping plugins switching off only the matching Simplify part.
- Still to come: installing, updating and switching plugins on or off from Simplify's plugin manager.

Plugin support is also what makes the next step possible: letting Simplify replace the original panel completely.

### Next up

- A real phone experience. In beta: the overview, server page, sheets, creator, Pelican's own pages and host mode built for phones, and the panel installable as an app on your home screen.
- A better server page. A clear status bar at the top, a console that gets the space it needs, workspaces that open inside the page instead of popups, and one place for everything that needs your attention.
- Simplify as the whole panel. An option in setup that makes Simplify the only panel. The original stays reachable under `/legacy`, or switches off entirely when no other plugin needs it.
- Clearer server installation. One clear "setting up…" state until a new server is ready, and the Minecraft EULA accepted up front without a restart loop.
- Better server creation for every game. Recommended resources per game and an install progress card.
- Egg settings in the dashboard. Startup settings for every game, with plain descriptions and checks.

### Later

- **Alerts** for crashes and servers running out of resources.
- Startup fixes: recognize common startup problems and offer a one-click fix.
- Server presets: save settings once and reuse them for new servers.
- Troubleshooting reports: export diagnostics with all secrets removed, for easier support.
- More overview options: extra information fields. Layouts, columns, card widths, server order and your own groups are in beta.

See the [roadmap](docs/ROADMAP.md) for details.

## All features

Items marked *(Pelican)* are existing Pelican or Wings features that Simplify shows in its own interface. Everything else is added by Simplify.

<details>
<summary><strong>Overview and host mode</strong></summary>

- Grid and list views, remembered per user; phones use the list view automatically
- Server name, alias, icon, game, live status *(Pelican)*, players, and CPU and RAM graphs
- Filter by game; servers grouped by node when there is more than one
- Quick start, stop and restart buttons on each server
- Health panel per node: CPU, RAM, disk and network, with a warning when something is wrong
- Host mode: a server list per node, the admin pages in the middle, and servers that open next to the list

</details>

<details>
<summary><strong>Creating servers</strong></summary>

- Easy Minecraft server creator: a step-by-step wizard for Vanilla, Paper and modpacks (Modrinth, CurseForge, FTB and Technic)
- Easy server creator: search 170+ games from the official Pelican eggs; missing eggs are installed automatically
- Steam banners and icons for games, and automatic Steam game server tokens for games that need one
- Resource presets, per-user limits, a name generator *(Pelican)*, and server color and icon choice
- Drafts are saved automatically and can be discarded
- **Automatic subdomains** *(add-on, Cloudflare)*: choose a name such as `oak` in the creator or the server's Network section; Simplify creates the DNS records (a CNAME, plus an SRV record so Minecraft players need no port) and removes them with the server
- Pelican's own creator stays available

</details>

<details>
<summary><strong>Managing a server</strong></summary>

- Live console with command history *(Pelican)*
- Power controls and resource usage *(Pelican)*
- Minecraft settings with a live MOTD preview: players, difficulty, game mode, PvP, whitelist and more
- Player management: whitelist, operators, kicks and bans
- Plugins and mods from Modrinth: search, install, update and remove
- Change software, version or modpack, with a recent backup required first
- Files, schedules, activity log, databases and SFTP details *(Pelican)*
- Changes that need a restart are collected, with an option to restart now or later

</details>

<details>
<summary><strong>Backups</strong></summary>

- Automatic backups with your own interval and retention, and a pause button
- A failed backup never removes the last good one
- Restore and download *(Pelican)*

</details>

<details>
<summary><strong>Look and feel</strong></summary>

- Light, dark or system theme
- One accent color for the whole panel, or a color per server
- Banners: built-in Minecraft art, Steam art, or your own uploads
- Panel name and logo: upload a logo, or style the name with your own font and colors
- The same save bar on every settings page

</details>

<details>
<summary><strong>People, access and settings</strong></summary>

- Friends mode: see all servers, manage only your own or the ones shared with you *(Pelican permissions)*
- People & roles: add people with an email invite, and create roles with Simplify permissions, limits and admin access
- Switch individual features on or off for users: files, backups, schedules, players, plugins and more
- Optional self-service server creation, with limits per role or per person
- Steam account on the profile page for game server tokens

</details>

<details>
<summary><strong>Installation and maintenance</strong></summary>

- Installs through Pelican's own plugin import *(Pelican)*
- Setup checks for PHP, Node.js, the database, queue, storage and upload limits, each with fix instructions
- Plugin manager with safe updates: backup first, checksum check, and automatic rollback if an update fails
- Optional automatic plugin updates

</details>

## How this project is made

I came up with PelicanSimplify and designed every part of it myself: what it does, how it looks and how it should work. I write the code together with an AI assistant. Every change is checked and tested before it is released: automated tests run on each build, each change is tried in a test installation of Pelican, and I use the beta builds in my own Pelican panel. Still, it is a young project: expect the odd bug, especially in beta builds, and please report anything you find through [Issues](https://github.com/jerajerajerajera/PelicanSimplify/issues).

MIT licensed. Bundled game eggs keep their original licenses.

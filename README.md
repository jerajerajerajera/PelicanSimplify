# PelicanSimplify

Simplify is an all-in-one plugin for [Pelican](https://github.com/pelican/panel) that puts usability first. It is packed with features that make the panel easier to use, starting with one clear overview of your servers and a set of add-ons you switch on when you need them. Pelican and [Wings](https://github.com/pelican/wings) keep doing the work underneath, and the original panel stays available. See the full feature list below.

**Stable 0.5.2 for Pelican 1.0.0-beta38, 1.0.0-rc.1 and 1.0.0-rc.2 · PHP 8.3 to 8.5 (8.4+ on rc.1 and rc.2)** · [Compatibility](#compatibility)

*Screenshots use example servers.*

<a href="docs/screenshots/server-overview.png"><img src="docs/screenshots/server-overview.png" alt="Server overview" width="280"></a>
<a href="docs/screenshots/server-dashboard.png"><img src="docs/screenshots/server-dashboard.png" alt="Server dashboard" width="280"></a>
<a href="docs/screenshots/server-creator.png"><img src="docs/screenshots/server-creator.png" alt="Guided server creation" width="280"></a>
<a href="docs/screenshots/server-list.png"><img src="docs/screenshots/server-list.png" alt="List view grouped by node" width="280"></a>

## Add-ons

You can use all of Simplify, or pick Only the add-ons in the first setup. Pelican then keeps its own dashboard, server pages and look, and the add-ons you switch on appear in Pelican's menu under Simplify. Each add-on can be switched on or off by itself, and you can change your choice later in Simplify settings.

- Server Maker: create Minecraft servers (Vanilla, plugins, mods, modpacks or Bedrock) and 170+ other games step by step.
- Plugin manager: install, update and remove Pelican plugins, and browse the Hub. Updates roll back by themselves when something breaks.
- People & roles: invite people and decide on one page what each role may do.
- Automatic subdomains: give every server its own address. Simplify makes and removes the DNS records in Cloudflare.
- Error explanations: a server error explained in plain words, by Claude, GPT or Gemini with your own API key.
- Website embed: show your servers and their status on your own website with one line of code, or on a page of its own.
- Plugin sites: find and install Minecraft plugins from Modrinth, Hangar, SpigotMC and CurseForge on one page.

## What it does

Simplify puts all your servers on one page, with their status, players, CPU and RAM, as cards or a list. New servers are made with a step-by-step creator: pick Minecraft (Vanilla, Paper or a modpack) or one of 170+ other games, and Simplify installs the right egg.

For a few servers the simple mode is enough. Larger hosts can switch to host mode, with a server list per node and the admin pages next to it. You can choose a light or dark theme, give servers their own color and banner, and use your own logo or panel name. In friends mode everyone sees every server but only manages their own, and roles decide who may do what.

## Features

### Highlights

- Explain this error tells you in plain words what went wrong and what to try. It uses Claude, GPT or Gemini with your own API key.
- Add the panel to your home screen and use it as an app, with notifications for announcements and updates.
- Simplify Server Maker is one creator for Minecraft (Vanilla, plugins, mods, modpacks and Bedrock) and 170+ other games.
- Other Pelican plugins show up inside Simplify. On Minecraft servers you find and install plugins from Modrinth, Hangar and SpigotMC on one page.

### All features

Items marked *(Pelican)* are existing Pelican or Wings features that Simplify shows in its own interface. Everything else is added by Simplify.

<details>
<summary><strong>Overview and host mode</strong></summary>

- Cards, compact cards or a list, with 2 to 6 columns, three card widths and a full screen width option, remembered per user
- Server name, alias, icon, game, live status *(Pelican)*, players, and CPU and RAM graphs
- Your own server groups, each with its own colour, and your own server order, saved with your account
- Filter by game; administrators can also filter on All servers, My servers or Others
- Quick start, stop and restart buttons on each server: off, always on, or on hover
- Health per node: CPU, RAM, disk and network, as a Healthy button in the top bar or a bar above or below the servers, with a warning when something is wrong
- One request per node instead of one per server, so the overview stays fast
- Host mode: a server list per node, the admin pages in the middle, and servers that open next to the list

</details>

<details>
<summary><strong>Creating servers</strong></summary>

- Simplify Server Maker: one creator for Minecraft and 170+ other games from the official Pelican eggs; missing eggs are installed automatically
- Minecraft: Vanilla, Paper or Purpur for plugins, Fabric, Forge, NeoForge or Quilt for mods, the official Bedrock server, Velocity or BungeeCord as a proxy, and modpacks from Modrinth, CurseForge, FTB and Technic
- Steam banners and icons for games, and automatic Steam game server tokens for games that need one
- Resource presets, per-user limits, a name generator *(Pelican)*, and server color and icon choice
- Drafts are saved automatically and can be discarded
- An installation view with steps and the installation log, Kill to stop a first installation, and "Your server is ready" with a Start button
- The Minecraft EULA is accepted in the new server's setup view, without Pelican's popup
- Automatic subdomains *(add-on, Cloudflare)*: choose a name such as `oak` in the creator or the server's Network section; Simplify creates the DNS records (a CNAME, plus an SRV record so Minecraft players need no port) and removes them with the server
- Pelican's own creator stays available

</details>

<details>
<summary><strong>Managing a server</strong></summary>

- A compact header with the status, address and power buttons, a row with players and usage, and a console that fits the screen
- Live console with command history *(Pelican)*, which can be made bigger or opened in its own window, and a one-line mini console while a workspace is open
- Customize server page: full screen width and how you like the console, separate from the overview
- Power controls and resource usage *(Pelican)*
- Minecraft settings with a live MOTD preview: players, difficulty, game mode, PvP, whitelist and more
- Player management: whitelist, operators, kicks and bans
- Plugins on one page: search Modrinth, Hangar and SpigotMC (and CurseForge with a free API key) on the left, installed plugins on the right; mods from Modrinth
- Install, switch off, update and remove plugins and mods
- A plugin that fails to load at the next start is switched off automatically, with the reason and a ? to ask the AI why
- A plugin without a release for your Minecraft version can still be installed after a warning
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
<summary><strong>Announcements and notifications</strong></summary>

- Info, Warning or Success announcements with a start and end time, for everyone or administrators only, with an optional button to a server or a link
- Announcements show above the overview and server pages while they are live; unpin one to hide its bar, pin it again from the bell
- Administrators can pin an announcement to everyone's notifications
- In-game announcements: send to all servers, server groups or single servers, using each game's own chat command, optionally repeated
- Notices for new versions of Simplify, other plugins, Wings and Pelican, with a few lines of what is new and a link to the full notes
- A bell with all notifications, Mark all read, Clear read and an Archived list
- App notifications on your phone or computer, turned on per device, with a test button
- Times are entered and shown in your own time zone
- Announcements from the Hub plugin "Announcements" show too

</details>

<details>
<summary><strong>Explain this error</strong> (optional, with your own API key)</summary>

- An Explain button in the console checks the log first: "Everything seems in order" or "Something needs attention", with the explanation and steps to try
- Works with Claude, GPT or Gemini; a root administrator picks one and enters its API key, and each question is paid by that key
- Allow it for everyone who can see the console or for administrators only
- Root administrators can also check the panel log and the Wings log, and the admin menu has "Explain panel errors"
- IP addresses, e-mail addresses, passwords and tokens are removed before anything is sent
- Turn it on in Simplify settings → Error explanations

When the panel itself breaks, there are two other ways to ask:

- The panel shows an error page (such as 500), but you can still sign in: open `/simplify/explain` on your panel, for example `https://panel.example.com/simplify/explain`. This plain page does not need the normal panel pages, so it keeps working when a plugin, Filament or Livewire fails.
- You cannot sign in or the website is down: run `php artisan simplify:explain` in the panel folder on the server. Use `--source=wings --node=<id>` for a Wings log or `--source=server=<id>` for a server console. `php artisan simplify:explain --url` prints a temporary address for the plain page that works for an hour without signing in.

These need PHP and the panel's files to work. If even `php artisan` fails, the error it prints is usually the cause.

</details>

<details>
<summary><strong>Phones and the app</strong></summary>

- Every page works on phones, upright and on its side
- A tab bar at the bottom, compact server rows, a full-height console with quick commands, and popups that slide up as sheets
- Add the panel to your home screen and open it as an app

</details>

<details>
<summary><strong>Other Pelican plugins</strong></summary>

- Pages from other plugins in the account menu, admin tools and server pages
- Widgets from plugins as tiles, and buttons plugins add to Pelican's server list and console
- Each plugin's parts can be switched on or off inside Simplify
- When a plugin does the same as a part of Simplify, only that part of Simplify switches off
- Theme plugins such as Nord, Catppuccin and Theme Customizer: Simplify follows their colours, font and light or dark mode

</details>

<details>
<summary><strong>Look and feel</strong></summary>

- Light, dark or system theme
- One accent color for the whole panel, a color per server group, or a color per server
- Banners: built-in Minecraft art, Steam art, or your own uploads
- Panel name and logo: upload a logo, or style the name with your own font and colors
- The same icons as the rest of the Pelican panel, and the same save bar on every settings page
- Pelican's own pages inside Simplify (Eggs, Nodes, Servers, Settings and more) follow Simplify's look
- Halloween touches from 25 October to 2 November, easy to turn off

</details>

<details>
<summary><strong>Website embed</strong></summary>

- Your servers on your own website with one line of code, as an iframe, or on a page of its own
- Show every server, one server, one game or one server group, with a live preview in Settings
- Only name, game, modpack, status, players and a public join address are shown; private and Tailscale addresses never are
- Nothing is published until you switch a server on, in Settings or with "Show on the website" in the server menu

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
- Egg updates: Simplify checks every egg for a new version and tells root admins. Update one or all, update eggs without servers automatically, or test a new egg on a temporary server first
- Every egg update keeps the previous version, so you can put it back, and Move servers brings existing servers to the new startup command or image
- Only the add-ons: keep Pelican's own panel and use just the add-ons you pick
- Stable, Beta and Snapshot update channels, and optional automatic plugin updates

</details>

## Before you install

Simplify is a large change to how your panel looks and works. Please read this first.

- It replaces a lot of the panel. The server overview, the server pages and server creation become Simplify's. Optionally it also takes over the Users, Roles and Plugins pages, and admins can use host mode. If you only want the add-ons, pick Only the add-ons in the setup and Pelican keeps its own look.
- Everything can be switched off. Each part has its own switch in Simplify settings, and you can turn off the whole Simplify dashboard. Pelican's original panel always stays available: the Legacy links in Admin tools lead to it, and even if you hide those links the pages keep working.
- It does not touch your running servers. Servers, eggs, nodes and permissions stay Pelican's own. Simplify is a different way to look at and manage them.
- Other plugins are partly supported inside Simplify. They keep working in Pelican's original panel. Simplify shows their admin and server pages under "External", and their account pages, widgets and buttons inside its own pages.
- **Make a backup before installing**, as with any plugin that changes this much.

## Getting started

1. In Pelican, go to **Admin → Plugins → Import from URL**.
2. Paste the link to the [latest stable plugin ZIP](https://github.com/jerajerajerajera/PelicanSimplify/releases/download/v0.5.2/pelican-simplify-v0.5.2.zip) and click **Install**. All versions are on the [Releases page](https://github.com/jerajerajerajera/PelicanSimplify/releases).

   **Beta and snapshot builds:** the newest beta is always at `https://raw.githubusercontent.com/jerajerajerajera/PelicanSimplify/beta/dist/pelican-simplify-beta-latest.zip`, the newest snapshot at `https://raw.githubusercontent.com/jerajerajerajera/PelicanSimplify/snapshot/dist/pelican-simplify-snapshot-latest.zip`. Betas test the next stable release with all its new features; snapshots are development builds with the latest changes. Both are less tested; see the channel table below.
3. Follow the setup checks. Anything missing is shown with how to fix it.

| Channel | Current version | What it has | Changelog |
|---|---|---|---|
| **Stable** (default) | 0.5.2 | Everything under All features below | [CHANGELOG](CHANGELOG.md) |
| **Beta** | 0.5.3-beta.3 | The next stable release, tested before it ships: stable plus a Customize menu for server pages, pinned announcements and Explain this error | [beta changelog](https://github.com/jerajerajerajera/PelicanSimplify/blob/beta/CHANGELOG.md) |
| **Snapshot** | 0.5.3-snapshot.4 | The newest development build; at the moment the same features as beta | [snapshot changelog](https://github.com/jerajerajerajera/PelicanSimplify/blob/snapshot/CHANGELOG.md) |

### Compatibility

| Pelican | PHP | Stable 0.5.1 |
|---|---|---|
| 1.0.0-beta38 | 8.3 | ✅ Tested, works |
| 1.0.0-beta38 | 8.4 | ✅ Works |
| 1.0.0-beta38 | 8.5 | ✅ Works |
| 1.0.0-rc.1 | 8.4 | ✅ Tested, works |
| 1.0.0-rc.1 | 8.5 | ✅ Tested, works |
| 1.0.0-rc.2 | 8.4 | ✅ Tested, works |
| 1.0.0-rc.2 | 8.5 | ✅ Tested, works |

Pelican 1.0.0-rc.1 and rc.2 need PHP 8.4 or newer. "Tested" means a fresh panel with that Pelican and PHP version, tested with 0.5.1: Simplify installed, the panel started and every Simplify page opened and worked without errors, on desktop and on phones. Pelican 1.0.0-beta38 with PHP 8.4 and 8.5 was tested the same way with the 0.5.0 betas. On a Pelican release that is not in this table (a newer one, or a panel installed from git that reports "canary"), the setup check "Tested Pelican release" shows a warning and installing continues.

**Requirements:** Node.js 22.12+ or 24+, Yarn, and a running queue worker and scheduler.

**Updates:** Simplify updates itself through its plugin manager, with a backup and automatic rollback. You always get the build made for your Pelican version. Stable releases are the default; choose Beta or Snapshot under "Simplify versions" in Simplify settings → Plugin manager to try new features earlier.

Coming from the standalone creator? See the [installation notes](docs/INSTALLATION.md). Found a problem? Open an [issue](https://github.com/jerajerajerajera/PelicanSimplify/issues).

## Coming features

The order and scope may still change. Everything Simplify does not cover yet is still available in the original Pelican panel.

### Top priority: plugin support

Simplify should work together with other Pelican plugins instead of beside them:

- Done in 0.5.1: pages from other plugins in the account menu, admin tools and server pages, widgets from plugins as tiles, buttons plugins add to Pelican's server list and console, and overlapping plugins switching off only the matching Simplify part.
- Still to come: installing, updating and switching plugins on or off from Simplify's plugin manager.

Plugin support is also what makes the next step possible: letting Simplify replace the original panel completely.

### Next up

- A better server page. Done in 0.5.1: a compact header, a stats row and a console that fits the screen. Still to come: workspaces that open inside the page instead of popups, and one place for everything that needs your attention.
- Simplify as the whole panel. An option in setup that makes Simplify the only panel. The original stays reachable under `/legacy`, or switches off entirely when no other plugin needs it.
- Better server creation for every game. Recommended resources per game.
- Egg settings in the dashboard. Startup settings for every game, with plain descriptions and checks.

### Later

- **Alerts** for crashes and servers running out of resources.
- Startup fixes: recognize common startup problems and offer a one-click fix.
- Server presets: save settings once and reuse them for new servers.
- Troubleshooting reports: export diagnostics with all secrets removed, for easier support.
- More overview options: extra information fields.

See the [roadmap](docs/ROADMAP.md) for details.

## How this project is made

I came up with PelicanSimplify and designed every part of it myself: what it does, how it looks and how it should work. I write the code together with an AI assistant. Every change is checked and tested before it is released: automated tests run on each build, each change is tried in a test installation of Pelican, and I use the beta builds in my own Pelican panel. Still, it is a young project: expect the odd bug, especially in beta builds, and please report anything you find through [Issues](https://github.com/jerajerajerajera/PelicanSimplify/issues).

MIT licensed. Bundled game eggs keep their original licenses.

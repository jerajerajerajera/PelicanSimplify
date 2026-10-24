# PelicanSimplify — handoff

A summary of the project, how we work on it and where things stand, so work can continue in a new conversation or Claude project. Last updated: 24 October 2026 (stable 0.4.7, beta 0.5.0-beta.4, snapshot 0.5.0-snapshot.9).

## The project in short

PelicanSimplify is a plugin for the [Pelican](https://github.com/pelican-dev/panel) game server panel. It replaces the server overview, the server pages and server creation with a simpler, more modern interface. Everything still runs on Pelican and Wings, and the original panel stays available through Legacy links.

- **Repository:** `jerajerajerajera/PelicanSimplify` (public, MIT). `main` holds stable releases; development happens on `snapshot` and `beta` (see below), through feature branches and PRs.
- **Owner:** designs everything and tests on their own panel. Talks in **Dutch** and prefers clear, short answers in Dutch. Code, commits, CHANGELOG and README are in English.
- **Stack:** Pelican 1.0.0-beta38 (Filament 5.7, Livewire 4, Laravel 13, PHP 8.3) and Pelican 1.0.0-rc.1 (Filament 5.10, PHP 8.4). Frontend: Blade + Alpine.js, plain CSS (no build step for the plugin).

## Versions and update channels

Three channels, each on its own branch (decided 20 October 2026):

| Channel | Branch | Update file | Versions | Latest zip |
|---|---|---|---|---|
| Stable | `main` | `update.json` (the Pelican Hub syncs `main`) | `0.4.7` | `dist/pelican-simplify-latest.zip` + GitHub Release |
| Beta | `beta` | `update-beta.json` | `0.5.0-beta.N`, `0.5.0-rc.N` | `dist/pelican-simplify-beta-latest.zip` |
| Snapshot | `snapshot` | `update-snapshot.json` | `0.5.0-snapshot.N` | `dist/pelican-simplify-snapshot-latest.zip` |

- **Current:** stable 0.4.7, beta 0.5.0-beta.4 and snapshot 0.5.0-snapshot.9, all for Pelican 1.0.0-beta38, 1.0.0-rc.1 and 1.0.0-rc.2. On an untested Pelican release the setup check warns instead of blocking (from 0.4.7 and 0.5.0-beta.4). Beta 0.5.0-beta.3 was tested on fresh panels: beta38 with PHP 8.3, 8.4 and 8.5, rc.1 and rc.2 with PHP 8.4 and 8.5 (the compatibility table in the README and INSTALLATION). The beta adds Simplify on phones, the panel as an installable app and support for other plugins.
- **Documentation on `main`:** the README's "Now in beta" section and channel table, CHANGELOG, ROADMAP and these docs are kept up to date on `main` (documentation only, no version or plugin files). The CHANGELOG on `main` has one summary entry per beta that links to the full beta changelog; when the beta becomes stable, that summary is replaced by the stable entry.
- **Pelican Hub:** plugin.json's `update_url` must be `https://hub.pelican.dev/plugins/pelican-simplify/update.json` (the Hub refuses a sync otherwise), so Pelican's own updater reads the Hub. Simplify's plugin manager also reads the GitHub files above (`PluginUpdater::channelUrls`) and offers the newest. After a stable release on `main`, the owner clicks "sync" in the Hub.
- **`main` holds stable releases only.** The Pelican Hub reads the repository, so a beta or snapshot version on `main` would show up there.
- **Channel choice:** Simplify settings → Plugin manager → "Simplify versions": Stable (default), Beta or Snapshot. Stored as the `simplify_beta` and `simplify_snapshot` switches. A panel gets the newest of its channel and every more stable one (`PluginUpdater::channelUrls`).
- **Bridge for old betas:** installations up to 0.4.2-beta.7 read `main/update-beta.json`. A stable build writes that file too, so they move to the stable release, whose code then reads the beta branch.
- Version order (PHP `version_compare`): `0.5.0-snapshot.N < 0.5.0-beta.N < 0.5.0-rc.N < 0.5.0`.

## How a release is made

1. Work on a feature branch and open a PR into `snapshot`.
2. Bump the version in `plugin.json`, `package.json` and `package-lock.json` (lines 3 and 9).
   - The version decides the channel: `-snapshot.N`, `-beta.N`/`-rc.N`, or none for stable.
3. Add an entry at the top of `CHANGELOG.md`.
4. `python3 scripts/build-package.py`. It builds `dist/pelican-simplify-v<version>.zip` and writes the channel's update file with download links on the channel's branch, updates the latest zip, removes zips this branch no longer serves and refreshes `SHA256SUMS`. A stable build also writes `update-beta.json`, `dist/pelican-simplify-latest.zip` and the README install link. Supported Pelican versions are in `SUPPORTED_PANELS`.
5. Run all checks and **check every exit code**, chained with `&&`. `tests/run.sh` includes `tests/hub-check.py`, which runs the Pelican Hub's checks (banned file and process calls in every PHP file of the repository, tests and helper scripts included, as the Hub scans the whole repository; required plugin.json fields read from pelican-dev/docs; update_url on the Hub):
   - `python3 tests/package-layout.py && bash tests/run.sh && PHP_BIN=php8.4 bash tests/run.sh && PW_CHROMIUM=/opt/pw-browsers/chromium node tests/theme-browser.js`
6. Commit with the attribution lines the session asks for and push to the channel's branch (via a PR, with the owner's approval).
7. **Promote:** PR `snapshot` → `beta` for a beta (version `-beta.1`), PR `beta` → `main` for stable. After a stable release, merge `main` back into `beta` and `snapshot` and continue with the next `-snapshot.1`.
8. Stable releases only: the `Release` workflow (`.github/workflows/release.yml`) creates GitHub Release `v<version>` with the zip and the CHANGELOG section, marked latest, as soon as the stable version is on `main`. It skips versions that already have a release and can be started by hand under Actions. (Pushing tags from the cloud environment is refused, so this is how releases are made.)

## Architecture (main files)

- `src/PelicanSimplifyPlugin.php` registers the pages per panel (app, admin, server).
- **Pages:**
  - `src/Filament/App/Pages/Dashboard.php`: the whole Simplify interface. Its `initial()` provides the boot data.
  - `src/Filament/App/Pages/CreateServer.php`
  - `src/Filament/Admin/Pages/SimplifySettings.php`: settings tabs.
  - `src/Filament/Admin/Pages/CreateMinecraftServer.php`: both creators.
  - `src/Filament/Admin/Pages/ManagePlugins.php`
  - `src/Filament/Admin/Pages/ManagePeople.php`
  - `src/Filament/Profile/SteamProfileTab.php`
- **Services:** `src/Services/`. The most important ones:
  - `FeatureSettings`: all on/off switches.
  - `AppearanceSettings` + `BrandSettings`: colours, logo, panel name.
  - `AdminPages`: admin menu and host mode.
  - `ExternalPlugins`: other plugins' pages and the conflict list.
  - `PluginUpdater`: safe updates and channels.
  - `GameCatalog` / `OtherGameCatalog`: game creator.
  - `SteamAccount` / `SteamArt`
  - `PeopleManager` / `RoleAccess`
  - `ServerArtwork` / `ServerBanners`: banners, including colour banners.
  - `DependencyCheck`: setup checks.
- **Views:** `resources/views/dashboard.blade.php` (everything in one Alpine component), `admin-settings.blade.php`, `admin-plugins.blade.php`, `admin-people.blade.php`.
- **JS:**
  - `resources/views/js/dashboard.js`: the Alpine component `pelicanSimplify`.
  - `resources/views/js/core.js`: shared UI such as the save bar, dialogs, tooltips and `brandTextStyle`.
- **CSS:** `resources/views/css/tokens.css`, `components.css`, `dashboard.css`, `native.css` (styling of embedded Pelican pages), `wizard.css` (settings and creators).
- `conflicts.json`: plugins that clash with Simplify (`plugins`, currently empty). From 0.5.0 also `overlaps`: plugins that do part of what Simplify does, with the Simplify `features` that switch off while they are enabled (Subdomains switches off automatic subdomains). It is also fetched hourly from GitHub `main`, so it can be changed without a release; older versions ignore `overlaps`.

## What the plugin does now (short)

- **Overview:**
  - Grid or list view. Create buttons and filters on the toolbar row.
  - Grouping by node (Auto/Always/Off).
  - Host health tile.
  - "Customize overview" menu (simple mode only).
- **Host mode:** for admins. A server list per node and the admin menu with pages in a pane. Clicking a server opens its page in the pane; an arrow or ✕ closes it.
- **Server page:**
  - Banner (image, colour or broken heart when offline).
  - Menu on the left; sections open in the middle while the console shrinks to the bottom right.
  - Power, players and resources on the right.
  - A status bar appears when you scroll.
- **Creators:** Easy Minecraft server creator, and the Easy server creator with 170+ games. Includes Steam art and automatic Steam tokens.
- **Appearance:** themes, accent colour, panel name/logo editor, automatic scaling (1440p = 100%, about 80% on 1080p).
- **Account menu:** profile, theme (unfolds Light/Dark/System), host/simple switch, sign out.
- **People & roles, plugin manager:** safe updates with rollback; update channels.
- **Other plugins:**
  - Their admin pages appear under "External" in Admin tools and host mode, and their server pages under "External" in the server page menu.
  - Per page you can show or hide them (Settings → General → "Pages from other plugins").
  - Plugins on the conflict list switch the Simplify dashboard off, with an explanation.
  - Tested with Tickets and Legal Pages from `pelican-dev/plugins`.
  - In the 0.5.0 betas: their account pages in the account menu, their widgets as tiles, their buttons on the server list and console, and overlapping plugins switch off only the matching Simplify feature.
- **Phones (0.5.0 betas):** the overview, the server page (four tabs, quick commands), popups as sheets, the creator, Pelican's embedded pages and host mode have their own layout below 600 px wide; the panel is an installable web app with an offline page.

## Open items and ideas

- **Plugin support:** account pages, widgets, buttons and overlaps are done in the 0.5.0 betas. Still open: installing, updating and switching plugins from Simplify's plugin manager.
- **Phones:** done in the 0.5.0 betas (overview, server page, sheets, creator, embedded Pelican pages, host mode, installable web app).
- **Optional:** a manual "interface size" choice next to the automatic scaling.
- **Roadmap** (README "Coming features" and `docs/ROADMAP.md`): Simplify as the whole panel, clearer installation, egg settings, alerts, presets.
- **Pelican Hub:** it reads the repository, so `main` must stay stable-only.

## Notes on the owner's own panel

- Server `deckzwabbers` (Ubuntu 24.04), user `dz_admin`, local address `192.168.1.3`. SSH is key-only.
- nginx listens on the Tailscale address `100.72.58.65`: the panel on port 80, the site `deckzwabbers` on port 8081. `APP_URL=http://100.72.58.65`, so the panel only works properly through that address.
- At a friend's place, extra `listen 192.168.1.3:...` lines were added to `/etc/nginx/sites-enabled/pelican` and `deckzwabbers`. They can be removed again with:
  `sudo sed -i '/listen 192.168.1.3:/d' /etc/nginx/sites-enabled/deckzwabbers /etc/nginx/sites-enabled/pelican && sudo nginx -t && sudo systemctl reload nginx`
- If `APP_URL` was temporarily changed to `192.168.1.3`, switch it back to `http://100.72.58.65` and run `php artisan optimize:clear`. The backup is in `/var/www/pelican/.env.backup`.
- The friend's PC key (`thier@MrThierryH`) was added to `dz_admin`'s `authorized_keys`. Remove it there if that access is no longer wanted.

## Test environment (for whoever continues in the cloud)

The earlier sessions used a local Pelican beta38 test installation with fake Wings, Playwright scripts and demo data for screenshots. That environment lived in a temporary folder and is gone in a new session. The automated tests in `tests/` do work anywhere. For a new test installation, set up Pelican beta38 (or rc.1 with PHP 8.4), copy the plugin in and install it with `php artisan p:plugin:install pelican-simplify`.

**Pelican rc.1 in the cloud (worked on 20 October 2026):** PHP 8.4 comes from the ondrej/php PPA (add its key from keyserver.ubuntu.com by hand; `add-apt-repository` is broken there). Clone `pelican-dev/panel` at tag `v1.0.0-rc.1` with git (GitHub downloads are blocked, so `composer install` falls back to git sources and takes a few minutes), run composer with `php8.4`, set `'version' => '1.0.0-rc.1'` in `config/app.php` (a git checkout says "canary"), use SQLite and `QUEUE_CONNECTION=database`, run `yarn install`, then `php8.4 artisan migrate` and `p:plugin:install pelican-simplify`. Create the first admin with `User::withoutEvents()`, because Simplify only lets administrators create accounts.

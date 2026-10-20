# PelicanSimplify — handoff

A summary of the project, how we work on it and where things stand, so work can continue in a new conversation or Claude project. Last updated: 20 October 2026.

## The project in short

PelicanSimplify is a plugin for the [Pelican](https://github.com/pelican-dev/panel) game server panel. It replaces the server overview, the server pages and server creation with a simpler, more modern interface. Everything still runs on Pelican and Wings, and the original panel stays available through Legacy links.

- **Repository:** `jerajerajerajera/PelicanSimplify` (public, MIT). Work happens on `main`; the working branch so far was `claude/simplify-plugin-beta-review-vcj589` (kept equal to `main`).
- **Owner:** designs everything and tests on their own panel. Talks in **Dutch** and prefers clear, short answers in Dutch. Code, commits, CHANGELOG and README are in English.
- **Stack:** Pelican 1.0.0-beta38 (Filament 5.7, Livewire 4, Laravel 13, PHP 8.3) and Pelican 1.0.0-rc.1 (Filament 5.10, PHP 8.4). Frontend: Blade + Alpine.js, plain CSS (no build step for the plugin).

## Versions and update channels

| | Stable channel (`update.json`) | Beta channel (`update-beta.json`) |
|---|---|---|
| Pelican 1.0.0-beta38 | **0.4.0** | **0.4.1-beta.7** |
| Pelican 1.0.0-rc.1 | — (none yet) | **0.4.1-beta.7** |

- **0.4.0** is the first stable release, published as a GitHub Release (tag `v0.4.0`, zip attached).
- **Download links:**
  - Stable: `https://github.com/jerajerajerajera/PelicanSimplify/releases/download/v0.4.0/pelican-simplify-v0.4.0.zip`. The build script updates this README link on each stable release.
  - Beta: `https://raw.githubusercontent.com/jerajerajerajera/PelicanSimplify/main/dist/pelican-simplify-beta-latest.zip`.
- **Channel choice:** Simplify settings → Plugin manager → "Get Simplify beta versions" (default off). Pelican's own updater always reads `update.json`, which is stable.
- **Next step:** test 0.4.1-beta on a real rc.1 panel. Rc.1 needs PHP 8.4, which was not available in the cloud test environment. Then release **0.4.1** as stable for both Pelican versions.

## How a release is made

1. Bump the version in `plugin.json`, `package.json` and `package-lock.json` (lines 3 and 9).
   - Beta versions look like `0.4.1-beta.N`.
   - A version without `-` is stable.
2. Add an entry at the top of `CHANGELOG.md` (`## 0.4.1-beta.N (beta channel)` + bullets).
3. `python3 scripts/build-package.py`. It builds `dist/pelican-simplify-v<version>.zip` and:
   - always writes `update-beta.json` and `dist/pelican-simplify-beta-latest.zip`;
   - for stable versions, also writes `update.json` and `dist/pelican-simplify-latest.zip`, and updates the README install link;
   - removes zips that are no longer on any channel;
   - refreshes `SHA256SUMS`.
   - Supported Pelican versions are listed in `SUPPORTED_PANELS` in that script.
4. Run all checks, and **check every exit code** (a hidden failure once went out with a release):
   - `python3 tests/package-layout.py`
   - `bash tests/run.sh` (PHP/JS unit tests; files load individually with stubs, so new classes need a `class_exists` guard or a `require` in the test)
   - `PW_CHROMIUM=/opt/pw-browsers/chromium node tests/theme-browser.js` (browser tests with Playwright)
   - Chain them with `&&` so a failure stops the push.
5. Commit with the attribution lines the session asks for. Push to the working branch **and** to `main`.
6. Stable releases only: the owner creates the GitHub Release by hand.
   - Releases → Draft a new release → tag `v<version>` on `main`.
   - Attach the zip and mark it as **latest**, not pre-release.
   - Pushing tags from the cloud environment is refused (403).
7. Never open a pull request unless the owner asks.

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
- `conflicts.json`: plugins that clash with Simplify. Currently empty. It is also fetched hourly from GitHub `main`, so it can be changed without a release.

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

## Open items and ideas

- **Rc.1 test + 0.4.1 stable.**
- **Plugin support, next steps:**
  - App-panel pages from plugins in the account menu (for example Legal Pages).
  - Widgets from plugins as tiles.
  - Check overlap with the official plugins `minecraft-modrinth`, `subdomains` and `user-creatable-servers`.
- **Optional:** a manual "interface size" choice next to the automatic scaling.
- **Roadmap** (README "Coming features" and `docs/ROADMAP.md`): better phone experience, Simplify as the whole panel, clearer installation, egg settings, alerts, presets.
- **Pelican Hub:** publish once 0.4.1 is stable. Submission requirements still need to be checked on `hub.pelican.dev`.
- **When importing a plugin over an existing installation:** rc.1 runs the install again; Simplify's own plugin manager does not do that yet.

## Notes on the owner's own panel

- Server `deckzwabbers` (Ubuntu 24.04), user `dz_admin`, local address `192.168.1.3`. SSH is key-only.
- nginx listens on the Tailscale address `100.72.58.65`: the panel on port 80, the site `deckzwabbers` on port 8081. `APP_URL=http://100.72.58.65`, so the panel only works properly through that address.
- At a friend's place, extra `listen 192.168.1.3:...` lines were added to `/etc/nginx/sites-enabled/pelican` and `deckzwabbers`. They can be removed again with:
  `sudo sed -i '/listen 192.168.1.3:/d' /etc/nginx/sites-enabled/deckzwabbers /etc/nginx/sites-enabled/pelican && sudo nginx -t && sudo systemctl reload nginx`
- If `APP_URL` was temporarily changed to `192.168.1.3`, switch it back to `http://100.72.58.65` and run `php artisan optimize:clear`. The backup is in `/var/www/pelican/.env.backup`.
- The friend's PC key (`thier@MrThierryH`) was added to `dz_admin`'s `authorized_keys`. Remove it there if that access is no longer wanted.

## Test environment (for whoever continues in the cloud)

The earlier sessions used a local Pelican beta38 test installation with fake Wings, Playwright scripts and demo data for screenshots. That environment lived in a temporary folder and is gone in a new session. The automated tests in `tests/` do work anywhere. For a new test installation, set up Pelican beta38 (or rc.1 with PHP 8.4), copy the plugin in and install it with `php artisan p:plugin:install pelican-simplify`.

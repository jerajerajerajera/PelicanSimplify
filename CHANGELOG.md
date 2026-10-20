# Changes

## 0.4.2-beta.2 (beta channel)
- Fixed: opening the panel's home page (`/`) on Pelican 1.0.0-beta38 and 1.0.0-rc.1 showed Pelican's own server list instead of the Simplify dashboard, and that list missed the "Go to Simplify Panel" button. Simplify looked for the list under the wrong route name; it now asks Pelican for it, so `/` goes straight to Simplify. Pelican's list stays reachable with `?simplify_legacy=1`.

## 0.4.2-beta.1 (beta channel)
- **Automatic subdomains (add-on, off by default):** a server can get its own address, such as oak.example.com, chosen in the creator or in the server's Network section. Simplify creates a DNS-only CNAME to your game address in Cloudflare, plus an SRV record for Minecraft so Java players can leave out the port, and removes them when the server is deleted. Set the domain, game address and forwarded port range under Simplify settings → Automatic subdomains; the Cloudflare token (DNS edit only) and zone ID are read from the panel's .env (CLOUDFLARE_API_TOKEN, CLOUDFLARE_ZONE_ID) and never stored or shown. Records Simplify did not create are never changed. Cloudflare is called after the page has responded, and `simplify:subdomains` (every five minutes) retries failures and follows port changes.
- The subdomain starts as the server name made DNS-safe ("Oak Survival" becomes oak-survival, with a number when taken), in the creator and in the Network section; it follows the name until you type your own.

## 0.4.1-rc.2 (beta channel) — second release candidate for 0.4.1
Same as rc.1 plus one fix. Still supports Pelican 1.0.0-beta38 and 1.0.0-rc.1.
- Fixed: player counts and the MOTD were missing on the dashboard for servers on a 0.0.0.0 or :: allocation when the node's FQDN points at an HTTP-only proxy such as Cloudflare. Those servers are now pinged on the local machine first, then on the allocation alias (when it is a real host name or IP), then on the node's FQDN.

## 0.4.1-rc.1 (beta channel) — release candidate for 0.4.1
The first release candidate for 0.4.1: the 0.4.1 betas bundled, plus one small fix. If no problems turn up, 0.4.1 becomes the next stable release. Changes since stable 0.4.0:
- **Pelican 1.0.0-rc.1 support:** loads on Pelican 1.0.0-rc.1 as well as 1.0.0-beta38 (plugin.json declares "^1.0.0-beta38"), system checks accept rc.1, and server addresses respect rc.1's "hide port" allocation setting. (beta.1)
- **Pages from other plugins:** admin pages that other Pelican plugins add are listed under "External" in Admin tools and host mode; their server pages appear on Simplify's server page under "External" and open in the middle block, styled to match. Simplify settings → General → "Pages from other plugins" chooses which ones show. Tested with Tickets and Legal Pages. (beta.4, beta.5, beta.7)
- **Known conflicts:** conflicts.json (bundled, refreshed hourly from GitHub) lists plugins that clash with Simplify; while one is enabled Simplify switches its own dashboard off and explains why. The list currently starts empty. (beta.4, beta.6)
- **Interface scaling:** designed for 1440p, Simplify scales down in proportion on smaller windows (about 80% on 1080p); phones keep their own layout. (beta.3)
- **Overview:** the create buttons always sit first in the toolbar, also with the node filter shown. (beta.2)
- Fixed in this rc: a skipped layout animation on the server page no longer leaves an error in the browser console (seen with newer Chrome versions).

## 0.4.1-beta.7 (beta channel)
- Simplify settings → General → "Pages from other plugins": a checkbox per page that other plugins add to the admin panel or to servers. Unticked pages are hidden from Simplify's External sections; they stay available in Pelican's own panel.

## 0.4.1-beta.6 (beta channel)
- The conflict list starts empty: Essentials (Essentials Core) no longer exists, and a name match could hit an unrelated future plugin.

## 0.4.1-beta.5 (beta channel)
- Server pages from other plugins appear on the server page, in the menu under "External", and open in the middle block like Simplify's own sections (styled to match; the console moves to the side). Tested with the Tickets plugin.

## 0.4.1-beta.4 (beta channel)
- Other plugins: admin pages added by other Pelican plugins are recognised (by the plugin's namespace) and listed under "External" in Admin tools and in host mode, with the plugin's name. Tested with Tickets and Legal Pages from the official plugin repository.
- Known conflicts: conflicts.json (bundled, and refreshed hourly from GitHub so it can grow without a release) lists plugins that clash with Simplify. While one is enabled, Simplify switches its own dashboard off so everyone gets Pelican's own panel, and Simplify settings and the plugin page explain why. Settings are kept; removing the plugin turns Simplify back on.

## 0.4.1-beta.3 (beta channel)
- Interface scaling: Simplify is designed for a 1440p screen and now scales down in proportion on smaller windows (about 80% on a 1080p screen), so everything fits without scrolling. Phones keep their own layout; the host mode pane scales with the page around it.

## 0.4.1-beta.2 (beta channel)
- Overview: the create buttons always sit first on the left of the toolbar, also when the node filter is shown.

## 0.4.1-beta.1 (beta channel) — Pelican 1.0.0-rc.1 support
- Loads on Pelican 1.0.0-rc.1 as well as 1.0.0-beta38: plugin.json declares "^1.0.0-beta38" instead of pinning one exact release (Pelican refuses an exact pin on any other version).
- The beta channel announces this build for both Pelican releases.
- System checks accept 1.0.0-rc.1 as a supported release.
- Server addresses respect rc.1's "hide port" allocation setting (display address), falling back to the plain address on beta38.

## 0.4.0 — first stable release (Pelican 1.0.0-beta38)
- Everything from the 0.3.0 betas, now marked stable for Pelican 1.0.0-beta38.
- Update channels: stable releases are announced in update.json (also what Pelican's own updater reads), betas in update-beta.json. Simplify settings → Plugin manager → "Get Simplify beta versions" (off by default) follows the beta channel; you then get whichever of the stable or beta build is newer.
- Updates are always the build made for your Pelican version; the plugin page states your Pelican version and channel.
- The README download link (pelican-simplify-latest.zip) now always points at the latest stable release.

## 0.3.0-beta.53
- Host mode pane: the server page fills it edge to edge. The pane has no frame or padding of its own; the banner, menu, console and side cards form its edges.

## 0.3.0-beta.52
- Account menu: the theme button unfolds into Light, Dark and System; picking one switches the theme and folds it back.

## 0.3.0-beta.51
- Account menu: it now opens wide enough for all buttons: profile, theme, host/simple mode and sign out (three for non-admins and on server pages).

## 0.3.0-beta.50
- Header menus (admin tools, notifications, customize overview) now point at the button that opened them, with the same small arrow as incoming notifications.

## 0.3.0-beta.49
- Simplify settings in the new style: one rounded card that fits the window. The menu on the left stays put and only the settings on the right scroll. Menu items have coloured icons and a tinted active state, and long add-on names are no longer cut off.
- Customize overview: "Group by node" (Auto / Always / Off) is always shown, also with a single node.

## 0.3.0-beta.48
- Overview: grid view is always four tiles wide, the same width as list view, and the health tile follows it. Cards keep their size with fewer servers.

## 0.3.0-beta.47
- Customize overview, redone: every option has a clear name and a one-line explanation, with its control on the right (Layout, Card icon, Card title, Group by node), then "Show on each card" switches: Message of the day, CPU usage, Memory usage and Quick actions on hover.
- Account menu: theme, profile, the host/simple mode switch (its icon shows the mode you switch to) and sign out, in that order.

## 0.3.0-beta.46
- "Customize overview" (was "Customize appearance") only appears on the simple-mode overview, in a calmer new style: rounded segmented controls and the card details as two columns of switches.
- Theme and the simple/host mode switch moved to the account menu, so they stay available on every page.

## 0.3.0-beta.45
- Overview: a small gap between the fading server list and the health tile, so no card peeks past the tile corners.

## 0.3.0-beta.44
- Host mode pane: the server card sits flush at the top of the pane, and the workspace gets exactly the height that is left, so the pane no longer scrolls (also with a section open; the small console then takes the players card's place).
- Files: a button in the file manager header opens it in a separate window.
- Overview: the server list fades out softly above the health tile instead of ending in a hard edge.

## 0.3.0-beta.43
- Overview: the Host health tile is always at least three tiles wide again; with fewer servers the cards keep their normal size instead of stretching.

## 0.3.0-beta.42
- Create buttons: the shine now sweeps only inside the icon and no longer runs over the label.

## 0.3.0-beta.41
- Phones: resources show as a tidy 2 × 2 grid (no more squeezed network text); the status bar shortens the name before the buttons and drops the player count on narrow phones; the server banner puts its icons in a row and uses the full width for the text.
- Host mode pane: the console fills the height of the pane instead of a fixed tall block.

## 0.3.0-beta.40
- Host mode server pane: the header bar is gone, so the server page fills the whole pane. Open-full-page and close float at the top right. The open server in the list shows a small arrow pointing at the pane; clicking it closes the server.
- Create buttons: slightly bigger in both views, with a larger plus. The shine stays on the icon only; the label is plain text.
- Fixed: a confirmation (for example Kill) could open invisibly right after closing a section.

## 0.3.0-beta.39
- Overview: the create buttons moved out of the grid into the toolbar, on the same row as the game filters, in both grid and list view. Every tile in the grid is now a server, all the same size.
- Cards in simple and host mode match the new server page: rounder corners, a softer accent border that brightens on hover, and rounder icons.
- Server page: scrolling with the mouse over an open section (such as Game settings) scrolls the page again once the section itself has nothing left to scroll.
- Host mode server pane: no floating status bar inside the pane (its header already shows the server), and the section menu sticks to the top of the pane.

## 0.3.0-beta.38
- Colour banners: besides images, a server banner can be a colour. "Accent colour" picks a neighbouring hue of the server accent that stands out but matches; "Your own colour" lets you choose one. Both fade in from the middle to the right, on the server page and the overview cards.
- The broken heart on an offline server page fades in from the middle and tilts off the bottom-right edge of the card.

## 0.3.0-beta.37
- New server page layout. The banner stays; the tiles became a colour-coded section menu on the left; power, players and resources stay visible on the right.
- Sections open inside the page instead of as popups. While one is open, the console shrinks into a read-only view under the resources, so its output is kept. Closing a section (✕ or "Console" in the menu) brings the console back to the middle, animated.
- The console shows it is live: when connected, the dot ripples, small bars move and the status turns green.
- Phones: the menu becomes a scrollable row under the power buttons.

## 0.3.0-beta.36
- List view: the create buttons use the new icon style too, side by side on the left. The label slides out on hover.

## 0.3.0-beta.35
- Server page: a status bar slides in at the top once you scroll past the server card. It keeps the name, status, address (click to copy), players and the start, restart, stop and kill buttons in reach.

## 0.3.0-beta.34
- Simple mode create buttons read clearly as "add": a dashed accent outline, a filled plus badge, and on hover a solid outline with the plus turning.

## 0.3.0-beta.33
- Simple mode: the two create buttons now sit together at the top of their tile, one above the other.

## 0.3.0-beta.32
- Host mode server pane: the server page fills the whole area, the open and close buttons sit on the right, and the embedded page no longer shows a second copyright footer.

## 0.3.0-beta.31
- Renamed the add-ons: "Easy game server creator" is now "Easy server creator" (not every egg is a game), and the Minecraft one is "Easy Minecraft server creator". The home tile and admin menu say "Create Other server".
- Simple mode: the two create buttons are now half-size icons stacked on the right of one tile, without a border. The grid keeps its columns; the label slides out on hover with the existing animation.

## 0.3.0-beta.30
- Host mode: clicking a server on the left opens its page in the right-hand area, in place of the admin pages. The cross closes it, picking another server swaps it, and Ctrl/Cmd-click or the "Open full page" button still opens the full page.

## 0.3.0-beta.29
- Panel name & logo (Settings → Appearance): style the name in the top-left corner with a font (Inter, system, rounded, serif, monospace, Monocraft, condensed, Impact), weight, letter spacing, capitals, one color or a fade between two colors, your own text and an optional "× Pelican". A live preview shows the result.
- Upload your own logo (PNG, JPEG or WebP up to 1 MB, checked as a real image and served with nosniff), with a height setting and the option to keep the text next to it.
- The default name now fades from the text color into the accent color from the middle to the right.
- Health panel: node names are written out in full instead of breaking mid-word.

## 0.3.0-beta.28
- Steam moved to the profile page (new "Steam" tab). It shows the Steam account linked with Pelican's own "Sign in with Steam" (OAuth), and your Web API key for game server login tokens. A key must belong to the linked Steam account.
- Automatic tokens: with a connected key, a new server for a game that needs a token gets one by itself when the field is left empty (one token per server, named after it). Can be turned off on the Steam tab.
- Save bar: it now compares real values with the last saved state. Switching tabs or clicking around no longer shows it, putting a value back hides it, and after saving the check is a status, not a button, until the next change.

## 0.3.0-beta.27
- Steam account (account menu → Steam): connect your own Steam Web API key once. It is checked with Steam, stored encrypted, only used for your own requests and never shown again (only its last four characters).
- Game creator: fields that take a Game Server Login Token (GSLT; e.g. Unturned, Insurgency: Sandstorm, Euro/American Truck Simulator, The Forest, CS-style STEAM_ACC) get a "Create with Steam" button that creates a token for the game on your account and fills it in. Without a connected key the field explains how to connect.

## 0.3.0-beta.26
- New add-on "Let Simplify manage people & roles" (General → Add-ons, off by default). One People & roles page replaces Pelican's Users and Roles lists in Admin tools, host mode and Pelican's own links:
  - People: search, invite (by email; they choose their own password), edit username/email/roles, send a password reset link, delete people who own no servers. Shows owned servers and whether two-factor sign-in is on.
  - Roles: each role combines Simplify permissions and limits with optional admin panel access. They are Pelican roles, so Pelican and other plugins keep working with them.
  - Safety: only root admins, the Root Admin role is locked, you cannot remove your own root access or delete yourself, the last root admin always stays, passwords of others are never set or shown, only known permissions are stored.
  - Pelican's pages stay reachable under Legacy (Users (legacy), Roles (legacy)).

## 0.3.0-beta.25
- Game creator: "Use the Steam banner and icon". When the chosen game is found in the Steam store (public store search by name, no API key, cached for a week), the creator shows a preview; after the server is created its Steam banner and a square icon are copied once to this panel, set as the server banner and used as the server icon. Can be switched off under Settings → Easy game server creator → Artwork.

## 0.3.0-beta.24
- One save bar everywhere: on Simplify settings and on Pelican's own edit pages (users, nodes, settings…) a floating bar appears once something changed; after a successful save the button turns into a check and the bar slides away. Failed validation keeps it open.
- Creators: the draft bar uses the same style, with a "Discard" button that asks for confirmation.
- Settings page: the category menu stays in place while the settings scroll.
- Host mode remembers the open section and page when you open a server and come back.
- Appearance settings no longer have the per-server colour picker (set colours on the server page).
- Dark theme: no dark haze around the health dock.
- Review fixes: a game from the egg index is only matched to an existing egg by UUID (never by name), and website settings are validated before they are passed on.

## 0.3.0-beta.23
- Settings page overhaul: header with the Simplify icon, version and quick actions; a two-pane layout with a calmer category menu and a separate "Add-ons" group; switches shown as setting rows (name and explanation left, switch right); quieter section cards; a floating save bar that shows "Unsaved changes".
- Server page: while Pelican is still installing a new or reinstalled server, a clear "Setting up your server…" card is shown and workspaces and power buttons are locked; it checks every 5 seconds and unlocks with a "Your server is ready" message. A failed installation or a running backup restore is shown the same way.
- Game creator: other games skip the (Minecraft-only) Server properties step; the steps are Game, Server and Game settings.

## 0.3.0-beta.22
- The game creator is its own add-on: "Easy game server creator" under General → Add-ons, with its own settings tab (approved games, how installing new games works). It can be switched on and off separately from the Minecraft creator.
- "Other game" on the dashboard now opens the game creator (it still opened Pelican's own form); with the add-on off, administrators get Pelican's form as before. Host mode and Admin tools list "Create game server" next to "Create server".
- The creator no longer shows a redundant "Other game" choice when the game is already chosen.
- Choosing a game that is not on the panel shows an "Installing …" screen with the steps while its egg is downloaded, checked and imported; the creator continues by itself when it is done.

## 0.3.0-beta.21
- Fix: updates, installs and removals started from the Plugins page ran the install step with php-fpm instead of the PHP command line (PHP_BINARY under php-fpm), so they only completed once the scheduled update took over. Simplify now finds the PHP CLI binary.
- New Simplify icon: the pelican with one clear twinkle on its beak, in the favicon and the legacy panel navigation.
- The Simplify settings button shows the Simplify pelican with a small gear.

## 0.3.0-beta.20
- Other games creator: one searchable grid with the games on this panel and 170+ games from the official Pelican egg repositories (bundled index, searched in the browser), with Popular and Installed filters.
- Picking a game that is not on the panel downloads its egg (pinned to a commit, checksum-checked) and imports it with Pelican's own importer when you continue. Administrators can always do this; other roles need the new "Install new games" ability (off by default). Root admins get a notification, and the game becomes available for self-service.
- Game settings use proper fields: on/off switches, choice lists and number fields from the egg's rules, defaults filled in, passwords hidden, and install/Steam options under a collapsed "Advanced" section.
- New `scripts/build-egg-index.py` rebuilds the index from local clones of the egg repositories.

## 0.3.0-beta.19
- Host mode: Legacy sits right under Panel in the menu.
- New setting "Show legacy pages" (General → Simplify interface) hides the Legacy section in Admin tools and host mode.

## 0.3.0-beta.18
- Notification links stay in Simplify: admin pages (such as Plugins) open in the Simplify popup without leaving or reloading the dashboard, also from the drop-down notice and the "Simplify is available" item.
- Plugin update, install and uninstall results carry an "Open plugins" link.

## 0.3.0-beta.17
- Much faster Simplify updates: the install step skips Pelican's panel-wide `yarn install` and `yarn build` (minutes on a small server). Simplify ships its own compiled CSS and JS; other plugins still get the full build.

## 0.3.0-beta.16
- Host mode: the page tabs at the top size to their text; the easy creator is called "Create server".
- Host mode: the selected menu item, the page header and the active tab use the panel accent colour; section icons keep their own colour.

## 0.3.0-beta.15
- Host mode: Servers holds the easy server creator (when on), Eggs and Mounts; the pages of a section are larger tabs centred at the top.
- Host mode: new Legacy item at the bottom of the menu with Pelican's own create-server form and plugin manager.
- Admin tools in simple mode use the same sections as host mode, as coloured cards, with health checks and Legacy at the bottom.

## 0.3.0-beta.14
- New notifications drop down from the bell with a pointer to it and slide back up after a few seconds (hover keeps them open). Checked with one small query every 30 seconds while the tab is visible.
- After a plugin update, install or uninstall the dashboard and the Plugins page reload by themselves once the change is done.
- "Check for updates" and updates themselves read update.json straight from GitHub instead of a copy GitHub's CDN keeps for up to five minutes; version, download and checksum always come from the same read.
- Update, install and uninstall from the Plugins page no longer need Pelican's queue worker.
- The Plugins page warns when Pelican's scheduler (cron) is not running, so automatic checks cannot run.
- Pelican's own plugin page and the links in its notifications open Simplify's Plugins page while the plugin manager is on.

## 0.3.0-beta.13
- Settings popup: the category menu stays in place while the settings scroll.
- Host mode: the admin menu is joined to the page it opens and always shows its labels (no more folding); current health check icons sit in a tidy grid under Health.
- Admin tools: health check icons in a grid under "Host health checks".

## 0.3.0-beta.12
- Plugin install and uninstall from the Plugins page run in a fresh process with a start-up check; success and failure always arrive as a notification, also when the background job itself crashes or times out.
- A plugin whose code fails to load is set back to "not installed" with the error shown, instead of staying enabled and stopping the whole panel (Pelican's own install leaves it enabled).
- The Plugins page shows when a change is still running and warns when the queue worker seems to be down.

## 0.3.0-beta.11
- Plugin updates are checked every minute (lookups cached for 50 seconds), so new versions are announced and, with automatic updates on, installed within a minute of GitHub serving them.

## 0.3.0-beta.10
- New plugin versions are noticed within about 15 minutes again (checked like Pelican's own plugin page) instead of once a day.
- Root admins get a notification for each new version, once, with a link to the Plugins page.
- With "Install plugin updates automatically" on, the update is installed right away (backup, check, rollback) instead of waiting for the night.

## 0.3.0-beta.9
- Plugins page: "Check for updates" button that skips the cache and checks right away.
- The update check runs every three hours instead of every twelve, and the update notice opens Simplify's plugin page.

## 0.3.0-beta.8
- Host mode redesign: server cards per node with status, software/version and players; a vertical, colour-coded admin menu with Health always at the bottom that folds to icons once you pick a page; related pages merged (Users & Roles, Database hosts / API keys / Webhooks, Nodes & Backup hosts) as tabs.
- The Simple / Host mode switch moved into Customize appearance.
- Admin tools: health check icons at the bottom, coloured per status; hover shows what is wrong, click opens the Health page.
- Plugins page: import from URL or zip, install, uninstall and delete in Simplify; Pelican's plugin page and its notification links are no longer needed.
- Settings: new Add-ons section in General ("Easy server creator", "Let Simplify manage plugins"); each add-on has its own settings tab, marked "Add-on". Turning the plugin manager off restores Pelican's own plugin page and stops automatic updates.
- Proposal for the "other games" creator rework: docs/proposals/other-games-creator.md.

## 0.3.0-beta.7
- Safe plugin updates (Admin tools → Plugins and once a day when "Install plugin updates automatically" is on): backup, download with checksum check, install in a fresh process, start-up check, automatic rollback on failure. Plugins stay enabled after an update (Pelican's own update left them disabled). Results appear in the notifications.
- Fix: Simplify's scheduled commands were never registered, so automatic backups and scheduled restarts did not run from the scheduler. Commands now live where Pelican loads them.
- New Plugins page in Simplify style with status, available update, Update and Enable/Disable.
- Admin tools: "Create server (legacy)" opens Pelican's create form; menu in two columns with "Host health checks" at the bottom.
- Large dialogs on admin pages (egg import, record forms) open as a side panel; admin wizards and tabbed pages (profile, user edit) follow the Simplify look.
- Notification links open the matching Simplify popup instead of the legacy page.
- Overview: servers grouped per node (grid and list), node filter next to the game filter with search above three nodes, "Group by node" choice (Automatic / Always / Off).
- Host mode for administrators: servers per node in a compact column, admin pages as the main workspace. "Display host mode automatically" in Simplify settings (recommended for big hosts with multiple nodes).
- Fix: list rows grew very tall with quick actions and CPU/RAM tiles off; the player count chevron now nudges on hover in both views; the MOTD gives way when quick actions slide in.
- Roadmap: easy game server creator, installation overhaul with EULA up front, Simplify as the whole panel (docs/ROADMAP.md).

## 0.3.0-beta.6
- Permissions reworked around Pelican roles (Simplify settings → Users & access). "Everyone" applies to all signed-in users; each role adds abilities (workspaces, legacy panel, create servers, install missing eggs) and sets its own limits. With several roles, abilities combine and the highest limit wins. Administrators can always do everything.
- Existing settings migrate automatically: the earlier user toggles become Everyone, creator roles keep creating with their limits. Individual user grants from earlier versions keep working and can be removed once those users have a role.
- Server creation settings now only hold where and what users may create (nodes, games, starting resources).
- New admin tools button (admins only) next to the settings button: Pelican's admin pages that Simplify does not replace, grouped as Game servers, Infrastructure, People & access, Panel (and Other for plugin pages). Each opens in a Simplify popup.
- Admin pages may be framed by the same site so they can open in that popup; other sites remain blocked.

## 0.3.0-beta.5
- New permission under Server creation → Who can create servers: "Allow users to install missing eggs". Off by default; when on, users who may create servers let the creator import Simplify's bundled, integrity-checked egg when it is not installed yet. Administrators with Pelican's Import egg permission can always do this.

## 0.3.0-beta.4
- "Use easy server creator" off now disables the whole guided creator (Minecraft, modpacks, other games and user self-service). The overview shows one "Create server" tile that opens Pelican's native creation; admins always have access to native creation.

## 0.3.0-beta.3
- Fix Next in the server creator for real (verified on Pelican 1.0.0-beta38 / Filament 5 / Livewire 4): the hidden allocation field lost its options on single-node panels, and the draft step watcher lost track when Livewire 4 re-rendered the wizard.
- Embedded pages no longer flip the dashboard to light mode (Filament's start-up theme event is ignored).
- Popups with Pelican pages: no frames inside frames, no second page title, Simplify icon buttons.
- Files: the page fits the popup and only the file list scrolls; toolbar and pagination stay in place.
- File editor: fills the space beside the list with only the code scrolling; Simplify header with file name, Save and Close.
- Pelican's own notifications appear in the Simplify bell (mark read / mark all read); notifications inside popups become Simplify toasts.
- Suggested server accent colours are pastel; custom colours stay free.
- Simplify settings reorganised into General, Appearance, Users & access, Server creation and Backups & performance, with side tabs.
- System checks only on the legacy settings page; new "Run setup wizard again" button there.
- The file revision check reports an unreachable node instead of failing.

## 0.3.0-beta.2
- Fix Next in the server creator: fields that are hidden in the browser (custom RAM, backup schedule) are no longer validated, and CPU/disk presets always win over hidden custom values.
- Panel name follows the accent colour; server names fade from white (ink) into their accent.
- MOTDs use the Minecraft-style Monocraft font (SIL OFL) with their dark background on overview cards.
- Offline artwork is faded and blurred like background banners.
- Server and egg icons swap place and size with a short animation when "Egg icon" is chosen.
- New "Large name" choice in the overview menu: alias or server name.
- Dark tooltips and game labels in dark mode.
- Compact list rows (about 68 px) with the MOTD beside the name.
- Quick actions sit on the card background with regular grey buttons.
- Remove leftover backgrounds behind the footer on the overview.

## 0.3.0-beta.1
- Rebuild the frontend on one design system: shared tokens, buttons, fields, switches, badges, dropdowns, popups, tooltips and toasts, used by the dashboard, creator, settings and embedded Pelican pages.
- Replace every browser confirm, prompt and hover text with Simplify dialogs and tooltips; add a notification bell for pending changes, health warnings, updates and notices.
- Give popups fixed sizes with a fixed header and footer; only the body scrolls, and the file manager uses a single scroll container that fits the popup.
- Style embedded Pelican pages before they paint, in the current theme and accent.
- Reorganise server settings into General, Gameplay and MOTD tabs.
- Show plugin updates in Simplify style in the notification bell and Simplify settings.
- Add Performance settings for overview, server, scheduled-restart and host-health intervals and for automatic backup defaults.
- Pause refreshes while a popup is open or the tab is hidden; back off websocket reconnects up to five minutes.
- Cache missing icons, MOTDs, failed lookups and host health; send server icons as cached URLs instead of base64 on every refresh.
- Serve assets and backgrounds as versioned, long-cached files with ETags.
- Make read-only dashboard actions renderless and build the initial dashboard state once per request.
- Automatic backups default to every 6 hours and skip servers that have not run since the last backup.
- Speed up the server creator: fewer live fields, memoised lookups, debounced draft saves and reactive step tracking.
- Keep only the current release archive in dist/; packages no longer ship screenshots.
- Fix saved-message reporting, repeated settings toasts, truncated favicons, Packages metadata paths, the 24-hour scheduler lock and Server creation hooks throwing on errors.
- Remove dead code: the legacy create redirect, unused catalog and settings helpers and old stylesheets and scripts.

## 0.2.0-alpha.26
- Replace tiny beak dots with two distinct four-point sparkles in the favicon and legacy navigation icon.
- Center health status between its heading and reporting count, enlarge the heart and clear footer-hook backgrounds.
- Add accent swatches, custom colors and editable hex values to Appearance settings.
- Carry global accent colors through Other game creation and aggregate health; tint server-name gradients with their accent.
- Add a personal switch to keep Minecraft creation green or use the global accent.
- Match copyright background to the page and darken quick-action buttons.
- Compact desktop list rows and show original names beside aliases, preserving two-line MOTDs and nearby resource widgets.

## 0.2.0-alpha.25
- Match solid quick-action bars to the page background in both themes.
- Show avatar and username in a wider profile button; reveal inline profile-settings and logout icons instead of an account dropdown.
- Move Light, Dark and System theme controls into the appearance dropdown, also available on server pages.
- Remove surrounding overview and legacy page backgrounds behind the health dock.
- Align Host health to the rendered server grid, including platform scrollbar widths.
- Increase aggregate health icon sizes and padding.
- Balance free space above and below the MOTD while keeping metrics beside player counts.
- Use shorter grid cards without CPU/RAM tiles, with room for quick actions when enabled.

## 0.2.0-alpha.24
- Anchor grid metrics above player counts and place list metrics farther right, preserving title/icon positions during power-control reveal.
- Align health edges with the server cards and confine footer background to the copyright row.
- Health details grow to their content height up to a viewport-aware cap, scroll internally and close on outside clicks.
- Add a quick-action visibility preference; solid action bars join the card edges and shift artwork aside.
- Give the aggregate health row slightly more height.
- Add a personal overview dropdown with layout, MOTD, primary icon and CPU/RAM tile preferences saved per user in the browser.
- Move grid/list controls into the header dropdown and use a gear for panel settings.


## 0.2.0-alpha.23
- Reduce collapsed Host health padding, typography and icons to keep the dock compact.
- Remove the empty strip above Host health; its soft shadow overlays the scrolling server region.
- Shorter list cards use smaller server icons, software icons alongside and raised titles.
- Taller overview grid cards retain logos and compress their contents when quick controls appear, without hover lift.
- Original server names appear in parentheses beneath aliases.
- Compact, borderless node summaries show four metrics in one row, with node icons and persistent expanded highlights.


## 0.2.0-alpha.22
- Overview titles shrink to fit; aliases and original names use consistent stacked rows.
- Software labels move into styled icon tooltips with reported versions.
- Inline cancel/confirm controls for draft deletion and overview power actions.
- Batch wizard draft autosaves and ignore draft indicator mutations when sizing popups.


## 0.2.0-alpha.21

- Confirm every overview quick power action before sending a command.
- Compact resource presets with larger icons beside their values and descriptions underneath.
- Build the creator progress bar from available steps; hide website display without its helper and respect disabled backups.
- Preserve draft stages by their names when optional plugins change.
- Avoid repeated iframe height writes when creator content has not changed; retain content shrinking and popup scroll positions.
- Lift grid server icons and place egg icons underneath server icons in list view.
- Remove MOTD rendering from overview while keeping its data for future configurable cards in 0.3.0.
- Use a narrow two-line MOTD display and support multiline MOTDs in the creator.
- Remove native copyright footer hook backgrounds and excessive wrapper height; tighten copyright spacing and add a shadow above host health.

## 0.2.0-alpha.20

- Give expanded host health a fixed, viewport-bounded detail area with internal scrolling.
- Keep every node visible; expand each node’s servers inline, with independent animated accordions.
- Preserve aggregate metrics and the upward expansion animation while expanding nodes.

## 0.2.0-alpha.19

- Redirect to the overview after deletion, without rendering the deleted server.
- Keep health and the transparent copyright footer docked on mobile; scroll inside the server list.
- Reveal quick power actions inside the same-size cards, with dark buttons and a slightly lighter dark background.
- Add a small sparkle beside the pelican beak in the favicon and legacy navigation icon.
- Repeat bump and shine effects on each hovered creator logo; respect reduced motion.
- Smaller offline heart further right in grid; shift the list heart slightly right.

## 0.2.0-alpha.18

- Expand host health upward with animated node details and visible-server resource rows.
- Show sampled upload/download rates alongside container traffic totals, with reset-safe calculations.
- Add permission-aware overview power controls below grid cards and alongside list rows.
- Refine offline heart size and position separately for grid and list views.
- Avoid restart reminders for unchanged settings, equivalent Minecraft values and unchanged allocations.
- Add MOTD color/formatting tools and safe live previews; show formatted MOTDs in dark overview and dashboard panels.
- Scale list top spacing with viewport height.

## 0.2.0-alpha.17

- Move clickable RAM, CPU and disk presets into creator step 2; reload configured type defaults when returning to step 1 and changing type.
- Add per-role and per-user allowances with backend quota enforcement and explicit access grants.
- Preserve popup scroll positions during embedded resizing and restore usable fixed file/editor heights.
- Use a filled broken-heart silhouette fading into the right side of offline cards.
- Credit existing Pelican/Wings functionality throughout the README feature list.

## 0.2.0-alpha.16

- Align the host health status and open per-node CPU, memory, storage and gameserver traffic details from the health tile.
- Display offline player counts as 0 / 0 and fade a large broken-heart symbol into offline artwork.
- Link header/footer Simplify credits to GitHub with inherited color and an underline.
- Group the complete feature inventory inside a collapsible README section.

## 0.2.0-alpha.15

- Remove leaked popup helper sections, empty footers and creator header spacing.
- Adapt server menus to game capabilities; hide Minecraft-only software and player controls.
- Show temporary gray offline artwork and a replaceable game-controller banner for other games.
- Add received/sent network traffic to host health and server resource graphs.
- Set the plugin author to jera.
- Keep SFTP details compact and inline, with a connection link beside them.
- Use fixed file viewer/editor heights with internal scrolling; record file saves in the shared restart banner.
- Allow uploads directly in the settings banner library, with server selection and optional immediate use.
- Keep host health width independent of server grid size and use a smaller icon-only alias action; show aliases on overview cards too.
- Match both creator hover patterns in their own accent colors.
- Replace embedded action and file icons with Simplify outline icons.

## 0.2.0-alpha.14

- Align dark iframe control colors with Simplify and use the panel accent in overview workspaces.
- Retain the alpha 13 features and correct its browser regression fixture.


## 0.2.0-alpha.13

- Show advisory PHP/Livewire upload capacity and NGINX/proxy configuration guidance; cap banner uploads at the detected application limit.
- Add a dedicated iframe stylesheet with server accent colors and shared control/icon styling.
- Keep the file list visible while its native editor opens alongside it; save closes only the editor, preserving native warnings and permissions. Protect unsaved changes.
- Show expandable, permission-gated SFTP details with copy and connect actions.
- Enlarge the favicon pelican, tint Offline badges light red and add editable display aliases beside actual server names.
- Place Server banners on its own tab inside Settings.


## 0.2.0-alpha.12

- Stop popup iframe height feedback loops; resize when content grows or shrinks.
- Fix mobile navigation stacking, touch targets and menu overflow.
- Replace the account settings icon with a Profile button; allow authenticated same-origin profile embedding and retain save actions.
- Move Server banners into a dedicated header menu button and popup.


## 0.2.0-alpha.11

- Wait for dashboard initialization, styles, fonts and visible images before showing the page, preventing refresh flashes.
- Include the page loader and standalone settings stylesheet in blocking installation checks.

## 0.2.0-alpha.10

- Outline the server title tile in its server accent color.
- Reuse the outline navigation pelican in the favicon on dark gray.
- Upload and manage server banners with validated JPG/PNG/WebP images; retain the entire bundled collection.
- Add uploaded banner management to settings and server image pickers, with server permission checks.
- Use content-sized embedded workspaces and one scrolling popup area; remove native shell spacing.
- Keep popups open until their close icon is used, including after saving.
- Style standalone settings separately from embedded settings.
- Open Other game creation in an embedded native creator until its configurator is ready.

## 0.2.0-alpha.9

- Place one Simplify return button before notifications on the legacy overview and admin topbar; keep the admin topbar visible.
- Add a permission-aware creator menu with native and Simplify choices, and stop redirecting native creation.
- Preserve native legacy styling while matching embedded popup backgrounds and removing nested spacing.
- Fit the settings popup to its contents and show diagnostics below settings with a close button.
- Keep creator tiles stationary on hover and isolate each tile’s hover layout.
- Add sharp gray pelican favicon artwork, an outline Simplify navigation icon and sparkle accents.

## 0.2.0-alpha.8

- Fixed release ZIP timestamps so updated files no longer share the same date as older builds.
- Clear compiled Blade views during installation and updates so the new screens appear immediately.

## 0.2.0-alpha.7

- Show the installed build number beside Enhanced by Simplify in the copyright footer.
- Replaced the README disclaimer image with a native GitHub warning block.

- Added a Go to Simplify Panel button with a dashboard icon to the legacy server overview.
- Added a matching return button beside the admin and legacy topbar controls, with an icon-only mobile layout.
- Kept the current server selected when returning from its native pages; embedded workspaces hide the button.

## 0.2.0-alpha.6

- Split first setup into separate categories with the same numbered stepper and controls as server creation.
- Organized settings into category tabs with grouped columns.
- Made Vanilla, Vanilla+ and modpack CPU/RAM/disk defaults configurable in setup and settings. Existing resource allocations remain unchanged.
- Updated the README AI disclaimer with the requested orange notice and backup guidance.

## 0.2.0-alpha.5

- Redesigned first-run checks into compact cards with a separate first-setup button.
- Added a dedicated navigation icon and popup for Simplify settings; diagnostics stay hidden by default.
- Restored automatic backgrounds for existing Minecraft servers without the Web extension.
- Added a background picker next to the server color control.
- Automatically assigned distinct saved colors to servers without one when individual colors are enabled.

## 0.2.0-alpha.4

- Added a verified plugin backup/reset/restore script to reopen the setup wizard.
- Added a stable latest ZIP URL for native panel imports.
- Made README screenshots smaller, clickable previews.

## 0.2.0-alpha.3

- Moved plugin.json, src, resources and database to the repository root for Pelican Hub submission and native source-archive imports.
- Updated packaging, migration preflight and regression paths for the standard plugin layout.

## 0.2.0-alpha.2

- Added blocking dependency checks to the native installer, migration installer and setup/settings save.
- Validate PHP extensions, Composer/icons, Node/Yarn, actual compiled CSS/JS, storage, database/cache and queue configuration.
- Show failed requirements with repair instructions and a Check again action.
- Shortened the README, added highlighted features, screenshots, full feature list and AI disclosure.

## 0.2.0-alpha.3

- Folded the standalone creator into Simplify; removed duplicate services and game resources.
- Added a shared feature registry, grouped settings and first-install wizard.
- Added configurable Friends mode and ordinary-user workspace visibility.
- Added a global theme manager, shared controls, optional legacy styling and console modes.
- Extracted private Web navigation, creator fields, settings and subdomain UI into the separate extension.
- Removed installation-specific branding, domains and paths. Panel branding and bird-name examples are dynamic.
- Added request-scoped settings resolution, bounded maintenance, batched assignment checks, hidden-tab refresh suppression and shared cached assets.
- Added migration of legacy queued-job namespaces and backward-compatible reads of existing metadata and drafts.
- Added feature/access and theme regression tests and a reproducible check runner.

# Lab: the plugin in real Pelican panels

The CI jobs check the code and load the plugin in a panel (`tests/panel/smoke.php`). The lab goes further: it
installs a Pelican release locally, puts this checkout of the plugin in it, fakes a Wings daemon with twelve
servers on one node, and walks through every Simplify page in a browser on a desktop, two phones (390 and 360
wide), the same phones turned sideways and while turning, in light and dark. It records HTTP 5xx, page errors,
console errors, text cut off without an ellipsis and controls outside the screen, takes a screenshot of every
step, measures the overview's listing with a cold and a warm cache, and counts the requests Wings receives.

Run it before a beta or stable release, on every Pelican release the plugin supports:

```bash
tests/lab/run-lab.sh 1.0.0-beta38 php8.3 8001 8090
tests/lab/run-lab.sh 1.0.0-rc.1 php8.4 8002 8091
tests/lab/run-lab.sh 1.0.0-rc.2 php8.5 8003 8092
```

Each run prints the smoke test result, the benchmark JSON and `<version>: N/N checks passed, M issues`, exits 1 when
a check fails or an issue is found, and leaves screenshots and `result.json` in `~/pelican-lab/shots/<version>-<php>/`.
A panel is installed once (`~/pelican-lab/panels/<version>`) and reused; delete the folder to start over.

Needs git, composer (`/usr/local/bin/composer`), the PHP build with sqlite, node with `playwright` installed
(`PW_CHROMIUM=/path/to/chromium` when Playwright did not download its own browser), and network access to
github.com and packagist. Behind a proxy that refuses zip downloads, composer installs from git (`--prefer-source`).

## Pieces

| File | What it does |
| --- | --- |
| `setup-panel.sh` | Clones a Pelican release, installs it with sqlite, no Wings, `QUEUE_CONNECTION=database` (the plugin refuses `sync`), Faker from a git checkout for the factories. |
| `install-plugin.sh` | Copies this checkout into `plugins/pelican-simplify` (never a symlink: `p:plugin:install` rewrites plugin.json) and enables it. |
| `fake-wings.js` | Answers `/api/servers`, `/api/servers/{uuid}`, `/api/system*`, file reads (`server.properties`) and power calls, with a delay per request and a log of every request. |
| `seed.php` | Admin `lab@example.com` / `LabPassword123!`, a second owner, one node on the fake Wings, four eggs, twelve servers (eight of the admin, four of the other owner), every Simplify feature on. Prints the server list as JSON. |
| `cleanup.php` | Removes what the smoke test leaves behind (its own node, user and server), because a second node switches host mode on automatically. |
| `urls.php` | The URL of every Simplify page on every panel, for the browser walk. |
| `bench.php` | Times `Dashboard::listing()` as the admin with an empty cache, three rounds, plus one warm call; counts Wings requests and outbound HTTP per URL. |
| `lab.js` | The browser walk: overview in cards, compact and list, owner and game filters, host mode, server groups and customize popups, phone menu, server page with every workspace and the MOTD editor, every other page, phone turning. |
| `theme-check.js` | With a theme plugin installed (Nord, Catppuccin, Theme Customizer): Simplify detects it and renders with its palette on every page, light and dark, desktop and phone. `node tests/lab/theme-check.js --base http://127.0.0.1:8002 --out ~/pelican-lab/shots/nord --label nord`. |

## Pitfalls met while building it

- `APP_URL` must carry the port, or every asset loads from port 80.
- `php artisan serve` needs `PHP_CLI_SERVER_WORKERS=4`: Livewire fires requests while a page is still loading.
- The smoke test creates a node with a random public IP; until it is removed, every overview call waits a second
  on it and host mode switches on (two nodes).
- Phones open workspaces as sheets with their own close button; Escape only closes desktop popups.
- Filament's screen-reader-only labels (`.fi-sr-only`) look like cut-off text to a naive check and are skipped.
- The host health card lists every server on the node whatever the filters, so the filter checks read the
  server rows, not the whole page.

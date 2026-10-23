# Installation

Target: Pelican 1.0.0-beta38 (PHP 8.3 or newer), 1.0.0-rc.1 (PHP 8.4 or newer) or, from beta 0.5.0-beta.3 on, 1.0.0-rc.2 (PHP 8.4 or newer). Stable 0.4.6 does not support rc.2 yet.

| Pelican | PHP | Stable 0.4.6 | Beta 0.5.0-beta.3 |
|---|---|---|---|
| 1.0.0-beta38 | 8.3 | ✅ Works | ✅ Tested, works |
| 1.0.0-beta38 | 8.4 | Not tested | ✅ Tested, works |
| 1.0.0-beta38 | 8.5 | Not tested | ✅ Tested, works |
| 1.0.0-rc.1 | 8.4 | ✅ Works | ✅ Tested, works |
| 1.0.0-rc.1 | 8.5 | Not tested | ✅ Tested, works |
| 1.0.0-rc.2 | 8.4 | ❌ Not supported | ✅ Tested, works |
| 1.0.0-rc.2 | 8.5 | ❌ Not supported | ✅ Tested, works |

Pelican 1.0.0-rc.1 and rc.2 need PHP 8.4 or newer. "Tested" means a fresh panel with that Pelican and PHP version: Simplify installed, the panel started and every Simplify page opened without errors. Snapshot 0.5.0-snapshot.8 supports the same versions as the beta.


## Panel import

In **Admin → Plugins → Import from URL**, paste `https://raw.githubusercontent.com/jerajerajerajera/PelicanSimplify/main/dist/pelican-simplify-latest.zip`, import it, then click **Install**. To try the beta (0.5.0-beta.3: Simplify on phones, the panel as an app, support for other plugins and Pelican 1.0.0-rc.2), use `https://raw.githubusercontent.com/jerajerajerajera/PelicanSimplify/beta/dist/pelican-simplify-beta-latest.zip` instead, or choose Beta later under Simplify settings → Plugin manager → "Simplify versions". You can also upload the ZIP through Import. The repository also uses Pelican's standard plugin layout, with plugin.json and src at its root. The installer checks dependencies before enabling the plugin. Open Simplify settings and finish setup after every required check passes.

Node.js 22.12+ or 24+, Yarn, the panel's PHP extensions and Composer dependencies, writable Laravel storage, a working database/cache and a persistent queue connection are required. The setup page lists failed checks and repair instructions; use **Check again** after fixing them. The configured queue check does not prove that the worker or scheduler is running. Keep both services active as required by Pelican.

Simplify supplies its own CSS/JavaScript without a separate build. Pelican's own compiled assets must exist and be readable. If the build check fails, repair Node/Yarn and run `yarn install` and `yarn build` from your Pelican directory. Browser delivery, reverse-proxy configuration and Wings operations still need testing on your installation.

## Migration from the standalone creator

The former standalone creator (`simple-server-creator`) must not run alongside the unified creator. In **Admin → Plugins**, disable and delete it first, then import Simplify as above. The setup checks block the unified creator while the standalone creator is still enabled. Make a backup of the panel first, as with any plugin change.

## Development

Run `bash tests/run.sh` with PHP 8.3 and Node installed. For the browser theme check, run `npm ci`, `npx playwright install chromium`, then `RUN_BROWSER_TESTS=1 bash tests/run.sh`.

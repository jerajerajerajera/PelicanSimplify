# Upgrade from PelicanSimplify to Simplify 2.0 (beta)

PelicanSimplify is end of life and will not be updated. Simplify replaces it. It takes over your settings itself, so the upgrade is mostly installing.
Rather wait for the full release? You can keep using PelicanSimplify until Simplify 2.0 is out on the Hub.

## Before you start

- Make a backup of the panel's database and the panel folder (usually `/var/www/pelican`).
  Beta builds are not reviewed by Pelican yet.
- Your panel runs Pelican 1.0.0-rc.1 or newer. Older panels never get Hub betas.
  Update Pelican first if needed, with [Pelican's own update guide](https://pelican.dev/docs/panel/update).
- **Do not uninstall PelicanSimplify.** An uninstall removes its tables and your settings.
  Leave it installed; Simplify removes the old plugin itself.

## 1. Get access to the beta

1. Make an account on [hub.pelican.dev](https://hub.pelican.dev) (you sign in with Discord).
2. Send your Discord ID to [Jera](https://discord.com/users/167026719063015424) on Discord. You get an invite on the Hub.
   To copy your ID: Discord › Settings › Advanced › Developer Mode on, then right-click your name › Copy User ID.
3. Accept the invite at [hub.pelican.dev/my/beta](https://hub.pelican.dev/my/beta).
4. Connect your panel on the Hub and enroll it for the beta there.

## 2. Install Simplify first

In the panel: Admin › Plugins, install Simplify from the Hub. While it installs, Simplify:

- takes over your settings, branding, friends mode and website details;
- takes over subdomains, including the Cloudflare token and zone ID from `.env` (stored encrypted);
- removes the old PelicanSimplify plugin folder. Its data stays.

## 3. Then the add-ons you want

Install them from the Hub after Simplify. Each one is optional.

- **Toolkit**: plugins and mods, Minecraft settings, players, automatic backups (takes over your backup plans).
- **Server Maker**: create servers in a few steps.
- **Egg Updates**: safe egg updates with Test first.
- **Explain**: explains errors (takes over your saved AI key).
- **Hosting**: links to your billing system.

## 4. Clear the caches

On the panel server (Linux shell):

```bash
cd /var/www/pelican
sudo -u www-data php artisan migrate --force
sudo -u www-data php artisan optimize:clear
sudo systemctl restart pelican-queue
```

## 5. Check

Open the panel. Simplify may ask you to run its setup once. Check the overview, a server page and
Settings › Simplify. Something wrong? Send [Jera](https://discord.com/users/167026719063015424) the error and the last lines of
`storage/logs/laravel-*.log` (remove addresses and keys first).

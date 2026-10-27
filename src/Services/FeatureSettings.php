<?php
declare(strict_types=1);
namespace Simplify\Panel\Services;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
/** One feature registry shared by installation and settings. Resolved per request. */
final class FeatureSettings
{
    public const FILE = 'simplify/features.json';
    /** Parts of the full Simplify panel. "Only the add-ons" switches them off and keeps each saved choice for a switch back. */
    public const FULL_ONLY = ['dashboard','host_mode','global_theme','legacy_theme','host_health'];
    private ?array $loaded = null;
    public static function definitions(): array
    {
        return [
            'addons_only'=>['Only the add-ons','Pelican keeps its own dashboard, server pages and look; Simplify only adds the add-ons that are switched on. Off makes Simplify the whole panel.',false,'Experience'],
            'friends_mode'=>['Friends mode','Show all server cards to signed-in users. Only owners, assigned users and administrators can open or manage a server.',true,'Access and visibility'],
            'user_legacy'=>['Legacy mode for users','Allow ordinary users to open the native client panel. Administrators always retain legacy access.',false,'User access'],
            'user_advanced'=>['Advanced workspace for users','Show container, startup and advanced management to ordinary users with the required permissions.',false,'User access'],
            'user_software'=>['Software changes for users','Allow permitted owners to change Minecraft software or modpacks.',false,'User access'],
            'user_resources'=>['Resource editing for users','Allow owners with configured resource grants to change CPU, RAM and storage.',true,'User access'],
            'user_settings'=>['Game settings for users','Show the game settings workspace to assigned users with the required Pelican permissions.',true,'User access'],
            'user_files'=>['Files for users','Show the embedded file workspace. Pelican file permissions remain enforced.',true,'User access'],
            'user_players'=>['Players for users','Show player lists and moderation actions permitted by Pelican.',true,'User access'],
            'user_backups'=>['Backups for users','Show recovery points to users with backup permissions. Automatic policy changes remain administrator-only.',true,'User access'],
            'user_packages'=>['Mods and plugins for users','Show only the package type supported by the server. File permissions still apply.',true,'User access'],
            'user_activity'=>['Activity log for users','Show recorded actions to users with activity permissions.',true,'User access'],
            'user_schedules'=>['Scheduled tasks for users','Show scheduled tasks to ordinary users with schedule permissions. Administrator managed access controls who can modify them.',false,'User access'],
            'dashboard'=>['Simplify dashboard','Replace the native server overview and console landing page with Simplify.',true,'Interface'],
            'host_mode'=>['Display host mode automatically','Recommended for big hosts with multiple nodes: administrators see servers in a compact list per node and the admin pages as the main workspace. The standard view is perfect for smaller servers. Each admin can still switch views.',false,'Interface'],
            'legacy_pages'=>['Show legacy pages','Show the Legacy section in Admin tools and host mode, with Pelican\'s own create-server form and plugin manager. Off hides it; the pages themselves keep working.',true,'Interface'],
            'game_creator'=>['Easy server creator','Search 170+ games from the official Pelican eggs and create servers for them; missing eggs are installed automatically (administrators, or roles with "Install new games"). Off sends "Other game" to Pelican\'s own form for administrators.',true,'Add-ons'],
            'steam_art'=>['Offer Steam banners and icons','When a game is found in the Steam store, the game creator offers to copy its banner and a square icon to the new server. The images are downloaded once and stored on this panel.',true,'Game creator'],
            'easy_creator'=>['Easy Minecraft server creator','The guided creator for Minecraft and modpacks, including self-service creation for users. Off disables it completely: servers are created with Pelican\'s native form only. Admins always have access to native creation.',true,'Add-ons'],
            'global_theme'=>['Shared appearance','Apply the same light or dark theme, surfaces, inputs and buttons throughout the panel and creator.',true,'Appearance'],
            'legacy_theme'=>['Style legacy workspaces','Apply the shared theme to embedded files, activity, account and advanced pages.',true,'Appearance'],
            'console_dark'=>['Always dark console','Keep the console black in both light and dark modes.',true,'Appearance'],
            'follow_panel_theme'=>['Follow panel theme','When a Pelican theme plugin is installed (such as Nord, Catppuccin or Theme Customizer), use its colours, font and light or dark mode, so Simplify matches the rest of the panel.',true,'Appearance'],
            'resources'=>['Resource controls','Show the CPU, memory and storage editing workspace. Existing resource permissions and quotas still apply.',true,'Server features'],
            'players'=>['Player management','Show player lists and Minecraft moderation controls.',true,'Server features'],
            'packages'=>['Plugin and mod management','Show the search, installation and removal workspace for plugins and mods.',true,'Server features'],
            'software'=>['Software management','Show game software and modpack reinstallation controls.',true,'Server features'],
            'backups'=>['Backup workspace','Show backups, downloads, restore actions and automatic backup policy.',true,'Server features'],
            'automatic_backups'=>['Automatic backups','Run configured backup policies through the existing Pelican scheduler. Off pauses policies without deleting them.',true,'Automation'],
            'subdomains'=>['Automatic subdomains','Give each server its own address, such as oak.example.com, chosen in the creator or in the server\'s Network section. The DNS records are created in Cloudflare automatically and removed with the server.',false,'Add-ons'],
            'website_embed'=>['Website embed','Show your servers on your own website with one line of code: status, players, modpack and join address. Only servers whose owner chose "Show on the website" appear.',true,'Add-ons'],
            'people_manager'=>['Let Simplify manage people & roles','One People & roles page replaces Pelican\'s Users and Roles pages: invite people (they choose their own password), assign roles, and give each role Simplify permissions plus optional admin access. Built on Pelican\'s own roles. Off uses Pelican\'s pages.',false,'Add-ons'],
            'plugin_manager'=>['Let Simplify manage plugins','Simplify\'s plugin page replaces Pelican\'s, with import, install, removal and safe updates with automatic rollback. Off uses Pelican\'s own plugin page and stops automatic updates.',true,'Add-ons'],
            'auto_update_plugins'=>['Install plugin updates automatically','Install new plugin versions within about a minute of their release, safely: a backup is kept, the new version is installed and checked, and the previous version is restored if anything fails. Results appear in the notifications.',true,'Automation'],
            'simplify_beta'=>['Get Simplify beta versions','Beta versions test the next stable release with all its new features.',false,'Plugin updates'],
            'simplify_snapshot'=>['Get Simplify snapshot versions','Snapshots are development builds with the latest changes, published often. They include beta versions.',false,'Plugin updates'],
            'host_health'=>['Host health','Show host CPU, memory and storage availability on the overview.',true,'Server features'],
            'restrict_access'=>['Administrator managed access','Restrict account creation, server sharing and additional schedules to administrators.',true,'Access and visibility'],
        ];
    }
    public static function defaults(): array
    {
        return ['setup_completed'=>false,...array_map(fn(array $v):bool=>$v[2],self::definitions())];
    }
    public static function all(): array { return app(self::class)->load(); }
    /** True when the panel keeps Pelican's own pages and only uses the add-ons. */
    public static function addonsOnly(): bool { return self::all()['addons_only']; }
    /** The saved switches as they apply right now: with "Only the add-ons" the full panel's parts read as off. */
    public static function effective(): array
    {
        $all = self::all();
        return $all['addons_only'] ? array_replace($all, array_fill_keys(self::FULL_ONLY, false)) : $all;
    }
    private function load(): array
    {
        if($this->loaded!==null)return $this->loaded;
        $data=array_intersect_key(JsonFile::read(self::FILE),self::defaults());
        return $this->loaded=array_replace(self::defaults(),array_map(fn($v):bool=>$v===true,$data));
    }
    public static function enabled(string $feature): bool
    {
        // A plugin that clashes with Simplify is enabled: hand the panel back to Pelican's own pages.
        if ($feature === 'dashboard' && ExternalPlugins::conflicts() !== []) return false;
        // A plugin that does the same job is enabled (such as Subdomains): only that Simplify feature steps aside.
        if (class_exists(ExternalPlugins::class) && ExternalPlugins::overlapFor($feature) !== null) return false;
        return self::effective()[$feature]??false;
    }
    public static function save(array $input,bool $complete=false): void
    {
        abort_unless(user()?->isRootAdmin(),403);
        Cache::lock('simplify.features',10)->block(3,function()use($input,$complete):void{
            $settings=app(self::class);$settings->loaded=null;$old=$settings->load();
            $next=array_replace($old,array_map(fn($v):bool=>$v===true,array_intersect_key($input,self::definitions())));
            if($complete)$next['setup_completed']=true;
            if(!Storage::disk('local')->put(self::FILE,json_encode($next,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT)))throw new \RuntimeException('Could not save Simplify settings.');
            $settings->loaded=$next;
        });
    }
}

<?php
declare(strict_types=1);
namespace Simplify\Panel\Services;
/**
 * The last message of PelicanSimplify, for root admins: it is end of life, Simplify replaces it, and the Simplify 2.0
 * beta is open for testers.
 * A bar and a bell item like the Panel's update announcements; it stays until PelicanSimplify is replaced.
 */
final class SimplifyReplaced
{
    public const KEY='panel-simplify-replaces-pelicansimplify';
    public const UPGRADE='https://github.com/jerajerajerajera/PelicanSimplify/blob/main/UPGRADE.md';
    public const DISCORD='https://discord.com/users/167026719063015424';

    /** In the shape of PanelUpdates' items. */
    public static function item(): array
    {
        // @english: the owner's last message to PelicanSimplify's admins, the same English text in every language.
        return ['key'=>self::KEY,'logo'=>true,'title'=>'PelicanSimplify is end of life',
            'text'=>'This plugin is end of life and will not be updated. Simplify replaces it. Simplify 2.0 is in beta on the Pelican Hub. Want to test it? Make an account on hub.pelican.dev and send your Discord ID to Jera on Discord. Rather wait for the full release? You can keep using this plugin.',
            'action'=>['label'=>'How to upgrade','url'=>self::UPGRADE],
            'notes'=>[
                'This plugin is end of life and will not be updated. Simplify replaces it.',
                'Join the beta test: make an account on hub.pelican.dev, then send your Discord ID to Jera on Discord for an invite.',
                'Rather wait for the full release? You can keep using this plugin. Nothing changes until Simplify 2.0 is out.',
                'New: one core plugin plus add-ons you pick (Toolkit, Server Maker, Egg Updates, Explain, Hosting), all updated through the Hub.',
                'New: an Admin page for every admin, Pelican\'s lists as clear rows, and one Settings page per server.',
                'New: Connections in the account menu for Steam, CurseForge and AI keys, and changes wait until you press Save.',
                'Fixed: a click in the server menu no longer reloads the whole page, so the console stays connected.',
                'Fixed: on Edit schedule the tasks can be reached and edited again (issue #236).',
            ],
            'notes_url'=>self::UPGRADE];
    }

    /** The Simplify logo for the bar and the bell, as a data URI (empty when the file is missing). */
    public static function logo(): string
    {
        $file=dirname(__DIR__,2).'/resources/images/simplify-logo.svg';
        return is_file($file)?'data:image/svg+xml;base64,'.base64_encode((string)file_get_contents($file)):'';
    }
}

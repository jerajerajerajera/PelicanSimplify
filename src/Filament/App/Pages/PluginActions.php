<?php
declare(strict_types=1);
namespace Simplify\Panel\Filament\App\Pages;

/** Buttons other plugins add to Pelican's server list (such as Create server from User Creatable Servers). */
final class PluginActions extends \Simplify\Panel\Filament\PluginActionsPage
{
    protected static function actionPanel(): string
    {
        return 'app';
    }
}

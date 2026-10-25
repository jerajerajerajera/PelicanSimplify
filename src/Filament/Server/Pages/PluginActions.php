<?php
declare(strict_types=1);
namespace Simplify\Panel\Filament\Server\Pages;

/** Buttons other plugins add to Pelican's console (such as Upload logs from Mclogs Uploader), for one server. */
final class PluginActions extends \Simplify\Panel\Filament\PluginActionsPage
{
    protected static function actionPanel(): string
    {
        return 'server';
    }
}

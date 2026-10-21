<?php
declare(strict_types=1);
namespace Simplify\Panel\Filament\App\Pages;

/** Server list widgets from other plugins (such as the resource limits of User Creatable Servers). */
final class PluginWidgets extends \Simplify\Panel\Filament\PluginWidgetsPage
{
    protected static function widgetPanel(): string
    {
        return 'app';
    }

    public function widgetData(): array
    {
        return [];
    }
}

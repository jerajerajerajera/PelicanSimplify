<?php
declare(strict_types=1);
namespace Simplify\Panel\Filament;

use Simplify\Panel\Services\ExternalPlugins;

/**
 * Widgets other plugins add to Pelican pages that Simplify replaces, rendered on their own so Simplify can show
 * them as a tile. The page runs inside the widget's own panel (and server), so the widget's access check and
 * polling work exactly as on Pelican's page.
 */
abstract class PluginWidgetsPage extends \Filament\Pages\Page
{
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $slug = 'simplify-widgets';
    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable { return __('pelican-simplify::plugins.pages.plugin_widgets'); }
    protected string $view = 'pelican-simplify::plugin-widgets';

    /** The panel whose widgets this page shows (see ExternalPlugins::widgets()). */
    abstract protected static function widgetPanel(): string;

    /** What Pelican passes its own widgets on that page. */
    abstract public function widgetData(): array;

    /** Visible widgets, each after its own canView check: [{key, class, properties}]. */
    public function visibleWidgets(): array
    {
        return array_values(array_filter(ExternalPlugins::widgets(static::widgetPanel()), function (array $widget): bool {
            try {
                return !method_exists($widget['class'], 'canView') || $widget['class']::canView();
            } catch (\Throwable) {
                return false;
            }
        }));
    }

    public function renderWidget(array $widget): string
    {
        try {
            return (string) \Livewire\Livewire::mount($widget['class'], [...$this->widgetData(), ...$widget['properties']], $widget['key']);
        } catch (\Throwable $exception) {
            report($exception);
            return '';
        }
    }

    /** Whether there is anything to show; Simplify leaves the tile out otherwise. */
    public static function hasWidgets(): bool
    {
        return ExternalPlugins::widgets(static::widgetPanel()) !== [];
    }
}

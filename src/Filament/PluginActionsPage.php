<?php
declare(strict_types=1);
namespace Simplify\Panel\Filament;

use Simplify\Panel\Services\ExternalPlugins;

/**
 * Runs one button another plugin added to a Pelican page that Simplify replaces (such as Upload logs from Mclogs
 * Uploader on the console). Simplify loads this page in a transparent layer over the dashboard: the button runs
 * in its own panel (and server) exactly like on Pelican's page, its dialog shows over Simplify and its
 * notifications become Simplify toasts. Only a link signed for this session starts a button by itself.
 */
abstract class PluginActionsPage extends \Filament\Pages\Page
{
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $slug = 'simplify-actions';
    protected static ?string $title = 'Plugin actions';
    protected string $view = 'pelican-simplify::plugin-actions';

    #[\Livewire\Attributes\Locked] public string $run = '';
    #[\Livewire\Attributes\Locked] public bool $signed = false;

    /** The panel whose buttons this page runs (see ExternalPlugins::actions()). */
    abstract protected static function actionPanel(): string;

    public function mount(): void
    {
        $this->run = (string) request()->query('run', '');
        $this->signed = $this->run !== '' && hash_equals(ExternalPlugins::runToken(static::actionPanel(), $this->run), (string) request()->query('token', ''));
    }

    /** The plugins' buttons, so Filament can mount them by name; the page header itself is hidden. */
    protected function getHeaderActions(): array
    {
        return ExternalPlugins::registeredActions(static::actionPanel());
    }
}

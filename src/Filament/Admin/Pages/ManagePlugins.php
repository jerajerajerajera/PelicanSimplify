<?php
declare(strict_types=1);
namespace Simplify\Panel\Filament\Admin\Pages;

use App\Models\Plugin;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Simplify\Panel\Services\PluginUpdater;

/** Simplify's plugin overview: status, versions and safe updates with rollback. Opens from Admin tools. */
final class ManagePlugins extends Page
{
    protected static ?string $title = 'Plugins';
    protected static ?string $slug = 'simplify-plugins';
    protected string $view = 'pelican-simplify::admin-plugins';
    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool { return (bool) user()?->can('viewAny', Plugin::class) && \Simplify\Panel\Services\FeatureSettings::enabled('plugin_manager'); }

    private function canChange(Plugin $plugin): bool { return (bool) user()?->can('update', $plugin); }

    /** True while an update, install or uninstall started here is running; the page reloads once it is done. */
    public bool $busy = false;

    /** Rows for the view; update lookups are cached for 50 seconds. */
    public function plugins(): array
    {
        Plugin::refreshRows();
        $rows = Plugin::query()->orderBy('load_order')->orderBy('name')->get()->map(function (Plugin $plugin): array {
            $latest = $plugin->status->value === 'not_installed' ? null : PluginUpdater::latestVersion($plugin);
            return [
                'id' => $plugin->id,
                'name' => $plugin->name,
                'author' => $plugin->author,
                'description' => $plugin->description,
                'version' => $plugin->version,
                'latest' => $latest && version_compare($latest, $plugin->version, '>') ? $latest : null,
                'status' => $plugin->status->value,
                'message' => $plugin->status_message,
                'can_change' => $this->canChange($plugin),
                'can_delete' => (bool) user()?->can('delete', $plugin),
                'updating' => \Illuminate\Support\Facades\Cache::has('simplify.plugin-update-queued.'.$plugin->id),
                'busy_since' => \Illuminate\Support\Facades\Cache::get('simplify.plugin-queued.'.$plugin->id),
            ];
        })->all();
        $busy = collect($rows)->contains(fn (array $row) => $row['updating'] || $row['busy_since']);
        // New plugin code only takes effect on a fresh page load.
        if ($this->busy && !$busy) $this->js('window.location.reload()');
        $this->busy = $busy;
        return $rows;
    }

    /** Seconds since the scheduled update check last ran, or null when it never ran. */
    public function lastCheckAge(): ?int
    {
        $at = \Illuminate\Support\Facades\Cache::get('simplify.plugin-check-at');
        return is_int($at) ? max(0, time() - $at) : null;
    }

    public function updatePlugin(string $id): void
    {
        $plugin = Plugin::query()->findOrFail($id);
        abort_unless($this->canChange($plugin), 403);
        \Illuminate\Support\Facades\Cache::put('simplify.plugin-update-queued.'.$id, true, 1200);
        $this->busy = true;
        \Simplify\Panel\Jobs\UpdatePlugin::dispatchAfterResponse($plugin->id, (int) user()->id);
        Notification::make()->title('Update started')->body($plugin->name.' is updated in the background. You get a notification when it is done; if anything fails, the current version is restored.')->info()->send();
    }

    public function setEnabled(string $id, bool $enabled): void
    {
        $plugin = Plugin::query()->findOrFail($id);
        // Pelican 1.0.0-rc.2 marks a plugin that took the panel down as crashed; enabling it gives it another try.
        abort_unless($this->canChange($plugin) && in_array($plugin->status->value, $enabled ? ['disabled', 'crashed'] : ['enabled'], true), 403);
        $service = app(\App\Services\Helpers\PluginService::class);
        $enabled ? $service->enablePlugin($plugin) : $service->disablePlugin($plugin);
        Notification::make()->title($plugin->name.($enabled ? ' enabled' : ' disabled'))->body('Reload the panel to apply the change.')->success()->send();
    }

    /** Install, uninstall and delete run through Pelican's own jobs and service, so nothing needs the legacy page. */
    public function installPlugin(string $id): void
    {
        $plugin = Plugin::query()->findOrFail($id);
        abort_unless($this->canChange($plugin) && $plugin->status->value === 'not_installed', 403);
        \Illuminate\Support\Facades\Cache::put('simplify.plugin-queued.'.$id, now()->timestamp, 1800);
        $this->busy = true;
        \Simplify\Panel\Jobs\ChangePlugin::dispatchAfterResponse($plugin->id, 'install', (int) user()->id);
        Notification::make()->title('Install started')->body($plugin->name.' is installed in the background. You get a notification when it is done.')->info()->send();
    }

    public function uninstallPlugin(string $id): void
    {
        $plugin = Plugin::query()->findOrFail($id);
        abort_unless($this->canChange($plugin) && !in_array($plugin->status->value, ['not_installed', 'errored', 'crashed'], true), 403);
        \Illuminate\Support\Facades\Cache::put('simplify.plugin-queued.'.$id, now()->timestamp, 1800);
        $this->busy = true;
        \Simplify\Panel\Jobs\ChangePlugin::dispatchAfterResponse($plugin->id, 'uninstall', (int) user()->id);
        Notification::make()->title('Uninstall started')->body($plugin->name.' is removed in the background. Its files stay until you delete it.')->info()->send();
    }

    public function deletePlugin(string $id): void
    {
        $plugin = Plugin::query()->findOrFail($id);
        abort_unless(user()?->can('delete', $plugin) && in_array($plugin->status->value, ['not_installed', 'errored', 'crashed'], true), 403);
        app(\App\Services\Helpers\PluginService::class)->deletePlugin($plugin);
        Notification::make()->title($plugin->name.' deleted')->success()->send();
    }

    protected function getHeaderActions(): array
    {
        $import = fn (callable $download) => function (array $data) use ($download): void {
            try {
                $states = PluginUpdater::installStates();
                $id = $download(app(\App\Services\Helpers\PluginService::class), $data);
                // Importing over an installed plugin is an update: it is installed again in the background.
                if (is_string($id) && PluginUpdater::afterImport($id, $states, (int) user()->id)) {
                    $this->busy = true;
                    Notification::make()->title('Plugin imported')->body('It replaces the installed version and is installed again in the background. You get a notification when it is done.')->success()->send();
                    return;
                }
                Notification::make()->title('Plugin imported')->body('Install it from the list to start using it.')->success()->send();
            } catch (\Throwable $exception) {
                report($exception);
                Notification::make()->title('Import failed')->body($exception->getMessage())->danger()->send();
            }
        };
        return [
            \Filament\Actions\Action::make('checkUpdates')->label('Check for updates')->tooltip('Check for updates now')->icon('tabler-refresh')
                ->action(function (): void {
                    \Simplify\Panel\Services\UpdateCheck::forget();
                    $count = count(PluginUpdater::available(true));
                    \Simplify\Panel\Services\PanelUpdates::refresh();
                    Notification::make()->title($count ? $count.' update(s) available' : 'Everything is up to date')->success()->send();
                }),
            \Filament\Actions\Action::make('importUrl')->label('Import from URL')->tooltip('Import from URL')->icon('tabler-world-download')
                ->authorize(fn () => user()?->can('create', Plugin::class))
                ->schema([\Filament\Forms\Components\TextInput::make('url')->label('Zip URL')->required()->url()->endsWith('.zip')])
                ->action($import(fn ($service, array $data) => $service->downloadPluginFromUrl($data['url']))),
            \Filament\Actions\Action::make('importFile')->label('Upload zip')->tooltip('Upload zip')->icon('tabler-file-upload')
                ->authorize(fn () => user()?->can('create', Plugin::class))
                ->schema([\Filament\Forms\Components\FileUpload::make('file')->label('Plugin zip')->required()
                    ->acceptedFileTypes(['application/zip', 'application/zip-compressed', 'application/x-zip-compressed'])
                    ->preserveFilenames()->previewable(false)->storeFiles(false)])
                ->action($import(fn ($service, array $data) => $service->downloadPluginFromFile($data['file']))),
        ];
    }
}

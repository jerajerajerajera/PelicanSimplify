<?php
declare(strict_types=1);
namespace Simplify\Panel\Filament\Admin\Pages;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Simplify\Panel\Services\Announcements;
use Simplify\Panel\Services\PanelUpdates;
/** Announcements (Info, Warning, Success) for everyone or admins only, shown above the server overview. Root admins; opens from Admin tools. */
final class ManageAnnouncements extends Page
{
    protected static ?string $title = 'Announcements';
    protected static ?string $slug = 'simplify-announcements';
    protected string $view = 'pelican-simplify::admin-announcements';
    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool { return (bool) user()?->isRootAdmin(); }

    /** Rows for the view, newest first, with their state (live, scheduled, ended). */
    public function announcements(): array
    {
        $servers = null;
        return array_map(function (array $row) use (&$servers): array {
            $server = $row['button']['server'] ?? null;
            if ($server !== null) $servers ??= $this->serverOptions();
            return [...$row, 'state' => Announcements::state($row), 'target' => $server !== null ? ($servers[$server] ?? 'a deleted server') : ($row['button']['url'] ?? null)];
        }, Announcements::all());
    }

    /** The Panel's own update announcements, read only. */
    public function panelUpdates(): array { return PanelUpdates::announcements(); }

    /** Announcements of the Hub plugin "Announcements", shown in Simplify as well. */
    public function pluginAnnouncements(): array { return Announcements::external(); }

    public function pluginInstalled(): bool { return class_exists('Boy132\\Announcements\\Models\\Announcement'); }

    public function date(?int $at): string { return $at === null ? '' : date('D j M Y H:i', $at); }

    private function serverOptions(): array
    {
        return \App\Models\Server::query()->orderBy('name')->limit(500)->pluck('name', 'uuid')->all();
    }

    private function fields(): array
    {
        return [
            ToggleButtons::make('category')->label('Category')->options(Announcements::CATEGORIES)->inline()->required()->default('info')
                ->colors(['info' => 'info', 'warning' => 'warning', 'success' => 'success'])
                ->icons(['info' => 'tabler-info-circle', 'warning' => 'tabler-alert-triangle', 'success' => 'tabler-circle-check']),
            TextInput::make('title')->label('Title')->required()->maxLength(Announcements::TITLE_LENGTH),
            Textarea::make('text')->label('Message')->rows(3)->maxLength(Announcements::TEXT_LENGTH),
            Grid::make(2)->schema([
                DateTimePicker::make('starts_at')->label('Show from')->seconds(false)->helperText('Empty: right away.'),
                DateTimePicker::make('ends_at')->label('Show until')->seconds(false)->helperText('Empty: until you delete it.'),
            ]),
            ToggleButtons::make('audience')->label('Who sees it')->options(Announcements::AUDIENCES)->inline()->required()->default('everyone'),
            Select::make('link_type')->label('Button')->options(['none' => 'No button', 'server' => 'Open a server', 'url' => 'Open a link'])->default('none')->selectablePlaceholder(false)->live(),
            Select::make('server')->label('Server')->options(fn () => $this->serverOptions())->searchable()->visible(fn ($get) => $get('link_type') === 'server')->required(fn ($get) => $get('link_type') === 'server'),
            TextInput::make('url')->label('Link')->placeholder('https://… or /path on this panel')->maxLength(2048)->visible(fn ($get) => $get('link_type') === 'url')->required(fn ($get) => $get('link_type') === 'url'),
            TextInput::make('button_label')->label('Button text')->placeholder('Open')->maxLength(Announcements::LABEL_LENGTH)->visible(fn ($get) => in_array($get('link_type'), ['server', 'url'], true)),
        ];
    }

    private function saveFrom(array $data, ?string $id): void
    {
        try {
            Announcements::save($data, $id, (string) user()?->username);
        } catch (\InvalidArgumentException $exception) {
            Notification::make()->title('Not saved')->body($exception->getMessage())->danger()->send();
            throw new \Filament\Support\Exceptions\Halt();
        }
        Notification::make()->title($id ? 'Announcement saved' : 'Announcement added')->success()->send();
    }

    public function addAnnouncementAction(): Action
    {
        return Action::make('addAnnouncement')->label('New announcement')->icon('tabler-speakerphone')->modalHeading('New announcement')
            ->modalDescription('Shown as a bar above the server overview and under the bell, to everyone or to admins only.')
            ->schema($this->fields())->modalSubmitActionLabel('Publish')
            ->action(fn (array $data) => $this->saveFrom($data, null));
    }

    public function editAnnouncementAction(): Action
    {
        return Action::make('editAnnouncement')->modalHeading('Edit announcement')->modalSubmitActionLabel('Save')->schema($this->fields())
            ->fillForm(function (array $arguments): array {
                $row = Announcements::find((string) ($arguments['id'] ?? '')) ?? abort(404);
                $button = $row['button'] ?? [];
                return [...$row, 'starts_at' => $row['starts_at'] !== null ? date('Y-m-d H:i:s', $row['starts_at']) : null, 'ends_at' => $row['ends_at'] !== null ? date('Y-m-d H:i:s', $row['ends_at']) : null,
                    'link_type' => isset($button['server']) ? 'server' : (isset($button['url']) ? 'url' : 'none'), 'server' => $button['server'] ?? null, 'url' => $button['url'] ?? null, 'button_label' => $button['label'] ?? null];
            })
            ->action(fn (array $data, array $arguments) => $this->saveFrom($data, (string) $arguments['id']));
    }

    public function endAnnouncementAction(): Action
    {
        return Action::make('endAnnouncement')->requiresConfirmation()->modalHeading('End this announcement now?')
            ->modalDescription('It disappears for everyone but stays in this list, so you can show it again later by editing "Show until".')
            ->modalSubmitActionLabel('End now')
            ->action(function (array $arguments): void {
                $row = Announcements::find((string) ($arguments['id'] ?? '')) ?? abort(404);
                $button = $row['button'] ?? [];
                Announcements::save([...$row, 'ends_at' => time(), 'starts_at' => $row['starts_at'] !== null && $row['starts_at'] < time() ? $row['starts_at'] : null,
                    'link_type' => isset($button['server']) ? 'server' : (isset($button['url']) ? 'url' : 'none'), 'server' => $button['server'] ?? null, 'url' => $button['url'] ?? null, 'button_label' => $button['label'] ?? null], $row['id'], (string) user()?->username);
                Notification::make()->title('Announcement ended')->success()->send();
            });
    }

    public function deleteAnnouncementAction(): Action
    {
        return Action::make('deleteAnnouncement')->requiresConfirmation()->color('danger')->modalHeading('Delete this announcement?')
            ->modalDescription('It disappears for everyone. This cannot be undone.')->modalSubmitActionLabel('Delete')
            ->action(function (array $arguments): void {
                Announcements::delete((string) ($arguments['id'] ?? ''));
                Notification::make()->title('Announcement deleted')->success()->send();
            });
    }
}

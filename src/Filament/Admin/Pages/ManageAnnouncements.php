<?php
declare(strict_types=1);
namespace Simplify\Panel\Filament\Admin\Pages;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Simplify\Panel\Services\Announcements;
use Simplify\Panel\Services\InGameAnnouncements;
use Simplify\Panel\Services\OverviewGroups;
use Simplify\Panel\Services\PanelUpdates;
/** Announcements (Info, Warning, Success) for everyone or admins only, shown above the server overview. Root admins; opens from Admin tools. */
final class ManageAnnouncements extends Page
{
    protected static ?string $title = 'Announcements';
    protected static ?string $slug = 'simplify-announcements';
    protected string $view = 'pelican-simplify::admin-announcements';
    protected static bool $shouldRegisterNavigation = false;
    protected static string|\BackedEnum|null $navigationIcon = 'tabler-speakerphone';

    /** The browser's time zone (set by the view), so "Show from" and "Show until" are the admin's own clock time. */
    public string $browserTimezone = '';

    public static function canAccess(): bool { return (bool) user()?->isRootAdmin(); }

    /** "Only the add-ons": listed in Pelican's own menu under Simplify. */
    public static function shouldRegisterNavigation(): bool { return \Simplify\Panel\Services\FeatureSettings::addonsOnly() && static::canAccess(); }
    public static function getNavigationGroup(): ?string { return 'Simplify'; }
    public static function getNavigationLabel(): string { return 'Announcements'; }
    public static function getNavigationSort(): ?int { return 2; }

    /** ?tz= is the browser's time zone (from "New announcement" in the dashboard's notifications, which opens with ?action=addAnnouncement). */
    public function mount(): void
    {
        $zone = request()->query('tz');
        $this->browserTimezone = is_string($zone) ? substr($zone, 0, 64) : '';
    }

    /** The browser's time zone, else the account's (profile), else the panel's. Times are saved as Unix time, so they do not shift. */
    public function displayTimezone(): string
    {
        $zones = \DateTimeZone::listIdentifiers();
        foreach ([$this->browserTimezone, (string) (user()?->timezone ?? ''), (string) config('app.timezone')] as $zone) if ($zone !== '' && in_array($zone, $zones, true)) return $zone;
        return 'UTC';
    }

    /** Rows for the view, newest first, with their state (live, scheduled, ended). */
    public function announcements(): array
    {
        $servers = null;
        $sent = InGameAnnouncements::sent();
        return array_map(function (array $row) use (&$servers, $sent): array {
            $server = $row['button']['server'] ?? null;
            if ($server !== null) $servers ??= $this->serverOptions();
            $ingame = null;
            if ($row['ingame']['enabled']) {
                $last = $sent[$row['id']] ?? null;
                $next = InGameAnnouncements::next($row);
                $ingame = ['last' => $last !== null ? __('pelican-simplify::announcements.manage.ingame_last', ['summary' => implode(', ', InGameAnnouncements::summary($last)), 'time' => $this->date($last['at'], 'H:i')]) : null,
                    'next' => $next !== null ? ($next <= time() + 60 ? __('pelican-simplify::announcements.manage.ingame_soon') : __('pelican-simplify::announcements.manage.ingame_next', ['time' => $this->date($next, 'D j M H:i')])) : null];
            }
            return [...$row, 'state' => Announcements::state($row), 'target' => $server !== null ? ($servers[$server] ?? __('pelican-simplify::announcements.manage.deleted_server')) : ($row['button']['url'] ?? null), 'ingame_status' => $ingame];
        }, Announcements::all());
    }

    /** The Panel's own update announcements, read only. */
    public function panelUpdates(): array { return PanelUpdates::announcements(); }

    /** Announcements of the Hub plugin "Announcements", shown in Simplify as well. */
    public function pluginAnnouncements(): array { return Announcements::external(); }

    public function pluginInstalled(): bool { return class_exists('Boy132\\Announcements\\Models\\Announcement'); }

    public function date(?int $at, string $format = 'D j M Y H:i T'): string { return $at === null ? '' : (new \DateTimeImmutable('@'.$at))->setTimezone(new \DateTimeZone($this->displayTimezone()))->format($format); }

    private function serverOptions(): array
    {
        return \App\Models\Server::query()->orderBy('name')->limit(500)->pluck('name', 'uuid')->all();
    }

    /** Server groups (Server groups in the overview) with their size: the admin's own, or, when editing, those of the account that picked them. */
    private function groupOptions(mixed $groupsOf): array
    {
        $owner = is_numeric($groupsOf) && (int) $groupsOf !== (int) user()?->id ? \App\Models\User::query()->find((int) $groupsOf) : user();
        $options = [];
        foreach ($owner ? (OverviewGroups::get($owner)['groups'] ?? []) : [] as $group) $options[$group['id']] = $group['name'].' ('.count($group['servers']).' '.(count($group['servers']) === 1 ? 'server' : 'servers').')';
        return $options;
    }

    /** What players read, for the form. */
    private function ingamePreview(callable $get): string
    {
        $title = trim((string) $get('title'));
        return __('pelican-simplify::announcements.form.players_see', ['message' => InGameAnnouncements::message(['title' => $title !== '' ? $title : __('pelican-simplify::announcements.form.your_title'), 'text' => (string) $get('text')])]);
    }

    private function ingameFields(): array
    {
        $pick = fn ($get): bool => $get('ingame_targets') === 'pick';
        return [
            Toggle::make('ingame')->label(__('pelican-simplify::announcements.form.also_send_in_game'))->live()
                ->helperText(__('pelican-simplify::announcements.form.posts_it_in_the_chat_of')),
            Section::make()->compact()->visible(fn ($get): bool => (bool) $get('ingame'))->schema([
                Hidden::make('ingame_groups_of'),
                ToggleButtons::make('ingame_targets')->label(__('pelican-simplify::announcements.form.servers'))->options(\Simplify\Panel\Services\Translations::labels('announcements.targets', InGameAnnouncements::TARGETS))->inline()->default('all')->live(),
                Select::make('ingame_groups')->label(__('pelican-simplify::announcements.form.server_groups'))->multiple()->options(fn ($get): array => $this->groupOptions($get('ingame_groups_of')))->visible($pick)
                    ->helperText(__('pelican-simplify::announcements.form.your_groups_from_server_groups_servers')),
                Select::make('ingame_servers')->label(__('pelican-simplify::announcements.form.servers'))->multiple()->searchable()->options(fn (): array => $this->serverOptions())->visible($pick),
                Select::make('ingame_repeat')->label(__('pelican-simplify::announcements.form.repeat'))->options(\Simplify\Panel\Services\Translations::labels('announcements.repeats', InGameAnnouncements::REPEATS))->default(0)->selectablePlaceholder(false)
                    ->helperText(__('pelican-simplify::announcements.form.repeats_while_the_announcement_shows_the')),
                Text::make(fn ($get): string => $this->ingamePreview($get)),
                Text::make(__('pelican-simplify::announcements.form.games_without_command')),
            ]),
        ];
    }

    private function fields(): array
    {
        return [
            ToggleButtons::make('category')->label(__('pelican-simplify::announcements.form.category'))->options(Announcements::CATEGORIES)->inline()->required()->default('info')
                ->colors(['info' => 'info', 'warning' => 'warning', 'success' => 'success'])
                ->icons(['info' => 'tabler-info-circle', 'warning' => 'tabler-alert-triangle', 'success' => 'tabler-circle-check']),
            TextInput::make('title')->label(__('pelican-simplify::announcements.form.title'))->required()->maxLength(Announcements::TITLE_LENGTH)->live(onBlur: true),
            Textarea::make('text')->label(__('pelican-simplify::announcements.form.message'))->rows(3)->maxLength(Announcements::TEXT_LENGTH)->live(onBlur: true),
            Grid::make(2)->schema([
                // Entered and shown in the admin's own time zone; everyone else sees the times in theirs.
                DateTimePicker::make('starts_at')->label(__('pelican-simplify::announcements.form.show_from'))->seconds(false)->timezone(fn (): string => $this->displayTimezone())->helperText(fn (): string => __('pelican-simplify::announcements.form.empty_right_away', ['timezone' => $this->displayTimezone()])),
                DateTimePicker::make('ends_at')->label(__('pelican-simplify::announcements.form.show_until'))->seconds(false)->timezone(fn (): string => $this->displayTimezone())->helperText(__('pelican-simplify::announcements.form.empty_until_you_delete_it')),
            ]),
            ToggleButtons::make('audience')->label(__('pelican-simplify::announcements.form.who_sees_it'))->options(Announcements::AUDIENCES)->inline()->required()->default('everyone'),
            Select::make('link_type')->label(__('pelican-simplify::announcements.form.button'))->options(['none' => __('pelican-simplify::announcements.form.no_button'), 'server' => __('pelican-simplify::announcements.form.open_a_server'), 'url' => __('pelican-simplify::announcements.form.open_a_link')])->default('none')->selectablePlaceholder(false)->live(),
            Select::make('server')->label(__('pelican-simplify::announcements.form.server'))->options(fn () => $this->serverOptions())->searchable()->visible(fn ($get) => $get('link_type') === 'server')->required(fn ($get) => $get('link_type') === 'server'),
            TextInput::make('url')->label(__('pelican-simplify::announcements.form.link'))->placeholder('https://… or /path on this panel')->maxLength(2048)->visible(fn ($get) => $get('link_type') === 'url')->required(fn ($get) => $get('link_type') === 'url'),
            TextInput::make('button_label')->label(__('pelican-simplify::announcements.form.button_text'))->placeholder(__('pelican-simplify::announcements.form.open'))->maxLength(Announcements::LABEL_LENGTH)->visible(fn ($get) => in_array($get('link_type'), ['server', 'url'], true)),
            Toggle::make('pinned')->label(__('pelican-simplify::announcements.form.pin_to_everyones_notifications'))
                ->helperText(__('pelican-simplify::announcements.form.stays_at_the_top_of_the')),
            Toggle::make('push')->label(__('pelican-simplify::announcements.form.also_send_as_app_notification'))->default(true)
                ->helperText(__('pelican-simplify::announcements.form.to_everyone_who_turned_on_notifications')),
            ...$this->ingameFields(),
        ];
    }

    private function saveFrom(array $data, ?string $id): void
    {
        $old = $id !== null ? Announcements::find($id) : null;
        if (!empty($data['ingame_groups']) && !is_numeric($data['ingame_groups_of'] ?? null)) $data['ingame_groups_of'] = (int) user()?->id;
        try {
            $row = Announcements::save($data, $id, (string) user()?->username, null, (int) user()?->id);
            // Goes out in game again when it was just switched on or its start moved.
            if ($old !== null && (!$old['ingame']['enabled'] || $old['starts_at'] !== $row['starts_at'])) InGameAnnouncements::forget($row['id']);
        } catch (\InvalidArgumentException $exception) {
            Notification::make()->title(__('pelican-simplify::announcements.form.not_saved'))->body($exception->getMessage())->danger()->send();
            throw new \Filament\Support\Exceptions\Halt();
        }
        Notification::make()->title($id ? __('pelican-simplify::announcements.form.saved') : __('pelican-simplify::announcements.form.added'))->success()->send();
    }

    public function addAnnouncementAction(): Action
    {
        return Action::make('addAnnouncement')->label(__('pelican-simplify::announcements.form.new_announcement'))->icon('tabler-speakerphone')->modalHeading(__('pelican-simplify::announcements.form.new_announcement'))
            ->modalDescription(__('pelican-simplify::announcements.form.shown_as_a_bar_above_the'))
            ->schema($this->fields())->modalSubmitActionLabel(__('pelican-simplify::announcements.form.publish'))
            ->action(fn (array $data) => $this->saveFrom($data, null));
    }

    public function editAnnouncementAction(): Action
    {
        return Action::make('editAnnouncement')->modalHeading(__('pelican-simplify::announcements.form.edit_announcement'))->modalSubmitActionLabel(__('pelican-simplify::announcements.form.save'))->schema($this->fields())
            ->fillForm(function (array $arguments): array {
                $row = Announcements::find((string) ($arguments['id'] ?? '')) ?? abort(404);
                $button = $row['button'] ?? [];
                $ingame = $row['ingame'];
                return [...$row, 'ingame' => $ingame['enabled'], 'ingame_targets' => $ingame['targets'], 'ingame_servers' => $ingame['servers'], 'ingame_groups' => $ingame['groups'], 'ingame_repeat' => $ingame['repeat'], 'ingame_groups_of' => $ingame['groups'] !== [] ? $ingame['groups_of'] : null, 'starts_at' => $row['starts_at'] !== null ? date('Y-m-d H:i:s', $row['starts_at']) : null, 'ends_at' => $row['ends_at'] !== null ? date('Y-m-d H:i:s', $row['ends_at']) : null,
                    'link_type' => isset($button['server']) ? 'server' : (isset($button['url']) ? 'url' : 'none'), 'server' => $button['server'] ?? null, 'url' => $button['url'] ?? null, 'button_label' => $button['label'] ?? null];
            })
            ->action(fn (array $data, array $arguments) => $this->saveFrom($data, (string) $arguments['id']));
    }

    public function endAnnouncementAction(): Action
    {
        return Action::make('endAnnouncement')->requiresConfirmation()->modalHeading(__('pelican-simplify::announcements.form.end_this_announcement_now'))
            ->modalDescription(__('pelican-simplify::announcements.form.it_disappears_for_everyone_but_stays'))
            ->modalSubmitActionLabel(__('pelican-simplify::announcements.form.end_now'))
            ->action(function (array $arguments): void {
                $row = Announcements::find((string) ($arguments['id'] ?? '')) ?? abort(404);
                $button = $row['button'] ?? [];
                Announcements::save([...$row, 'ends_at' => time(), 'starts_at' => $row['starts_at'] !== null && $row['starts_at'] < time() ? $row['starts_at'] : null,
                    'link_type' => isset($button['server']) ? 'server' : (isset($button['url']) ? 'url' : 'none'), 'server' => $button['server'] ?? null, 'url' => $button['url'] ?? null, 'button_label' => $button['label'] ?? null], $row['id'], (string) user()?->username);
                Notification::make()->title(__('pelican-simplify::announcements.form.announcement_ended'))->success()->send();
            });
    }

    public function deleteAnnouncementAction(): Action
    {
        return Action::make('deleteAnnouncement')->requiresConfirmation()->color('danger')->modalHeading(__('pelican-simplify::announcements.form.delete_this_announcement'))
            ->modalDescription(__('pelican-simplify::announcements.form.it_disappears_for_everyone_this_cannot'))->modalSubmitActionLabel(__('pelican-simplify::announcements.form.delete'))
            ->action(function (array $arguments): void {
                Announcements::delete((string) ($arguments['id'] ?? ''));
                InGameAnnouncements::forget((string) ($arguments['id'] ?? ''));
                Notification::make()->title(__('pelican-simplify::announcements.form.announcement_deleted'))->success()->send();
            });
    }

    public function ingameCommandsAction(): Action
    {
        return Action::make('ingameCommands')->label(__('pelican-simplify::announcements.form.in_game_commands'))->icon('tabler-message-chatbot')->modalHeading(__('pelican-simplify::announcements.form.in_game_commands'))
            ->modalDescription(__('pelican-simplify::announcements.form.commands_help', ['placeholder' => InGameAnnouncements::PLACEHOLDER]))
            ->modalSubmitActionLabel(__('pelican-simplify::announcements.form.save'))
            ->schema(fn (): array => array_map(fn ($egg) => TextInput::make('egg_'.$egg->id)->label($egg->name)->maxLength(InGameAnnouncements::COMMAND_LENGTH)
                ->placeholder(InGameAnnouncements::defaultCommand((string) $egg->name) ?? __('pelican-simplify::announcements.form.no_command')), $this->eggs()))
            ->fillForm(function (): array {
                $own = InGameAnnouncements::commands();
                $data = [];
                foreach ($this->eggs() as $egg) $data['egg_'.$egg->id] = $own[(int) $egg->id] ?? null;
                return $data;
            })
            ->action(function (array $data): void {
                $commands = [];
                foreach ($data as $key => $command) if (str_starts_with((string) $key, 'egg_')) $commands[(int) substr((string) $key, 4)] = $command;
                try {
                    InGameAnnouncements::saveCommands($commands);
                } catch (\InvalidArgumentException $exception) {
                    Notification::make()->title(__('pelican-simplify::announcements.form.not_saved'))->body($exception->getMessage())->danger()->send();
                    throw new \Filament\Support\Exceptions\Halt();
                }
                Notification::make()->title(__('pelican-simplify::announcements.form.in_game_commands_saved'))->success()->send();
            });
    }

    private function eggs(): array
    {
        return \App\Models\Egg::query()->orderBy('name')->limit(200)->get(['id', 'name'])->all();
    }
}

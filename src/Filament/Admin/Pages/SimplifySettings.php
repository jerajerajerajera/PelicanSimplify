<?php

declare(strict_types=1);

namespace Simplify\Panel\Filament\Admin\Pages;

use App\Models\Egg;
use App\Models\Role;
use Filament\Forms\Components\Repeater;
use App\Models\Node;
use App\Models\Server;
use Simplify\Panel\Services\AppearanceSettings;
use Filament\Forms\Components\ColorPicker;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Simplify\Panel\Services\CreatorAccess;
use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

class SimplifySettings extends Page implements HasForms
{
    use InteractsWithForms;
    use \Livewire\WithFileUploads;
    public $bannerUpload = null;
    public $logoUpload = null;
    public ?string $bannerServer = null;
    public bool $useUploadedBanner = true;
    protected static ?string $navigationLabel = 'PelicanSimplify settings';
    protected static ?string $title = 'PelicanSimplify settings';
    /** With "Only the add-ons" the settings join the Simplify group in Pelican's menu. */
    public static function getNavigationGroup(): ?string { return \Simplify\Panel\Services\FeatureSettings::addonsOnly() ? 'Simplify' : null; }
    public static function getNavigationLabel(): string { return \Simplify\Panel\Services\FeatureSettings::addonsOnly() ? 'Simplify settings' : static::$navigationLabel; }
    public static function getNavigationSort(): ?int { return \Simplify\Panel\Services\FeatureSettings::addonsOnly() ? 9 : null; }

    /** The page draws its own header once set up; the first setup keeps Filament's heading. */
    public function getHeading(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return $this->needsSetup() ? static::$title : '';
    }
    protected static ?string $slug = 'pelican-simplify-settings';
    protected string $view = 'pelican-simplify::admin-settings';
    public static function getNavigationIcon(): \Illuminate\Contracts\Support\Htmlable
    {
        return new \Illuminate\Support\HtmlString(view('pelican-simplify::simplify-icon')->render());
    }
    public ?array $data = [];
    public array $requirements = [];
    public bool $setupStarted = false;
    public bool $showDiagnostics = false;
    #[\Livewire\Attributes\Locked] public bool $embedded = false;
    /** How many Pelican tabs close the settings menu; the view draws the "Pelican" heading above them. */
    public int $pelicanTabs = 0;
    public function bannerServers(): array
    {
        abort_unless(static::canAccess(),403);
        return Server::query()->orderBy('name')->pluck('name','uuid')->all();
    }
    public function uploadBanner(): void
    {
        abort_unless(static::canAccess(),403);
        $this->validate(['bannerServer'=>'required|string|exists:servers,uuid','bannerUpload'=>'required|file|max:'.max(1,(int)floor(\Simplify\Panel\Services\UploadLimits::bannerBytes()/1024))]);
        $server=Server::query()->where('uuid',$this->bannerServer)->firstOrFail();
        $banner=\Simplify\Panel\Services\ServerBanners::upload($server,$this->bannerUpload);
        if($this->useUploadedBanner)\Simplify\Panel\Services\ServerArtwork::save($server,$banner['id']);
        $this->bannerUpload=null;
        Notification::make()->success()->title(__('pelican-simplify::settings.form.banner_uploaded'))->send();
    }
    public function uploadLogo(): void
    {
        abort_unless(static::canAccess(),403);
        $this->validate(['logoUpload'=>'required|file|max:'.\Simplify\Panel\Services\BrandSettings::LOGO_MAX_KB]);
        $name=\Simplify\Panel\Services\BrandSettings::storeLogo($this->logoUpload);
        AppearanceSettings::setLogo($name);
        $this->logoUpload=null;
        data_set($this->data,'appearance.brand.mode','logo');
        Notification::make()->success()->title(__('pelican-simplify::settings.form.logo_uploaded'))->body(__('pelican-simplify::settings.form.it_shows_in_the_top_left'))->send();
    }
    public function removeLogo(): void
    {
        abort_unless(static::canAccess(),403);
        AppearanceSettings::setLogo(null);
        data_set($this->data,'appearance.brand.mode','text');
        Notification::make()->success()->title(__('pelican-simplify::settings.form.logo_removed'))->send();
    }
    public function brandInfo(): array
    {
        abort_unless(static::canAccess(),403);
        $brand=\Simplify\Panel\Services\BrandSettings::forDisplay(AppearanceSettings::all()['brand']);
        return ['logo_url'=>$brand['logo_url'],'fonts'=>array_map(fn($font)=>$font[1],\Simplify\Panel\Services\BrandSettings::FONTS),'panel_name'=>(string)config('app.name','Pelican')];
    }
    public function bannerLibrary(): array
    {
        abort_unless(static::canAccess(),403);
        return \Simplify\Panel\Services\ServerBanners::entries();
    }
    public function deleteBanner(string $id): void
    {
        abort_unless(static::canAccess(),403);
        \Simplify\Panel\Services\ServerBanners::delete($id);
        Notification::make()->success()->title(__('pelican-simplify::settings.form.banner_removed'))->send();
    }
    public function refreshRequirements(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->requirements = \Simplify\Panel\Services\DependencyCheck::inspect(base_path(), plugin_path('pelican-simplify'));
    }
    public function requirementsReady(): bool
    {
        return \Simplify\Panel\Services\DependencyCheck::ready($this->requirements);
    }
    public function beginSetup(): void
    {
        $this->refreshRequirements();
        $this->setupStarted = $this->requirementsReady();
    }
    public function toggleDiagnostics(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->showDiagnostics = !$this->showDiagnostics;
        if ($this->showDiagnostics) $this->refreshRequirements();
    }
    public function requirementGroups(): array
    {
        $groups = [];
        foreach ($this->requirements as $check) {
            $name = $check['name'];
            $group = match (true) {
                $name === 'Tested Pelican release' => 'Panel services',
                ($check['blocking'] ?? true) === false => 'Upload capacity',
                str_starts_with($name, 'PHP') => 'PHP runtime',
                str_starts_with($name, 'Composer:') || str_contains($name, 'Composer') || str_contains($name, 'Tabler') => 'Panel components',
                str_starts_with($name, 'Plugin asset:') || str_starts_with($name, 'Built ') || str_starts_with($name, 'Node.') || $name === 'Yarn' => 'Styles and scripts',
                str_starts_with($name, 'Writable ') || str_contains($name, 'storage') => 'Storage permissions',
                default => 'Panel services',
            };
            $groups[$group][] = $check;
        }
        return $groups;
    }

    public static function canAccess(): bool { return (bool) user()?->isRootAdmin(); }
    /** True while an administrator walks the setup wizard again; current settings stay active until it is saved. */
    public bool $rerunSetup = false;
    public function needsSetup(): bool { return $this->rerunSetup || !\Simplify\Panel\Services\FeatureSettings::enabled('setup_completed'); }
    /** Opens the first-setup wizard again (system checks first), prefilled with the current settings. */
    public function startSetupAgain(): void
    {
        abort_unless(static::canAccess() && !$this->embedded, 403);
        $this->rerunSetup = true;
        $this->setupStarted = false;
        $this->showDiagnostics = false;
        $this->refreshRequirements();
    }
    public function cancelSetupAgain(): void
    {
        $this->rerunSetup = false;
        $this->setupStarted = false;
    }
    /** Servers currently shown on the website (Website embed add-on, or the website plugin). */
    private function embedServers(): array
    {
        if (!\Simplify\Panel\Services\WebsiteListing::available()) return [];
        return Server::query()->orderBy('name')->limit(500)->get()->filter(fn (Server $s) => \Simplify\Panel\Services\WebsiteListing::visible($s))->pluck('uuid')->values()->all();
    }
    public function updateStatus(): ?array { return \Simplify\Panel\Services\UpdateCheck::status(); }
    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->embedded = request()->boolean('simplify_embed');
        $this->form->fill([...CreatorAccess::configuration(),'creation_defaults'=>\Simplify\Panel\Services\CreationDefaults::all(),'appearance'=>AppearanceSettings::all(),'features'=>\Simplify\Panel\Services\FeatureSettings::all(),'experience'=>(\Simplify\Panel\Services\FeatureSettings::all()['addons_only']??false)?'addons':'full','egg_updates'=>class_exists(\Simplify\Panel\Services\EggUpdates::class)?\Simplify\Panel\Services\EggUpdates::settings():[],'update_channel'=>\Simplify\Panel\Services\FeatureSettings::enabled('simplify_snapshot')?'snapshot':(\Simplify\Panel\Services\FeatureSettings::enabled('simplify_beta')?'beta':'stable'),'performance'=>\Simplify\Panel\Services\PerformanceSettings::all(),'roles'=>\Simplify\Panel\Services\RoleAccess::all(),'web'=>\Simplify\Panel\Services\Extensions::webInstalled()?app('simplify.web')->options():[],'external_shown'=>self::externalShown(),'subdomains'=>class_exists(\Simplify\Panel\Services\Subdomains::class)?\Simplify\Panel\Services\Subdomains::settings():[],'curseforge'=>class_exists(\Simplify\Panel\Services\CurseForge::class)?['key'=>'','remove_key'=>false,'user_keys'=>\Simplify\Panel\Services\CurseForge::allowUserKeys()]:[],'embed'=>class_exists(\Simplify\Panel\Services\WebsiteEmbed::class)?[...\Simplify\Panel\Services\WebsiteEmbed::settings(),'servers'=>$this->embedServers()]:[],'embed_code'=>['show'=>'all'],'explainer'=>class_exists(\Simplify\Panel\Services\ErrorExplainer::class)?[...array_intersect_key(\Simplify\Panel\Services\ErrorExplainer::settings(),array_flip(['provider','model','audience'])),'key'=>'','remove_key'=>false]:[]]);
    }
    /** Settings categories, in order. The first setup walks the same pages; the banner library is added afterwards. */
    private const CATEGORIES = [
        'General' => 'tabler-layout-dashboard',
        'Appearance' => 'tabler-palette',
        'Users & access' => 'tabler-users',
        'Backups & performance' => 'tabler-gauge',
    ];
    /** Optional add-ons, switched on under General → Add-ons; each keeps its own settings tab after the core categories. */
    private const ADDONS = [
        'Easy Minecraft server creator' => 'tabler-server-bolt',
        'Easy server creator' => 'tabler-device-gamepad-2',
        'People & roles' => 'tabler-users-group',
        'Plugin manager' => 'tabler-plug',
        'Automatic subdomains' => 'tabler-world-www',
    ];

    /** Add-ons set up later from their own tab, not during the first setup. */
    private const LATER_ADDONS = [
        'Error explanations' => 'tabler-help-circle',
        'Plugin sites' => 'tabler-puzzle',
        'Website embed' => 'tabler-code',
    ];

    /** Pages other plugins add (empty when the service is not available, as in unit tests). */
    private static function externalCatalog(): array
    {
        return class_exists(\Simplify\Panel\Services\ExternalPlugins::class) ? \Simplify\Panel\Services\ExternalPlugins::catalog() : [];
    }
    private static function externalShown(): array
    {
        return class_exists(\Simplify\Panel\Services\ExternalPlugins::class) ? array_values(array_diff(array_keys(self::externalCatalog()), \Simplify\Panel\Services\ExternalPlugins::hidden())) : [];
    }

    /** Feature toggles of one group (FeatureSettings::definitions()[key][3]) as a titled section. */
    private static function featureSection(string $title, string $group, ?string $description = null, bool $live = false): Section
    {
        $definitions = array_filter(\Simplify\Panel\Services\FeatureSettings::definitions(), fn ($item) => ($item[3] ?? 'Server features') === $group);
        $section = Section::make($title)->extraAttributes(['class' => 'ps-setting-list'])->schema(array_map(fn (string $key) => self::featureToggle($key, $live), array_keys($definitions)));
        return $description ? $section->description($description) : $section;
    }

    /** Ability checkboxes plus limits for one profile; $path null means "relative to a repeater item". */
    private static function abilityFields(?string $path): array
    {
        $name = fn (string $key): string => $path ? $path.'.'.$key : $key;
        $abilities = \Simplify\Panel\Services\RoleAccess::ABILITIES;
        $limits = ['max_servers' => 'Max. servers', 'max_memory_mb' => 'Total RAM (MB)', 'max_disk_mb' => 'Total disk (MB)', 'max_cpu' => 'CPU per server (%)', 'max_backups' => 'Backups per server'];
        $limitFields = [];
        foreach (\Simplify\Panel\Services\RoleAccess::LIMITS as $key => [$default, $min, $max]) {
            $limitFields[] = TextInput::make($name($key))->label($limits[$key])->integer()->minValue($min)->maxValue($max)->required()->default($default);
        }
        return [
            \Filament\Forms\Components\CheckboxList::make($name('abilities'))->label(__('pelican-simplify::settings.form.can'))->columns(2)->bulkToggleable()
                ->options(array_map(fn (array $a): string => $a[0], $abilities))
                ->descriptions(array_map(fn (array $a): string => $a[1], $abilities)),
            \Filament\Schemas\Components\Grid::make(5)->schema($limitFields),
        ];
    }

    /** Which Simplify releases the plugin manager offers; stored as the simplify_beta and simplify_snapshot switches. */
    private const UPDATE_CHANNELS = ['stable' => 'Stable', 'beta' => 'Beta', 'snapshot' => 'Snapshot'];

    private static function featureToggle(string $key, bool $live = false): Toggle
    {
        $definition = \Simplify\Panel\Services\FeatureSettings::definitions()[$key] ?? [$key, null];
        $overlap = class_exists(\Simplify\Panel\Services\ExternalPlugins::class) ? \Simplify\Panel\Services\ExternalPlugins::overlapFor($key) : null;
        // The saved choice is kept; it applies again once the overlapping plugin is disabled.
        $help = $overlap ? 'Off while the '.$overlap['name'].' plugin is enabled. '.$overlap['reason'] : $definition[1];
        $toggle = Toggle::make('features.'.$key)->label($definition[0])->helperText($help);
        return $live ? $toggle->live() : $toggle;
    }

    /** Full Simplify or only the add-ons; stored as the addons_only switch. */
    private const EXPERIENCES = [
        'full' => ['Full Simplify', 'Simplify becomes the panel for you and your players: dashboard and server pages in place of Pelican\'s, host mode, themes and all add-ons.'],
        'addons' => ['Only the add-ons', 'Pelican keeps its own dashboard, server pages and look. The add-ons you switch on appear in Pelican\'s own menu, under Simplify.'],
    ];
    private static function experienceSection(): Section
    {
        return Section::make('How much Simplify?')->description(__('pelican-simplify::settings.form.use_simplify_as_your_whole_panel'))->schema([
            \Filament\Forms\Components\Radio::make('experience')->hiddenLabel()->required()->live()
                ->options(array_map(fn (array $e) => $e[0], self::EXPERIENCES))->descriptions(array_map(fn (array $e) => $e[1], self::EXPERIENCES)),
        ]);
    }
    private static function full(Get $get): bool { return $get('experience') !== 'addons'; }

    public function form(Schema $schema): Schema
    {
        $performance = \Simplify\Panel\Services\PerformanceSettings::FIELDS;
        $numberField = static fn (string $key) => TextInput::make('performance.'.$key)->label($performance[$key][0])->helperText($performance[$key][1])
            ->integer()->minValue($performance[$key][3])->maxValue($performance[$key][4])->required();

        $categories = [
            'General' => [
                self::experienceSection(),
                self::featureSection('Simplify interface', 'Interface', 'What Simplify replaces in Pelican.')->visible(fn (Get $get) => self::full($get)),
                self::featureSection('Server workspaces', 'Server features', 'Workspaces shown on every server page. Pelican permissions still decide who may use them.')->visible(fn (Get $get) => self::full($get)),
                self::featureSection('Add-ons', 'Add-ons', 'Optional parts of Simplify. Each add-on has its own settings tab at the bottom of the menu.'),
                Section::make('Pages from other plugins')->visible(fn (Get $get) => self::full($get))->description(__('pelican-simplify::settings.form.pages_that_other_installed_plugins_add'))->schema([
                    \Filament\Forms\Components\CheckboxList::make('external_shown')->hiddenLabel()->options(fn () => self::externalCatalog())->bulkToggleable()->columns(1)
                        ->visible(fn () => self::externalCatalog() !== []),
                    \Filament\Schemas\Components\Text::make('No other plugins add pages right now.')->visible(fn () => self::externalCatalog() === []),
                ]),
            ],
            'Appearance' => [
                self::featureSection('Theme', 'Appearance'),
                Section::make('Colours')->columns(2)->schema([
                    ColorPicker::make('appearance.accent_color')->view('pelican-simplify::filament.admin.accent-picker')->label(__('pelican-simplify::settings.form.panel_accent_color'))->hex()->required()->rules(['regex:/^#[0-9a-fA-F]{6}$/'])->helperText(__('pelican-simplify::settings.form.used_throughout_the_shared_interface_and'))->columnSpanFull(),
                    Toggle::make('appearance.individual_colors')->label(__('pelican-simplify::settings.form.use_separate_server_colors'))->helperText(__('pelican-simplify::settings.form.on_assigns_a_color_to_every'))->columnSpanFull(),
                    Toggle::make('appearance.seasonal')->label(__('pelican-simplify::settings.form.seasonal_touches'))->helperText(__('pelican-simplify::settings.form.a_few_small_halloween_decorations_for'))->columnSpanFull(),
                ]),
                Section::make('Panel name & logo')->description(__('pelican-simplify::settings.form.how_your_panel_name_looks_in'))->columns(2)->schema([
                    \Filament\Schemas\Components\View::make('pelican-simplify::filament.admin.brand-editor')->columnSpanFull(),
                    Select::make('appearance.brand.mode')->label(__('pelican-simplify::settings.form.show'))->options(['text'=>__('pelican-simplify::settings.form.styled_text'),'logo'=>'Uploaded logo'])->selectablePlaceholder(false)->required()->live()
                        ->helperText(fn()=>AppearanceSettings::all()['brand']['logo']?null:'Upload a logo above to use one.')
                        ->disableOptionWhen(fn(string $value)=>$value==='logo'&&!AppearanceSettings::all()['brand']['logo']),
                    TextInput::make('appearance.brand.text')->label(__('pelican-simplify::settings.form.text'))->maxLength(40)->placeholder(fn()=>(string)config('app.name','Pelican'))->helperText(__('pelican-simplify::settings.form.empty_uses_the_panel_name')),
                    Select::make('appearance.brand.font')->label(__('pelican-simplify::settings.form.font'))->options(array_map(fn($font)=>$font[0],\Simplify\Panel\Services\BrandSettings::FONTS))->selectablePlaceholder(false)->required(),
                    Select::make('appearance.brand.weight')->label(__('pelican-simplify::settings.form.weight'))->options([400=>__('pelican-simplify::settings.form.regular'),500=>__('pelican-simplify::settings.form.medium'),600=>__('pelican-simplify::settings.form.semibold'),700=>__('pelican-simplify::settings.form.bold'),800=>__('pelican-simplify::settings.form.extra_bold'),900=>'Black'])->selectablePlaceholder(false)->required(),
                    Select::make('appearance.brand.color_style')->label(__('pelican-simplify::settings.form.color'))->options(['gradient'=>__('pelican-simplify::settings.form.fade_between_two_colors'),'solid'=>'One color'])->selectablePlaceholder(false)->required()->live(),
                    TextInput::make('appearance.brand.spacing')->label(__('pelican-simplify::settings.form.letter_spacing'))->integer()->minValue(-5)->maxValue(30)->suffix('/100 em')->required(),
                    ColorPicker::make('appearance.brand.color1')->label(fn(Get $get)=>$get('appearance.brand.color_style')==='solid'?'Text color':'Start color')->hex()->rules(['nullable','regex:/^#[0-9a-fA-F]{6}$/'])->helperText(__('pelican-simplify::settings.form.empty_follows_the_light_dark_theme')),
                    ColorPicker::make('appearance.brand.color2')->label(__('pelican-simplify::settings.form.end_color'))->hex()->rules(['nullable','regex:/^#[0-9a-fA-F]{6}$/'])->helperText(__('pelican-simplify::settings.form.empty_uses_the_panel_accent'))->visible(fn(Get $get)=>$get('appearance.brand.color_style')!=='solid'),
                    Toggle::make('appearance.brand.uppercase')->label(__('pelican-simplify::settings.form.capital_letters')),
                    Toggle::make('appearance.brand.suffix')->label(__('pelican-simplify::settings.form.show_pelican_after_the_name')),
                    TextInput::make('appearance.brand.logo_height')->label(__('pelican-simplify::settings.form.logo_height'))->integer()->minValue(16)->maxValue(64)->suffix('px')->required(),
                    Toggle::make('appearance.brand.logo_text')->label(__('pelican-simplify::settings.form.show_the_text_next_to_the')),
                ]),
            ],
            'Users & access' => [
                self::featureSection('Visibility', 'Access and visibility', 'Who sees which servers, and who may share them.')->visible(fn (Get $get) => self::full($get)),
                Section::make('Everyone')->description(__('pelican-simplify::settings.form.applies_to_every_signed_in_user'))
                    ->schema(self::abilityFields('roles.everyone')),
                Section::make('Roles')->description(__('pelican-simplify::settings.form.roles_and_their_permissions_are_managed'))
                    ->visible(fn () => \Simplify\Panel\Services\FeatureSettings::enabled('people_manager')),
                Section::make('Roles')->description(__('pelican-simplify::settings.form.members_of_a_pelican_role_get'))->visible(fn () => !\Simplify\Panel\Services\FeatureSettings::enabled('people_manager'))->schema([
                    Repeater::make('roles.roles')->hiddenLabel()->defaultItems(0)->collapsible()->addActionLabel(__('pelican-simplify::settings.form.add_role'))
                        ->itemLabel(fn (array $state): ?string => isset($state['role_id']) ? (Role::query()->whereKey($state['role_id'])->value('name') ?? 'Role') : 'New role')
                        ->schema([
                            Select::make('role_id')->label(__('pelican-simplify::settings.form.pelican_role'))->required()->distinct()->searchable()->options(fn () => Role::query()->orderBy('name')->pluck('name', 'id')->all()),
                            ...self::abilityFields(null),
                        ]),
                ]),
                ...(CreatorAccess::legacyUserGrants() > 0 ? [
                    Section::make('Individual user grants from an earlier version')->description(CreatorAccess::legacyUserGrants().' user(s) still have a personal creator grant or limit. They keep working; move these users into a role and remove the old grants below.')->schema([
                        Toggle::make('clear_legacy_user_grants')->label(__('pelican-simplify::settings.form.remove_the_old_individual_grants_when')),
                    ]),
                ] : []),
            ],
            'Backups & performance' => [
                Section::make('Automatic backups')->columns(2)->description(__('pelican-simplify::settings.form.defaults_for_new_servers_existing_servers'))->schema([
                    self::featureToggle('automatic_backups'),
                    Toggle::make('performance.backup_only_when_used')->label(__('pelican-simplify::settings.form.skip_backups_while_a_server_has'))->helperText(__('pelican-simplify::settings.form.an_offline_server_that_has_not')),
                    ...array_map($numberField, ['backup_interval_minutes', 'backup_retention']),
                ]),
                Section::make('Refresh rates')->columns(2)->description(__('pelican-simplify::settings.form.lower_values_feel_livelier_but_cost'))
                    ->schema(array_map($numberField, ['overview_seconds', 'server_seconds', 'restart_seconds', 'health_seconds'])),
            ],
            'Easy Minecraft server creator' => [
                Section::make('Self-service creation')->columns(2)->description(__('pelican-simplify::settings.form.where_users_may_create_minecraft_servers'))->schema([
                    Select::make('node_ids')->label(__('pelican-simplify::settings.form.hosting_machines'))->multiple()->searchable()->options(fn () => Node::query()->pluck('name', 'id')->all())->helperText(__('pelican-simplify::settings.form.nodes_used_for_self_service_creation')),
                    Toggle::make('minecraft_enabled')->label(__('pelican-simplify::settings.form.allow_minecraft')),
                ]),
                Section::make('Starting resources')->description(__('pelican-simplify::settings.form.cpu_ram_and_disk_for_each'))
                    ->schema(array_map(fn (string $type, string $label) => Section::make($label)->columns(3)->schema([
                        TextInput::make('creation_defaults.'.$type.'.cpu')->label(__('pelican-simplify::settings.form.cpu'))->integer()->minValue(1)->maxValue(1600)->required()->helperText('100% is one core; 200% is two cores.'),
                        TextInput::make('creation_defaults.'.$type.'.ram_gb')->label(__('pelican-simplify::settings.form.ram_gb'))->integer()->minValue(1)->maxValue(128)->required(),
                        TextInput::make('creation_defaults.'.$type.'.disk_mb')->label(__('pelican-simplify::settings.form.disk_mb'))->integer()->minValue(1024)->maxValue(10485760)->required(),
                    ]), ['vanilla', 'plus', 'loader', 'modded', 'bedrock', 'proxy'], ['Vanilla', 'Plugins', 'Mods', 'Modpacks', 'Bedrock', 'Proxy'])),
            ],
            'Easy server creator' => [
                Section::make('Games for self-service')->description(__('pelican-simplify::settings.form.games_users_may_create_with_the'))->schema([
                    Select::make('other_egg_ids')->label(__('pelican-simplify::settings.form.approved_games'))->multiple()->searchable()->options(fn () => Egg::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->helperText(__('pelican-simplify::settings.form.a_game_installed_from_the_egg')),
                ]),
                Section::make('Artwork')->extraAttributes(['class' => 'ps-setting-list'])->schema([self::featureToggle('steam_art')]),
                Section::make('Installing new games')->description(__('pelican-simplify::settings.form.administrators_can_always_install_a_game')),
            ],
            'People & roles' => [
                Section::make('How it works')->description(__('pelican-simplify::settings.form.with_let_simplify_manage_people_roles'))->schema([
                    \Filament\Schemas\Components\Text::make('People: invite someone by username and email; they get an email to choose their own password (nobody else ever sees it). Assign roles, send a password reset link, or remove people who own no servers.'),
                    \Filament\Schemas\Components\Text::make('Roles: each role combines Simplify permissions and limits (what members may do on their servers) with optional admin panel access. They are Pelican roles, so Pelican and other plugins keep working with them.'),
                    \Filament\Schemas\Components\Text::make('Safety: only root admins manage people and roles, the Root Admin role is locked, you cannot remove your own root access, and the last root admin can never be removed or deleted.'),
                ]),
            ],
            'Plugin manager' => [
                Section::make('Plugin updates')->description(__('pelican-simplify::settings.form.applies_to_every_installed_plugin_with'))->schema([self::featureToggle('auto_update_plugins'), Select::make('update_channel')->label(__('pelican-simplify::settings.form.simplify_versions'))->options(self::UPDATE_CHANNELS)->selectablePlaceholder(false)->required()
                    ->helperText(__('pelican-simplify::settings.form.stable_only_stable_releases_beta_also'))]),
            ],
            'Automatic subdomains' => [
                Section::make('Addresses')->columns(2)->description(__('pelican-simplify::settings.form.a_server_named_oak_gets_oak'))->schema([
                    TextInput::make('subdomains.domain')->label(__('pelican-simplify::settings.form.domain'))->dehydrateStateUsing(fn ($state) => strtolower(trim((string) $state)))->rules(['nullable','regex:/^(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\\.)+[a-z]{2,}$/'])->validationMessages(['regex' => 'Enter a host name such as example.com.'])->placeholder('example.com')->maxLength(200)->helperText(__('pelican-simplify::settings.form.the_cloudflare_zone_the_subdomains_are')),
                    TextInput::make('subdomains.target')->label(__('pelican-simplify::settings.form.game_address'))->dehydrateStateUsing(fn ($state) => strtolower(trim((string) $state)))->rules(['nullable','regex:/^(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\\.)+[a-z]{2,}$/'])->validationMessages(['regex' => 'Enter a host name such as example.com.'])->placeholder('play.example.com')->maxLength(253)->helperText(__('pelican-simplify::settings.form.an_existing_dns_only_record_that')),
                    TextInput::make('subdomains.port_min')->label(__('pelican-simplify::settings.form.first_forwarded_port'))->integer()->minValue(1)->maxValue(65535)->required(),
                    TextInput::make('subdomains.port_max')->label(__('pelican-simplify::settings.form.last_forwarded_port'))->integer()->minValue(1)->maxValue(65535)->required()->gte('subdomains.port_min')->helperText(__('pelican-simplify::settings.form.only_servers_with_a_port_in')),
                ]),
                Section::make('Cloudflare token')->description(__('pelican-simplify::settings.form.create_a_cloudflare_api_token_with'))->schema([
                    \Filament\Schemas\Components\Text::make(fn () => ($missing = \Simplify\Panel\Services\Subdomains::missing()) ? 'Still missing: '.implode(', ', $missing).'.' : 'Ready: new subdomains are connected automatically.'),
                ]),
            ],
        ];
        $icons = [...self::CATEGORIES, ...self::ADDONS];
        $addons = array_intersect_key($categories, self::ADDONS);
        $categories = array_diff_key($categories, self::ADDONS);
        if (\Simplify\Panel\Services\Extensions::webInstalled()) {
            $categories['Website'] = app('simplify.web')->settingsSections();
            $icons['Website'] = 'tabler-world';
        }
        if ($this->needsSetup()) {
            return $schema->statePath('data')->components([
                \Filament\Schemas\Components\Wizard::make($this->setupWizardSteps($categories, $addons))->skippable(false)->columnSpanFull()
                    ->nextAction(fn (\Filament\Actions\Action $action) => $action->label(__('pelican-simplify::settings.form.next'))->color('gray')->extraAttributes(['class' => 'ssc-next-button']))
                    ->previousAction(fn (\Filament\Actions\Action $action) => $action->label(__('pelican-simplify::settings.form.back'))->color('gray')->extraAttributes(['class' => 'ssc-back-button']))
                    ->submitAction(new \Illuminate\Support\HtmlString(view('pelican-simplify::setup-submit')->render())),
            ]);
        }
        // Server banners sit with the other appearance settings; the add-ons follow in the order of their switches under General.
        $banners = ['Server banners' => [\Filament\Schemas\Components\View::make('pelican-simplify::banner-library')]];
        $categories = [...array_slice($categories, 0, 2, true), ...$banners, ...array_slice($categories, 2, null, true)];
        $icons['Server banners'] = 'tabler-photo';
        $categories['Egg updates'] = [
            Section::make('Egg updates')->description(__('pelican-simplify::settings.form.simplify_checks_every_egg_for_a'))->schema([
                Select::make('egg_updates.mode')->label(__('pelican-simplify::settings.form.updates'))->options(\Simplify\Panel\Services\EggUpdates::MODES)->selectablePlaceholder(false)->required()
                    ->helperText(__('pelican-simplify::settings.form.eggs_that_servers_use_are_only')),
            ]),
            \Filament\Schemas\Components\View::make('pelican-simplify::egg-updates'),
        ];
        $icons['Egg updates'] = 'tabler-egg';
        $addons['Error explanations'] = $this->errorExplainerSections();
        if (class_exists(\Simplify\Panel\Services\CurseForge::class)) $addons['Plugin sites'] = $this->pluginSiteSections();
        if (class_exists(\Simplify\Panel\Services\WebsiteEmbed::class)) $addons['Website embed'] = $this->websiteEmbedSections();
        $icons = [...$icons, ...self::LATER_ADDONS];
        $categories = [...$categories, ...$addons];
        // "Only the add-ons" keeps Pelican's look and pages: the tabs that only serve the full panel step aside.
        $fullOnly = ['Appearance', 'Server banners', 'Backups & performance'];
        $tabList = array_map(fn (string $label, array $content) => \Filament\Schemas\Components\Tabs\Tab::make($label)->visible(in_array($label, $fullOnly, true) ? fn (Get $get) => self::full($get) : true)->icon($icons[$label] ?? null)->badge(isset(self::ADDONS[$label]) || isset(self::LATER_ADDONS[$label]) ? 'Add-on' : null)->schema($content), array_keys($categories), array_values($categories));
        // Pelican's own settings close the list: each tab shows Pelican's page on that tab, so all settings live in one place.
        $pelican = \Simplify\Panel\Services\PelicanSettingsTabs::all();
        $this->pelicanTabs = count($pelican);
        foreach ($pelican as $tab) {
            $tabList[] = \Filament\Schemas\Components\Tabs\Tab::make($tab['label'])->key('pelican-'.$tab['key'])->id('pelican-'.$tab['key'])->icon($tab['icon'])
                ->schema([\Filament\Schemas\Components\View::make('pelican-simplify::filament.admin.pelican-settings-frame')->viewData(['url' => $tab['url'], 'label' => $tab['label'], 'key' => 'pelican-'.$tab['key']])]);
        }
        $tabs = \Filament\Schemas\Components\Tabs::make('Simplify settings')->columnSpanFull()->extraAttributes(['class' => 'ps-settings-tabs'])->tabs($tabList);
        if (method_exists($tabs, 'vertical')) $tabs->vertical();
        if (method_exists($tabs, 'persistTabInQueryString')) $tabs->persistTabInQueryString('section');
        return $schema->statePath('data')->components([$tabs]);
    }
    /** "Explain this error" on server pages, paid by the admin's own API key for Claude, GPT or Gemini. */
    private function errorExplainerSections(): array
    {
        $explainer = \Simplify\Panel\Services\ErrorExplainer::class;
        $provider = fn (Get $get) => $explainer::provider((string) $get('explainer.provider'));
        return [
            Section::make('Explain this error')->columns(2)->description(__('pelican-simplify::settings.form.adds_an_explain_button_to_the'))->schema([
                Select::make('explainer.provider')->label(__('pelican-simplify::settings.form.ai'))->options($explainer::providers())->selectablePlaceholder(false)->required()->live()
                    ->afterStateUpdated(function (Set $set, ?string $state) use ($explainer) { $set('explainer.model', array_key_first($explainer::models($state))); $set('explainer.key', ''); $set('explainer.remove_key', false); }),
                Select::make('explainer.model')->label(__('pelican-simplify::settings.form.model'))->options(fn (Get $get) => $explainer::models((string) $get('explainer.provider')))->selectablePlaceholder(false)->required(),
                TextInput::make('explainer.key')->label(fn (Get $get) => $provider($get)::name().' API key')->password()->autocomplete(false)->maxLength(300)->columnSpanFull()
                    ->placeholder(fn (Get $get) => ($explainer::settings()['saved_keys'][(string) $get('explainer.provider')] ?? false) ? 'A key is saved. Leave empty to keep it.' : $provider($get)::keyPlaceholder())
                    ->helperText(fn (Get $get) => $provider($get)::keyHelp().' Simplify stores the key encrypted and never shows it again.'),
                Select::make('explainer.audience')->label(__('pelican-simplify::settings.form.who_can_use_it'))->options($explainer::AUDIENCES)->selectablePlaceholder(false)->required()
                    ->helperText('Each person can ask '.$explainer::PER_USER.' questions per 10 minutes, the whole panel '.$explainer::PER_DAY.' per day.'),
                Toggle::make('explainer.remove_key')->label(fn (Get $get) => 'Remove the saved '.$provider($get)::name().' key when saving')->helperText(__('pelican-simplify::settings.form.turns_error_explanations_off_while_this'))->visible(fn (Get $get) => $explainer::settings()['saved_keys'][(string) $get('explainer.provider')] ?? false)->columnSpanFull(),
                \Filament\Schemas\Components\Actions::make([
                    \Filament\Actions\Action::make('testErrorExplainer')->button()->label(__('pelican-simplify::settings.form.test_key'))->icon('tabler-plug-connected')->color('gray')->action(fn () => $this->testErrorExplainer()),
                ])->columnSpanFull(),
            ]),
            Section::make('When the panel itself breaks')->description(fn () => 'If the panel shows an error such as 500, open '.route('simplify.explain').' to check the panel and Wings logs: that plain page works without the normal panel pages. If you cannot sign in, run "php artisan simplify:explain" in the panel folder on the server, or "php artisan simplify:explain --url" for a temporary address to that page.')->schema([]),
        ];
    }
    /** Plugin search on server pages: Modrinth, Hangar and SpigotMC need nothing; CurseForge needs a key of this panel. */
    private function pluginSiteSections(): array
    {
        $saved = \Simplify\Panel\Services\CurseForge::panelHasKey();
        return [
            Section::make('CurseForge')->description(__('pelican-simplify::settings.form.plugins_and_mods_are_found_on'))->schema([
                TextInput::make('curseforge.key')->label(__('pelican-simplify::settings.form.curseforge_api_key'))->password()->autocomplete(false)->maxLength(200)
                    ->placeholder($saved ? 'A key is saved. Leave empty to keep it.' : 'Paste your CurseForge API key'),
                Toggle::make('curseforge.user_keys')->label(__('pelican-simplify::settings.form.let_people_add_their_own_curseforge'))->helperText(__('pelican-simplify::settings.form.for_bigger_hosts_each_person_can')),
                Toggle::make('curseforge.remove_key')->label(__('pelican-simplify::settings.form.remove_the_saved_curseforge_key_when'))->helperText(__('pelican-simplify::settings.form.plugin_search_then_leaves_curseforge_out'))->visible($saved),
                \Filament\Schemas\Components\Actions::make([
                    \Filament\Actions\Action::make('testCurseForge')->button()->label(__('pelican-simplify::settings.form.test_key'))->icon('tabler-plug-connected')->color('gray')->action(fn () => $this->testCurseForge()),
                ]),
            ]),
        ];
    }
    /** The Website embed add-on: how the list looks, which servers it shows, and the code for another website. */
    private function websiteEmbedSections(): array
    {
        $on = fn (Get $get): bool => (bool) $get('features.website_embed');
        return [
            Section::make('Not switched on')->description(__('pelican-simplify::settings.form.switch_on_website_embed_under_general'))->visible(fn (Get $get) => !$on($get))->schema([]),
            Section::make('Look')->columns(2)->description(__('pelican-simplify::settings.form.websites_can_still_change_each_option'))->schema([
                Select::make('embed.style')->label(__('pelican-simplify::settings.form.style'))->options(['auto' => __('pelican-simplify::settings.form.follow_the_website_visitor'), 'light' => __('pelican-simplify::settings.form.light'), 'dark' => 'Dark'])->selectablePlaceholder(false)->required(),
                Select::make('embed.corners')->label(__('pelican-simplify::settings.form.corners'))->options(['round' => __('pelican-simplify::settings.form.round'), 'square' => 'Square'])->selectablePlaceholder(false)->required(),
                ColorPicker::make('embed.accent')->label(__('pelican-simplify::settings.form.accent_colour'))->regex('/^#[0-9a-fA-F]{6}$/')->helperText(__('pelican-simplify::settings.form.empty_uses_the_panel_accent_colour')),
                \Filament\Schemas\Components\Grid::make(1)->schema([
                    Toggle::make('embed.players')->label(__('pelican-simplify::settings.form.show_players')),
                    Toggle::make('embed.modpack')->label(__('pelican-simplify::settings.form.show_modpack_and_version_with_a')),
                    Toggle::make('embed.join')->label(__('pelican-simplify::settings.form.show_the_join_address_with_a'))->helperText(__('pelican-simplify::settings.form.only_public_addresses_never_a_home')),
                ]),
            ]),
            Section::make('Servers on your website')->description(__('pelican-simplify::settings.form.owners_can_also_switch_this_on'))->schema([
                \Filament\Forms\Components\CheckboxList::make('embed.servers')->hiddenLabel()->options(fn () => Server::query()->orderBy('name')->limit(500)->pluck('name', 'uuid')->all())
                    ->searchable()->bulkToggleable()->columns(2)->noSearchResultsMessage('No servers found.'),
            ]),
            Section::make('Code for your website')->columns(2)->description(__('pelican-simplify::settings.form.paste_one_of_these_into_any'))->schema([
                Select::make('embed_code.show')->label(__('pelican-simplify::settings.form.what_this_code_shows'))->live()->selectablePlaceholder(false)
                    ->options(fn () => ['all' => 'Every server on the website', 'server' => 'One server', 'game' => 'One game'] + ($this->embedGroups() !== [] ? ['group' => 'One of your server groups'] : [])),
                Select::make('embed_code.server')->label(__('pelican-simplify::settings.form.server'))->live()->options(fn (Get $get) => $this->embedChosen($get)->mapWithKeys(fn (Server $s) => [(string) $s->uuid_short => $s->name])->all())
                    ->placeholder(__('pelican-simplify::settings.form.pick_a_server'))->helperText(__('pelican-simplify::settings.form.the_page_link_below_is_a'))->visible(fn (Get $get) => $get('embed_code.show') === 'server'),
                Select::make('embed_code.game')->label(__('pelican-simplify::settings.form.game'))->live()->options(fn (Get $get) => $this->embedChosen($get)->map(fn (Server $s) => \Simplify\Panel\Services\WebsiteEmbed::game($s))->unique()->sort()->mapWithKeys(fn (string $g) => [$g => $g])->all())
                    ->placeholder(__('pelican-simplify::settings.form.pick_a_game'))->visible(fn (Get $get) => $get('embed_code.show') === 'game'),
                Select::make('embed_code.group')->label(__('pelican-simplify::settings.form.server_group'))->live()->options(fn () => array_column($this->embedGroups(), 'name', 'id'))
                    ->placeholder(__('pelican-simplify::settings.form.pick_a_group'))->helperText(__('pelican-simplify::settings.form.takes_the_servers_in_this_group'))->visible(fn (Get $get) => $get('embed_code.show') === 'group'),
                \Filament\Schemas\Components\View::make('pelican-simplify::filament.admin.website-embed')->columnSpanFull()->viewData(fn (Get $get) => ['show' => $this->embedShow($get) ?? [], 'waiting' => $this->embedShow($get) === null]),
            ]),
        ];
    }
    /** The servers ticked above (also before saving), so the code choices match the list. */
    private function embedChosen(Get $get): \Illuminate\Support\Collection
    {
        $chosen = array_map('strval', (array) $get('embed.servers'));
        return $chosen === [] ? collect() : Server::query()->with('egg')->whereIn('uuid', $chosen)->orderBy('name')->limit(500)->get();
    }
    /** This admin's own server groups from the dashboard. */
    private function embedGroups(): array
    {
        try { return \Simplify\Panel\Services\OverviewGroups::get(user())['groups'] ?? []; } catch (\Throwable $e) { report($e); return []; }
    }
    /** What the code is limited to; null while the choice is not complete, so no code shows that would list every server. */
    private function embedShow(Get $get): ?array
    {
        $show = match ($get('embed_code.show')) {
            'server' => ['server' => (string) $get('embed_code.server')],
            'game' => ['game' => (string) $get('embed_code.game')],
            'group' => ['servers' => $this->embedChosen($get)->whereIn('uuid', collect($this->embedGroups())->firstWhere('id', $get('embed_code.group'))['servers'] ?? [])->pluck('uuid_short')->map(fn ($id) => (string) $id)->values()->all()],
            default => [],
        };
        return $show === [] || array_filter($show) !== [] ? $show : null;
    }
    private function saveEmbed(array $embed): void
    {
        \Simplify\Panel\Services\WebsiteEmbed::save($embed);
        if (!\Simplify\Panel\Services\WebsiteListing::available() || !array_key_exists('servers', $embed)) return;
        $chosen = array_flip(array_map('strval', (array) $embed['servers']));
        foreach (Server::query()->orderBy('name')->limit(500)->get() as $server) {
            $visible = isset($chosen[$server->uuid]);
            if (\Simplify\Panel\Services\WebsiteListing::visible($server) !== $visible) \Simplify\Panel\Services\WebsiteListing::setVisible($server, $visible);
        }
    }
    /** Egg updates tab: the listed updates and the eggs Simplify updated before. */
    public function eggUpdates(): array
    {
        return ['available' => \Simplify\Panel\Services\EggUpdates::available(), 'updated' => \Simplify\Panel\Services\EggUpdates::updated(), 'checked' => (int) \Simplify\Panel\Services\EggUpdates::state()['checked_at']];
    }

    public function checkEggUpdates(): void
    {
        abort_unless(user()?->isRootAdmin(), 403);
        \Simplify\Panel\Services\EggUpdates::check();
        $count = count(\Simplify\Panel\Services\EggUpdates::available());
        Notification::make()->title($count === 0 ? 'All eggs are up to date' : ($count === 1 ? '1 egg update available' : $count.' egg updates available'))->success()->send();
    }

    /** One egg, or every listed one with $id null. */
    public function updateEggs(?int $id = null): void
    {
        abort_unless(user()?->isRootAdmin(), 403);
        $ids = $id === null ? array_column(\Simplify\Panel\Services\EggUpdates::available(), 'id') : [$id];
        $failed = [];
        $done = 0;
        foreach (Egg::query()->whereIn('id', $ids)->get() as $egg) {
            $result = \Simplify\Panel\Services\EggUpdates::update($egg);
            $result['ok'] ? $done++ : $failed[] = $result['message'];
        }
        if ($failed !== []) Notification::make()->title($done > 0 ? $done.' updated, '.count($failed).' failed' : 'Update failed')->body(implode(' ', $failed))->danger()->send();
        else Notification::make()->title($done === 1 ? 'Egg updated' : $done.' eggs updated')->body(__('pelican-simplify::settings.form.running_servers_keep_running_the_previous'))->success()->send();
    }

    /** Test the update on a test server and update only when it installs and starts. */
    public function testEgg(int $id): void
    {
        abort_unless(user()?->isRootAdmin(), 403);
        $result = \Simplify\Panel\Services\EggUpdateTest::start(Egg::query()->findOrFail($id));
        Notification::make()->title($result['message'])->body(__('pelican-simplify::settings.form.this_takes_a_few_minutes_you'))->success()->send();
    }

    public function restoreEgg(int $id): void
    {
        abort_unless(user()?->isRootAdmin(), 403);
        $result = \Simplify\Panel\Services\EggUpdates::restore(Egg::query()->findOrFail($id));
        Notification::make()->title($result['message'])->status($result['ok'] ? 'success' : 'danger')->send();
    }

    public function moveEggServers(int $id): void
    {
        abort_unless(user()?->isRootAdmin(), 403);
        $result = \Simplify\Panel\Services\EggUpdates::moveServers(Egg::query()->findOrFail($id));
        Notification::make()->title($result['message'])->status($result['ok'] ? 'success' : 'danger')->send();
    }

    public function testCurseForge(): void
    {
        abort_unless(static::canAccess(), 403);
        $result = \Simplify\Panel\Services\CurseForge::test((string) data_get($this->data, 'curseforge.key', ''));
        Notification::make()->title($result['ok'] ? 'CurseForge key works' : 'CurseForge key not working')->body($result['message'])->{$result['ok'] ? 'success' : 'danger'}()->send();
    }
    /** Tries the typed key, or the saved one, with the chosen model (Test key button). */
    public function testErrorExplainer(): void
    {
        abort_unless(static::canAccess(), 403);
        $result = \Simplify\Panel\Services\ErrorExplainer::test((string) data_get($this->data, 'explainer.key', ''), (string) data_get($this->data, 'explainer.model', ''), (string) data_get($this->data, 'explainer.provider', ''));
        Notification::make()->title($result['ok'] ? 'API key works' : 'API key not working')->body($result['message'])->{$result['ok'] ? 'success' : 'danger'}()->send();
    }
    /** Add-on pages that need a value before the add-on works; they are wizard steps only while the add-on is switched on. */
    private const ADDON_STEPS = [
        'Server Maker' => ['tab' => 'Easy Minecraft server creator', 'features' => ['easy_creator', 'game_creator']],
        'Automatic subdomains' => ['tab' => 'Automatic subdomains', 'features' => ['subdomains']],
    ];

    /** Labels of the wizard steps for the progress bar, in order; follows the add-on switches as they are flipped. */
    public function setupSteps(): array
    {
        $features = (array) ($this->data['features'] ?? []);
        $steps = ($this->data['experience'] ?? 'full') === 'addons' ? ['How much Simplify?', 'Add-ons'] : ['How much Simplify?', 'Features', 'People & access', 'Look'];
        foreach (self::ADDON_STEPS as $label => $addon) if (array_filter($addon['features'], fn (string $feature) => !empty($features[$feature]))) $steps[] = $label;
        if (\Simplify\Panel\Services\Extensions::webInstalled()) $steps[] = 'Website';
        $steps[] = 'Ready';
        return $steps;
    }

    /**
     * The first setup asks four things: what Simplify does, who may do what, how it looks, and a check before finishing.
     * Add-ons that need a value (hosting machines, a domain) get a step of their own only while they are on; everything
     * else keeps its default and has a tab in the settings afterwards.
     */
    private function setupWizardSteps(array $categories, array $addons): array
    {
        $step = fn (string $label, string $hint, array $content) => \Filament\Schemas\Components\Wizard\Step::make($label)->description($hint)->schema($content);
        $full = fn (Get $get) => self::full($get);
        $steps = [
            $step('How much Simplify?', 'Needed', [self::experienceSection()]),
            $step('Features', 'Needed', [
                self::featureSection('What Simplify replaces', 'Interface', 'The Simplify dashboard, console and server pages in place of Pelican\'s own. Each can be turned off again later.'),
                self::featureSection('Add-ons', 'Add-ons', 'Optional parts of Simplify. An add-on that needs a value before it works (hosting machines, a domain) adds a step to this setup.', true),
            ])->visible($full),
            $step('Add-ons', 'Needed', [
                self::featureSection('Add-ons', 'Add-ons', 'Pick the add-ons you want. They appear in Pelican\'s own menu, under Simplify. An add-on that needs a value before it works (hosting machines, a domain) adds a step to this setup.', true),
            ])->visible(fn (Get $get) => !self::full($get)),
            $step('People & access', 'Needed', $categories['Users & access'])->visible($full),
            $step('Look', 'Optional', $categories['Appearance'])->visible($full),
        ];
        foreach (self::ADDON_STEPS as $label => $addon) {
            if (!isset($addons[$addon['tab']])) continue;
            $steps[] = $step($label, 'Add-on', $addons[$addon['tab']])->visible(fn (Get $get) => (bool) array_filter($addon['features'], fn (string $feature) => (bool) $get('features.'.$feature)));
        }
        if (isset($categories['Website'])) $steps[] = $step('Website', 'Optional', $categories['Website']);
        $steps[] = $step('Ready', 'Finish', [
            Section::make('Ready to finish')->description(__('pelican-simplify::settings.form.check_what_you_chose_everything_not'))->schema([
                \Filament\Schemas\Components\Text::make(fn (Get $get) => new \Illuminate\Support\HtmlString(self::setupSummary((array) $get('features'), (array) ($get('roles.everyone.abilities') ?? []), !self::full($get))))->columnSpanFull(),
            ]),
        ]);
        return $steps;
    }

    /** Plain summary for the last wizard step: what is on, and what everyone may do. */
    public static function setupSummary(array $features, array $everyone, bool $addonsOnly = false): string
    {
        $definitions = \Simplify\Panel\Services\FeatureSettings::definitions();
        $names = fn (string $group) => array_values(array_map(fn ($item) => $item[0], array_filter($definitions, fn ($item, $key) => ($item[3] ?? '') === $group && !empty($features[$key]), ARRAY_FILTER_USE_BOTH)));
        $abilities = \Simplify\Panel\Services\RoleAccess::ABILITIES;
        $can = array_values(array_map(fn ($key) => $abilities[$key][0] ?? $key, array_filter($everyone, fn ($key) => isset($abilities[$key]))));
        $rows = [
            ['Simplify replaces', $addonsOnly ? ['Nothing: Pelican\'s own pages and look stay (only the add-ons)'] : ($names('Interface') ?: ['Nothing yet: Pelican\'s own pages stay'])],
            ['Add-ons on', $names('Add-ons') ?: ['None']],
            ...($addonsOnly ? [] : [['Everyone may', $can ?: ['Only what Pelican\'s own permissions allow']]]),
        ];
        return '<dl class="ps-setup-summary">'.implode('', array_map(fn ($row) => '<dt>'.e($row[0]).'</dt><dd>'.e(implode(', ', $row[1])).'</dd>', $rows)).'</dl>';
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);
        // Dependency checks are slow (they run node/yarn); only the first setup requires them. Later checks run on demand.
        if ($this->needsSetup()) $this->refreshRequirements();
        if ($this->needsSetup() && !$this->requirementsReady()) {
            $this->setupStarted = false;
            $this->showDiagnostics = true;
            Notification::make()->title(__('pelican-simplify::settings.form.setup_blocked'))->body(__('pelican-simplify::settings.form.fix_the_failed_dependency_checks_and'))->danger()->send();
            return;
        }
        $firstSetup = $this->needsSetup();
        if ($firstSetup && !$this->setupStarted) return;
        $state=$this->form->getState();
        if (isset($state['appearance'])) AppearanceSettings::save($state['appearance']);
        if (array_key_exists('external_shown', $state) && class_exists(\Simplify\Panel\Services\ExternalPlugins::class)) \Simplify\Panel\Services\ExternalPlugins::saveShown((array) $state['external_shown']);
        // Creator file: only the self-service location and games are edited here; who may create lives in RoleAccess.
        // The setup wizard only asks part of the settings: anything not in its state keeps the saved value.
        $data = CreatorAccess::configuration();
        foreach (['node_ids' => Node::class, 'other_egg_ids' => Egg::class] as $key => $model) {
            if (array_key_exists($key, $state)) $data[$key] = $model::query()->whereIn('id', $state[$key] ?? [])->pluck('id')->map(fn ($id) => (int) $id)->all();
        }
        if (array_key_exists('minecraft_enabled', $state)) $data['minecraft_enabled'] = (bool) $state['minecraft_enabled'];
        if (!empty($state['clear_legacy_user_grants'])) { $data['user_ids'] = []; $data['user_limits'] = []; }
        // A wizard that skipped People & access (only the add-ons) keeps the saved roles.
        $roles = $state['roles'] ?? \Simplify\Panel\Services\RoleAccess::all();
        $existingRoles = Role::query()->pluck('id')->map(fn ($id) => (int) $id)->all();
        // With the People & roles page in charge, role profiles live there; this form only edits Everyone.
        if (\Simplify\Panel\Services\FeatureSettings::enabled('people_manager')) $roles['roles'] = \Simplify\Panel\Services\RoleAccess::all()['roles'];
        $roles['roles'] = array_values(array_filter($roles['roles'] ?? [], fn ($role) => in_array((int) ($role['role_id'] ?? 0), $existingRoles, true)));
        $creates = in_array('create', $roles['everyone']['abilities'] ?? [], true) || array_filter($roles['roles'], fn ($role) => in_array('create', $role['abilities'] ?? [], true));
        if ($creates && (!$data['node_ids'] || (!$data['minecraft_enabled'] && !$data['other_egg_ids']))) {
            throw \Illuminate\Validation\ValidationException::withMessages(['data.node_ids' => 'Users may create servers: choose a hosting machine and at least one game under Easy Minecraft server creator.']);
        }
        if (isset($state['creation_defaults'])) \Simplify\Panel\Services\CreationDefaults::save($state['creation_defaults']);
        if (isset($state['performance'])) \Simplify\Panel\Services\PerformanceSettings::save($state['performance']);
        if (!empty($state['egg_updates']) && class_exists(\Simplify\Panel\Services\EggUpdates::class)) \Simplify\Panel\Services\EggUpdates::save($state['egg_updates']);
        if (!empty($state['embed']) && class_exists(\Simplify\Panel\Services\WebsiteEmbed::class)) $embedState = $state['embed'];
        if (isset($state['subdomains']) && class_exists(\Simplify\Panel\Services\Subdomains::class)) \Simplify\Panel\Services\Subdomains::save($state['subdomains']);
        if (!empty($state['explainer']) && class_exists(\Simplify\Panel\Services\ErrorExplainer::class)) {
            \Simplify\Panel\Services\ErrorExplainer::save($state['explainer'], !empty($state['explainer']['remove_key']));
            data_set($this->data, 'explainer.key', '');
            data_set($this->data, 'explainer.remove_key', false);
        }
        if (!empty($state['curseforge']) && class_exists(\Simplify\Panel\Services\CurseForge::class)) {
            \Simplify\Panel\Services\CurseForge::save((string) ($state['curseforge']['key'] ?? ''), !empty($state['curseforge']['remove_key']), !empty($state['curseforge']['user_keys']));
            data_set($this->data, 'curseforge.key', '');
            data_set($this->data, 'curseforge.remove_key', false);
        }
        \Simplify\Panel\Services\RoleAccess::save($roles);
        if (!Storage::disk('local')->put(CreatorAccess::FILE, json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT))) {
            throw new \RuntimeException('Could not save creator settings.');
        }
        if (isset($state['web']) && \Simplify\Panel\Services\Extensions::webInstalled()) app('simplify.web')->saveOptions($state['web']);
        app(CreatorAccess::class)->reset();
        $features = $state['features'] ?? [];
        if (isset($state['experience'], self::EXPERIENCES[$state['experience']])) $features['addons_only'] = $state['experience'] === 'addons';
        if (isset($state['update_channel'], self::UPDATE_CHANNELS[$state['update_channel']])) {
            $features['simplify_beta'] = $state['update_channel'] !== 'stable';
            $features['simplify_snapshot'] = $state['update_channel'] === 'snapshot';
        }
        \Simplify\Panel\Services\FeatureSettings::save($features,true);
        // After the switches, so a just-enabled add-on can store which servers are shown.
        if (isset($embedState)) $this->saveEmbed($embedState);
        $this->rerunSetup = false;
        Notification::make()->title(__('pelican-simplify::settings.form.pelicansimplify_settings_saved'))->success()->send();
        if ($firstSetup) {
            $this->redirect(\Simplify\Panel\Services\FeatureSettings::enabled('dashboard') ? \Simplify\Panel\Filament\App\Pages\Dashboard::getUrl(panel: 'app') : \Simplify\Panel\Services\LegacyNavigation::nativeUrl());
        } else {
            $this->dispatch('simplify-settings-saved');
        }
    }
}

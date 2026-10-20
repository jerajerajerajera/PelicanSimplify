<?php

declare(strict_types=1);

namespace Simplify\Panel\Filament\Admin\Pages;

use App\Models\Egg;
use App\Models\Role;
use Filament\Forms\Components\Repeater;
use App\Models\Node;
use App\Models\User;
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
        Notification::make()->success()->title('Banner uploaded')->send();
    }
    public function uploadLogo(): void
    {
        abort_unless(static::canAccess(),403);
        $this->validate(['logoUpload'=>'required|file|max:'.\Simplify\Panel\Services\BrandSettings::LOGO_MAX_KB]);
        $name=\Simplify\Panel\Services\BrandSettings::storeLogo($this->logoUpload);
        AppearanceSettings::setLogo($name);
        $this->logoUpload=null;
        data_set($this->data,'appearance.brand.mode','logo');
        Notification::make()->success()->title('Logo uploaded')->body('It shows in the top-left corner right away.')->send();
    }
    public function removeLogo(): void
    {
        abort_unless(static::canAccess(),403);
        AppearanceSettings::setLogo(null);
        data_set($this->data,'appearance.brand.mode','text');
        Notification::make()->success()->title('Logo removed')->send();
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
        Notification::make()->success()->title('Banner removed')->send();
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
    public function updateStatus(): ?array { return \Simplify\Panel\Services\UpdateCheck::status(); }
    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->embedded = request()->boolean('simplify_embed');
        $this->form->fill([...CreatorAccess::configuration(),'creation_defaults'=>\Simplify\Panel\Services\CreationDefaults::all(),'appearance'=>AppearanceSettings::all(),'features'=>\Simplify\Panel\Services\FeatureSettings::all(),'performance'=>\Simplify\Panel\Services\PerformanceSettings::all(),'roles'=>\Simplify\Panel\Services\RoleAccess::all(),'web'=>\Simplify\Panel\Services\Extensions::webInstalled()?app('simplify.web')->options():[],'external_shown'=>self::externalShown(),'subdomains'=>class_exists(\Simplify\Panel\Services\Subdomains::class)?\Simplify\Panel\Services\Subdomains::settings():[]]);
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
    private static function featureSection(string $title, string $group, ?string $description = null): Section
    {
        $definitions = array_filter(\Simplify\Panel\Services\FeatureSettings::definitions(), fn ($item) => ($item[3] ?? 'Server features') === $group);
        $section = Section::make($title)->extraAttributes(['class' => 'ps-setting-list'])->schema(array_map(fn (array $definition, string $key) => Toggle::make('features.'.$key)->label($definition[0])->helperText($definition[1]), $definitions, array_keys($definitions)));
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
            \Filament\Forms\Components\CheckboxList::make($name('abilities'))->label('Can')->columns(2)->bulkToggleable()
                ->options(array_map(fn (array $a): string => $a[0], $abilities))
                ->descriptions(array_map(fn (array $a): string => $a[1], $abilities)),
            \Filament\Schemas\Components\Grid::make(5)->schema($limitFields),
        ];
    }

    private static function featureToggle(string $key): Toggle
    {
        $definition = \Simplify\Panel\Services\FeatureSettings::definitions()[$key] ?? [$key, null];
        return Toggle::make('features.'.$key)->label($definition[0])->helperText($definition[1]);
    }

    public function form(Schema $schema): Schema
    {
        $performance = \Simplify\Panel\Services\PerformanceSettings::FIELDS;
        $numberField = static fn (string $key) => TextInput::make('performance.'.$key)->label($performance[$key][0])->helperText($performance[$key][1])
            ->integer()->minValue($performance[$key][3])->maxValue($performance[$key][4])->required();

        $categories = [
            'General' => [
                self::featureSection('Simplify interface', 'Interface', 'What Simplify replaces in Pelican.'),
                self::featureSection('Server workspaces', 'Server features', 'Workspaces shown on every server page. Pelican permissions still decide who may use them.'),
                self::featureSection('Add-ons', 'Add-ons', 'Optional parts of Simplify. Each add-on has its own settings tab at the bottom of the menu.'),
                Section::make('Pages from other plugins')->description('Pages that other installed plugins add to the admin panel or to servers. Ticked pages show up in Simplify under "External" (Admin tools, host mode and the server page); they always stay available in Pelican\'s own panel.')->schema([
                    \Filament\Forms\Components\CheckboxList::make('external_shown')->hiddenLabel()->options(fn () => self::externalCatalog())->bulkToggleable()->columns(1)
                        ->visible(fn () => self::externalCatalog() !== []),
                    \Filament\Schemas\Components\Text::make('No other plugins add pages right now.')->visible(fn () => self::externalCatalog() === []),
                ]),
            ],
            'Appearance' => [
                self::featureSection('Theme', 'Appearance'),
                Section::make('Colours')->columns(2)->schema([
                    ColorPicker::make('appearance.accent_color')->view('pelican-simplify::filament.admin.accent-picker')->label('Panel accent color')->hex()->required()->rules(['regex:/^#[0-9a-fA-F]{6}$/'])->helperText('Used throughout the shared interface and by every server when separate colors are off.')->columnSpanFull(),
                    Toggle::make('appearance.individual_colors')->label('Use separate server colors')->helperText('On assigns a color to every server without one. Off makes all servers use the panel accent; saved colors are retained.')->columnSpanFull(),
                ]),
                Section::make('Panel name & logo')->description('How your panel name looks in the top-left corner. The default is your name with the accent color fading in toward the right.')->columns(2)->schema([
                    \Filament\Schemas\Components\View::make('pelican-simplify::filament.admin.brand-editor')->columnSpanFull(),
                    Select::make('appearance.brand.mode')->label('Show')->options(['text'=>'Styled text','logo'=>'Uploaded logo'])->selectablePlaceholder(false)->required()->live()
                        ->helperText(fn()=>AppearanceSettings::all()['brand']['logo']?null:'Upload a logo above to use one.')
                        ->disableOptionWhen(fn(string $value)=>$value==='logo'&&!AppearanceSettings::all()['brand']['logo']),
                    TextInput::make('appearance.brand.text')->label('Text')->maxLength(40)->placeholder(fn()=>(string)config('app.name','Pelican'))->helperText('Empty uses the panel name.'),
                    Select::make('appearance.brand.font')->label('Font')->options(array_map(fn($font)=>$font[0],\Simplify\Panel\Services\BrandSettings::FONTS))->selectablePlaceholder(false)->required(),
                    Select::make('appearance.brand.weight')->label('Weight')->options([400=>'Regular',500=>'Medium',600=>'Semibold',700=>'Bold',800=>'Extra bold',900=>'Black'])->selectablePlaceholder(false)->required(),
                    Select::make('appearance.brand.color_style')->label('Color')->options(['gradient'=>'Fade between two colors','solid'=>'One color'])->selectablePlaceholder(false)->required()->live(),
                    TextInput::make('appearance.brand.spacing')->label('Letter spacing')->integer()->minValue(-5)->maxValue(30)->suffix('/100 em')->required(),
                    ColorPicker::make('appearance.brand.color1')->label(fn(Get $get)=>$get('appearance.brand.color_style')==='solid'?'Text color':'Start color')->hex()->rules(['nullable','regex:/^#[0-9a-fA-F]{6}$/'])->helperText('Empty follows the light/dark theme.'),
                    ColorPicker::make('appearance.brand.color2')->label('End color')->hex()->rules(['nullable','regex:/^#[0-9a-fA-F]{6}$/'])->helperText('Empty uses the panel accent.')->visible(fn(Get $get)=>$get('appearance.brand.color_style')!=='solid'),
                    Toggle::make('appearance.brand.uppercase')->label('Capital letters'),
                    Toggle::make('appearance.brand.suffix')->label('Show "× Pelican" after the name'),
                    TextInput::make('appearance.brand.logo_height')->label('Logo height')->integer()->minValue(16)->maxValue(64)->suffix('px')->required(),
                    Toggle::make('appearance.brand.logo_text')->label('Show the text next to the logo'),
                ]),
            ],
            'Users & access' => [
                self::featureSection('Visibility', 'Access and visibility', 'Who sees which servers, and who may share them.'),
                Section::make('Everyone')->description('Applies to every signed-in user. Administrators can always do everything. Pelican\'s own server permissions (owner, subusers) still decide which servers someone can manage.')
                    ->schema(self::abilityFields('roles.everyone')),
                Section::make('Roles')->description('Roles and their permissions are managed on the People & roles page (Admin tools → People), because "Let Simplify manage people & roles" is on.')
                    ->visible(fn () => \Simplify\Panel\Services\FeatureSettings::enabled('people_manager')),
                Section::make('Roles')->description('Members of a Pelican role get these abilities on top of Everyone, and the role\'s limits replace Everyone\'s. With several roles, abilities combine and the highest limit wins.')->visible(fn () => !\Simplify\Panel\Services\FeatureSettings::enabled('people_manager'))->schema([
                    Repeater::make('roles.roles')->hiddenLabel()->defaultItems(0)->collapsible()->addActionLabel('Add role')
                        ->itemLabel(fn (array $state): ?string => isset($state['role_id']) ? (Role::query()->whereKey($state['role_id'])->value('name') ?? 'Role') : 'New role')
                        ->schema([
                            Select::make('role_id')->label('Pelican role')->required()->distinct()->searchable()->options(fn () => Role::query()->orderBy('name')->pluck('name', 'id')->all()),
                            ...self::abilityFields(null),
                        ]),
                ]),
                ...(CreatorAccess::legacyUserGrants() > 0 ? [
                    Section::make('Individual user grants from an earlier version')->description(CreatorAccess::legacyUserGrants().' user(s) still have a personal creator grant or limit. They keep working; move these users into a role and remove the old grants below.')->schema([
                        Toggle::make('clear_legacy_user_grants')->label('Remove the old individual grants when saving'),
                    ]),
                ] : []),
            ],
            'Backups & performance' => [
                Section::make('Automatic backups')->columns(2)->description('Defaults for new servers. Existing servers keep their own policy; change it from the server\'s Backups workspace.')->schema([
                    self::featureToggle('automatic_backups'),
                    Toggle::make('performance.backup_only_when_used')->label('Skip backups while a server has not run')->helperText('An offline server that has not started since its last backup is not backed up again.'),
                    ...array_map($numberField, ['backup_interval_minutes', 'backup_retention']),
                ]),
                Section::make('Refresh rates')->columns(2)->description('Lower values feel livelier but cost more requests to the panel and Wings. Hidden browser tabs and open popups never refresh.')
                    ->schema(array_map($numberField, ['overview_seconds', 'server_seconds', 'restart_seconds', 'health_seconds'])),
            ],
            'Easy Minecraft server creator' => [
                Section::make('Self-service creation')->columns(2)->description('Where users may create Minecraft servers (also used by the game creator). Who may create servers is set per role under Users & access ("Create servers").')->schema([
                    Select::make('node_ids')->label('Hosting machines')->multiple()->searchable()->options(fn () => Node::query()->pluck('name', 'id')->all())->helperText('Nodes used for self-service creation. Users do not choose ports or nodes.'),
                    Toggle::make('minecraft_enabled')->label('Allow Minecraft'),
                ]),
                Section::make('Starting resources')->description('CPU, RAM and disk for each Minecraft type. Applies to new servers within the user\'s limits; existing servers keep theirs.')
                    ->schema(array_map(fn (string $type, string $label) => Section::make($label)->columns(3)->schema([
                        TextInput::make('creation_defaults.'.$type.'.cpu')->label('CPU (%)')->integer()->minValue(1)->maxValue(1600)->required()->helperText('100% is one core; 200% is two cores.'),
                        TextInput::make('creation_defaults.'.$type.'.ram_gb')->label('RAM (GB)')->integer()->minValue(1)->maxValue(128)->required(),
                        TextInput::make('creation_defaults.'.$type.'.disk_mb')->label('Disk (MB)')->integer()->minValue(1024)->maxValue(10485760)->required(),
                    ]), ['vanilla', 'plus', 'modded'], ['Vanilla', 'Vanilla+', 'Modded / modpacks'])),
            ],
            'Easy server creator' => [
                Section::make('Games for self-service')->description('Games users may create with the game creator. Administrators can pick any game, including the 170+ games from the official Pelican egg index.')->schema([
                    Select::make('other_egg_ids')->label('Approved games')->multiple()->searchable()->options(fn () => Egg::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->helperText('A game installed from the egg index through the creator is added here automatically.'),
                ]),
                Section::make('Artwork')->extraAttributes(['class' => 'ps-setting-list'])->schema([self::featureToggle('steam_art')]),
                Section::make('Installing new games')->description('Administrators can always install a game that is not on the panel yet: its egg is downloaded from the official Pelican egg repositories, checked and imported when they continue in the creator. To let other people do this, give their role the "Install new games" ability under Users & access. Root admins get a notification for every game installed this way.'),
            ],
            'People & roles' => [
                Section::make('How it works')->description('With "Let Simplify manage people & roles" on (General → Add-ons), Admin tools and host mode open one People & roles page instead of Pelican\'s Users and Roles pages.')->schema([
                    \Filament\Schemas\Components\Text::make('People: invite someone by username and email; they get an email to choose their own password (nobody else ever sees it). Assign roles, send a password reset link, or remove people who own no servers.'),
                    \Filament\Schemas\Components\Text::make('Roles: each role combines Simplify permissions and limits (what members may do on their servers) with optional admin panel access. They are Pelican roles, so Pelican and other plugins keep working with them.'),
                    \Filament\Schemas\Components\Text::make('Safety: only root admins manage people and roles, the Root Admin role is locked, you cannot remove your own root access, and the last root admin can never be removed or deleted.'),
                ]),
            ],
            'Plugin manager' => [
                Section::make('Plugin updates')->description('Applies to every installed plugin with a published update, Simplify included. Updates can also be started by hand from Admin tools → Plugins. Needs the plugin manager add-on.')->schema([self::featureToggle('auto_update_plugins'), self::featureToggle('simplify_beta')]),
            ],
            'Automatic subdomains' => [
                Section::make('Addresses')->columns(2)->description('A server named "oak" gets oak.<domain>: a CNAME to the game address and, for Minecraft, an SRV record with its port, so Java players can leave the port out. Both are DNS only (not proxied), because Cloudflare\'s proxy does not carry game traffic.')->schema([
                    TextInput::make('subdomains.domain')->label('Domain')->dehydrateStateUsing(fn ($state) => strtolower(trim((string) $state)))->rules(['nullable','regex:/^(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\\.)+[a-z]{2,}$/'])->validationMessages(['regex' => 'Enter a host name such as example.com.'])->placeholder('example.com')->maxLength(200)->helperText('The Cloudflare zone the subdomains are created in.'),
                    TextInput::make('subdomains.target')->label('Game address')->dehydrateStateUsing(fn ($state) => strtolower(trim((string) $state)))->rules(['nullable','regex:/^(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\\.)+[a-z]{2,}$/'])->validationMessages(['regex' => 'Enter a host name such as example.com.'])->placeholder('play.example.com')->maxLength(253)->helperText('An existing DNS-only record that points to the public IP where your game ports arrive.'),
                    TextInput::make('subdomains.port_min')->label('First forwarded port')->integer()->minValue(1)->maxValue(65535)->required(),
                    TextInput::make('subdomains.port_max')->label('Last forwarded port')->integer()->minValue(1)->maxValue(65535)->required()->gte('subdomains.port_min')->helperText('Only servers with a port in this range get a subdomain. New servers get a port from this range when one is free.'),
                ]),
                Section::make('Cloudflare token')->description('Create a Cloudflare API token with only Zone → DNS → Edit for this zone. Add it and the zone ID (on the zone\'s overview page) to the panel\'s .env as CLOUDFLARE_API_TOKEN=… and CLOUDFLARE_ZONE_ID=…. Simplify reads them from there and never stores or shows the token.')->schema([
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
            $categories = [...$categories, ...$addons];
            return $schema->statePath('data')->components([
                \Filament\Schemas\Components\Wizard::make(
                    array_map(fn (string $label, array $content) => \Filament\Schemas\Components\Wizard\Step::make($label)->schema($content), array_keys($categories), array_values($categories))
                )->skippable(false)->columnSpanFull()
                    ->nextAction(fn (\Filament\Actions\Action $action) => $action->label('Next')->color('gray')->extraAttributes(['class' => 'ssc-next-button']))
                    ->previousAction(fn (\Filament\Actions\Action $action) => $action->label('Back')->color('gray')->extraAttributes(['class' => 'ssc-back-button']))
                    ->submitAction(new \Illuminate\Support\HtmlString(view('pelican-simplify::setup-submit')->render())),
            ]);
        }
        $categories['Server banners'] = [\Filament\Schemas\Components\View::make('pelican-simplify::banner-library')];
        $icons['Server banners'] = 'tabler-photo';
        $categories = [...$categories, ...$addons];
        $tabs = \Filament\Schemas\Components\Tabs::make('Simplify settings')->columnSpanFull()->extraAttributes(['class' => 'ps-settings-tabs'])->tabs(
            array_map(fn (string $label, array $content) => \Filament\Schemas\Components\Tabs\Tab::make($label)->icon($icons[$label] ?? null)->badge(isset(self::ADDONS[$label]) ? 'Add-on' : null)->schema($content), array_keys($categories), array_values($categories))
        );
        if (method_exists($tabs, 'vertical')) $tabs->vertical();
        if (method_exists($tabs, 'persistTabInQueryString')) $tabs->persistTabInQueryString('section');
        return $schema->statePath('data')->components([$tabs]);
    }
    public function setupSteps(): array
    {
        $steps = array_keys(self::CATEGORIES);
        if (\Simplify\Panel\Services\Extensions::webInstalled()) $steps[] = 'Website';
        return [...$steps, ...array_keys(self::ADDONS)];
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);
        // Dependency checks are slow (they run node/yarn); only the first setup requires them. Later checks run on demand.
        if ($this->needsSetup()) $this->refreshRequirements();
        if ($this->needsSetup() && !$this->requirementsReady()) {
            $this->setupStarted = false;
            $this->showDiagnostics = true;
            Notification::make()->title('Setup blocked')->body('Fix the failed dependency checks and check again.')->danger()->send();
            return;
        }
        $firstSetup = $this->needsSetup();
        if ($firstSetup && !$this->setupStarted) return;
        $state=$this->form->getState();
        AppearanceSettings::save($state['appearance']??[]);
        if (array_key_exists('external_shown', $state) && class_exists(\Simplify\Panel\Services\ExternalPlugins::class)) \Simplify\Panel\Services\ExternalPlugins::saveShown((array) $state['external_shown']);
        // Creator file: only the self-service location and games are edited here; who may create lives in RoleAccess.
        $data = CreatorAccess::configuration();
        foreach (['node_ids' => Node::class, 'other_egg_ids' => Egg::class] as $key => $model) {
            $data[$key] = $model::query()->whereIn('id', $state[$key] ?? [])->pluck('id')->map(fn ($id) => (int) $id)->all();
        }
        $data['minecraft_enabled'] = (bool) ($state['minecraft_enabled'] ?? false);
        if (!empty($state['clear_legacy_user_grants'])) { $data['user_ids'] = []; $data['user_limits'] = []; }
        $roles = $state['roles'] ?? [];
        $existingRoles = Role::query()->pluck('id')->map(fn ($id) => (int) $id)->all();
        // With the People & roles page in charge, role profiles live there; this form only edits Everyone.
        if (\Simplify\Panel\Services\FeatureSettings::enabled('people_manager')) $roles['roles'] = \Simplify\Panel\Services\RoleAccess::all()['roles'];
        $roles['roles'] = array_values(array_filter($roles['roles'] ?? [], fn ($role) => in_array((int) ($role['role_id'] ?? 0), $existingRoles, true)));
        $creates = in_array('create', $roles['everyone']['abilities'] ?? [], true) || array_filter($roles['roles'], fn ($role) => in_array('create', $role['abilities'] ?? [], true));
        if ($creates && (!$data['node_ids'] || (!$data['minecraft_enabled'] && !$data['other_egg_ids']))) {
            throw \Illuminate\Validation\ValidationException::withMessages(['data.node_ids' => 'Users may create servers: choose a hosting machine and at least one game under Easy Minecraft server creator.']);
        }
        \Simplify\Panel\Services\CreationDefaults::save($state['creation_defaults']??[]);
        \Simplify\Panel\Services\PerformanceSettings::save($state['performance']??[]);
        if (isset($state['subdomains']) && class_exists(\Simplify\Panel\Services\Subdomains::class)) \Simplify\Panel\Services\Subdomains::save($state['subdomains']);
        \Simplify\Panel\Services\RoleAccess::save($roles);
        if (!Storage::disk('local')->put(CreatorAccess::FILE, json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT))) {
            throw new \RuntimeException('Could not save creator settings.');
        }
        if(\Simplify\Panel\Services\Extensions::webInstalled())app('simplify.web')->saveOptions($state['web']??[]);
        app(CreatorAccess::class)->reset();
        \Simplify\Panel\Services\FeatureSettings::save($state['features']??[],true);
        $this->rerunSetup = false;
        Notification::make()->title('PelicanSimplify settings saved')->success()->send();
        if ($firstSetup) {
            $this->redirect(\Simplify\Panel\Filament\App\Pages\Dashboard::getUrl(panel: 'app'));
        } else {
            $this->dispatch('simplify-settings-saved');
        }
    }
}

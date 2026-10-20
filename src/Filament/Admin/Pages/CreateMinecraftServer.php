<?php

declare(strict_types=1);

namespace Simplify\Panel\Filament\Admin\Pages;

use App\Enums\TablerIcon;
use App\Filament\Admin\Resources\Servers\ServerResource;
use App\Models\Allocation;
use App\Models\Egg;
use App\Models\Node;
use App\Services\Servers\ServerCreationService;
use App\Services\Servers\RandomWordService;
use BackedEnum;
use Simplify\Panel\Services\EggInstaller;
use Simplify\Panel\Services\MinecraftCatalog;
use Simplify\Panel\Services\JavaRuntime;
use Simplify\Panel\Services\ModpackCatalog;
use Simplify\Panel\Services\ServerProperties;
use Simplify\Panel\Jobs\ApplyServerProperties;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Actions\Action;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Storage;
use Throwable;
use Livewire\Attributes\Renderless;
use Simplify\Panel\Services\CreatorAccess;
use Simplify\Panel\Services\CreationGuard;
use Simplify\Panel\Services\OtherGameCatalog;
use Filament\Facades\Filament;
use App\Filament\Server\Pages\Console;

/**
 * Shared Minecraft / game wizard used by the admin and authenticated client panels.
 */
class CreateMinecraftServer extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = TablerIcon::Server2;
    protected static ?string $navigationLabel = 'Create server';
    protected static ?string $title = 'Create server';
    protected static ?string $slug = 'simple-server-creator';
    protected static bool $shouldRegisterNavigation = false;
    protected string $view = 'pelican-simplify::filament.admin.create-server';

    /** @var array<string,mixed> */
    public ?array $data = [];

    public bool $editingAdvanced = false;
    #[\Livewire\Attributes\Locked]
    public float $draftSession = 0;
    #[\Livewire\Attributes\Locked]
    public ?string $draftIcon = null;
    #[\Livewire\Attributes\Locked]
    public bool $draftCompleted = false;
    #[\Livewire\Attributes\Locked]
    public bool $draftRestored = false;
    public int $draftStep = 1;
    public int $furthestStep = 1;
    #[\Livewire\Attributes\Locked]
    public string $creationGame = '';

    private function draftPath(): string
    {
        return 'ssc/creator-drafts/' . (int) user()->id . (in_array($this->creationGame,['minecraft','other'],true)?'-'.$this->creationGame:'') . '.enc';
    }

    #[Renderless]
    public function saveDraft(int $step = 1, int $furthestStep = 1): bool
    {
        abort_unless(static::canAccess(), 403);
        if ($this->draftCompleted) return true;
        return \Illuminate\Support\Facades\Cache::lock('ssc-draft-' . user()->id, 10)->block(5, function () use ($step,$furthestStep): bool {
            $disk = Storage::disk('local');
            if ($disk->exists($this->draftPath())) {
                $existing = json_decode(\Illuminate\Support\Facades\Crypt::decryptString($disk->get($this->draftPath())), true, 64, JSON_THROW_ON_ERROR);
                if (($existing['completed_at'] ?? 0) >= $this->draftSession) return true;
            }
            if ($this->creationGame!=='') $this->data['game']=$this->creationGame;
            $state = $this->data ?? [];
            if (($state['game'] ?? 'minecraft') !== 'minecraft') $this->draftIcon = null;
            $uploads = $state['server_icon'] ?? null;
            if (is_array($uploads)) $uploads = reset($uploads);
            if ($uploads instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
                $content = file_get_contents($uploads->getRealPath());
                $size = $content === false ? false : @getimagesizefromstring($content);
                if ($size && $size[0] === 64 && $size[1] === 64 && $size[2] === IMAGETYPE_PNG && strlen($content) <= 102400) $this->draftIcon = base64_encode($content);
            }
            unset($state['server_icon']);
            $clean = function ($value) use (&$clean) {
                if (is_array($value)) return array_map($clean, $value);
                return is_scalar($value) || $value === null ? $value : null;
            };
            $this->draftStep = max(1,min(count($this->wizardStepLabels()),$step));
            $this->furthestStep=max($this->furthestStep,$this->draftStep,min(count($this->wizardStepLabels()),$furthestStep));
            $json = json_encode(['state' => $clean($state), 'step' => $this->draftStep, 'advanced' => $this->editingAdvanced, 'icon' => $this->draftIcon, 'wizard_version'=>4,'wizard_steps'=>$this->wizardStepLabels(),'furthest_step'=>$this->furthestStep], JSON_THROW_ON_ERROR);
            if (strlen($json) > 1048576) throw new \RuntimeException('Draft is too large to save.');
            if (!$disk->put($this->draftPath(), \Illuminate\Support\Facades\Crypt::encryptString($json))) throw new \RuntimeException('Could not save draft.');
            return true;
        });
    }

    public function removeDraftIcon(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->draftIcon = null;
        $this->saveDraft($this->draftStep);
    }

    public function discardDraft(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->finishDraft();
        $parameters=$this->creationGame!==''?['game'=>$this->creationGame,'simplify_embed'=>1]:[];
        $this->redirect(static::getUrl($parameters,panel: Filament::getCurrentPanel()->getId()));
    }

    private function finishDraft(): void
    {
        $this->draftCompleted = true;
        \Illuminate\Support\Facades\Cache::lock('ssc-draft-' . user()->id, 10)->block(5, function (): void {
            if (!Storage::disk('local')->put($this->draftPath(), \Illuminate\Support\Facades\Crypt::encryptString(json_encode(['completed_at' => microtime(true)], JSON_THROW_ON_ERROR)))) throw new \RuntimeException('Could not clear the saved draft.');
        });
        $this->dispatch('ssc-draft-completed');
    }


    public function toggleAdvanced(): void
    {
        $this->editingAdvanced = !$this->editingAdvanced;
    }

    public function prepareEgg(bool $select = true): void
    {
        if (!static::canAccess()) {
            abort(403);
        }
        CreatorAccess::assertState($this->data ?? []);
        $egg = ($this->data['game'] ?? 'minecraft') === 'other'
            ? OtherGameCatalog::selected($this->data['game_egg_id'] ?? null)
            : EggInstaller::ensure($this->data ?? []);
        if (!$egg) {
            throw ValidationException::withMessages(['data.game_egg_id' => 'Select an available game.']);
        }
        if ($select || empty($this->data['egg_id'])) {
            $this->data['egg_id'] = $egg->id;
        }
    }

    public static function canAccess(): bool
    {
        return (\Simplify\Panel\Services\FeatureSettings::enabled('easy_creator') || \Simplify\Panel\Services\FeatureSettings::enabled('game_creator')) && CreatorAccess::canCreate();
    }

    #[\Livewire\Attributes\Locked] public string $nameExample='';

    /** Per-request memo for lookups that form closures evaluate several times per render. */
    private array $memo = [];
    private function memo(string $key, \Closure $resolve): mixed
    {
        return array_key_exists($key, $this->memo) ? $this->memo[$key] : ($this->memo[$key] = $resolve());
    }
    private function multipleNodes(): bool
    {
        return $this->memo('multiple-nodes', fn (): bool => CreatorAccess::admin() && CreatorAccess::nodes()->count() > 1);
    }
    /** Filament 4 can toggle visibility in the browser; older versions fall back to a server round trip. */
    private static function clientVisibility(): bool
    {
        return method_exists(\Filament\Forms\Components\Field::class, 'visibleJs');
    }
    /**
     * Shows a field while the condition holds. With browser-side toggling the server still sees the field
     * as visible, so a hidden field is also kept out of the saved state and out of validation; otherwise an
     * empty hidden field (e.g. custom RAM) blocks Next with an error nobody can see.
     */
    private static function showWhen(mixed $component, string $js, \Closure $server, bool $keepWhenHidden = false): mixed
    {
        if (!self::clientVisibility()) return $component->visible($server);
        $component->visibleJs($js);
        if (!$keepWhenHidden && method_exists($component, 'validatedWhenNotDehydrated')) $component->dehydrated($server)->validatedWhenNotDehydrated(false);
        return $component;
    }
    /** Fields that only reveal other fields stay local when the browser can do the toggling. */
    private static function liveForVisibility(mixed $component): mixed
    {
        return self::clientVisibility() ? $component : $component->live();
    }

    public function mount(): void
    {
        $this->nameExample=(new RandomWordService())->word();
        abort_unless(static::canAccess(), 403);
        $requestedGame=request()->query('game');
        abort_unless($requestedGame===null || in_array($requestedGame,['minecraft','other'],true),422);
        $minecraftAllowed=\Simplify\Panel\Services\FeatureSettings::enabled('easy_creator');
        $gamesAllowed=\Simplify\Panel\Services\FeatureSettings::enabled('game_creator');
        $this->creationGame=$requestedGame??($minecraftAllowed && (CreatorAccess::admin() || CreatorAccess::settings()['minecraft_enabled'])?'minecraft':'other');
        // Each add-on can be switched off on its own: the other creator then refuses that game.
        abort_unless($this->creationGame==='minecraft' ? $minecraftAllowed : $gamesAllowed,403);
        abort_unless($this->creationGame!=='minecraft' || CreatorAccess::admin() || CreatorAccess::settings()['minecraft_enabled'],403);
        $game=$this->creationGame ?: (CreatorAccess::admin() || CreatorAccess::settings()['minecraft_enabled']?'minecraft':'other');
        $this->draftSession = microtime(true);
        $node = CreatorAccess::nodes()->first();
        $resourceDefaults = $game === 'minecraft' ? \Simplify\Panel\Services\CreationDefaults::forType('vanilla') : ['cpu'=>100,'ram_gb'=>4,'disk_mb'=>10240];
        $defaultRam = CreatorAccess::admin() ? $resourceDefaults['ram_gb'] : min($resourceDefaults['ram_gb'], max(1, (int) floor(CreatorAccess::settings()['max_memory_mb'] / 1024)));
        $this->form->fill([
            'game' => $game,
            'game_egg_id' => null,
            'game_key' => null,
            'game_variables' => [],
            'name' => '',
            'server_alias' => '',
            'java_version' => 'auto',
            'image' => '',
            'show_on_website' => false,
            'website_background_id' => 'auto',
            'requested_subdomain' => '',
            'modpack_search' => '',
            'backup_enabled' => false,
            'backup_frequency' => 'daily',
            'backup_time' => '03:00',
            'backup_weekday' => '0',
            'backup_only_online' => false,
            'properties_enabled' => $game==='minecraft',
            'max_players' => 20,
            'motd' => 'A Minecraft Server',
            'difficulty' => 'easy',
            'gamemode' => 'survival',
            'pvp' => true,
            'white_list' => false,
            'allow_flight' => false,
            'view_distance' => 10,
            'spawn_protection' => 16,
            'server_type' => 'vanilla',
            'node_id' => $node?->id,
            'owner_id' => user()?->id,
            'allocation_id' => $node?->allocations()->whereNull('server_id')->orderBy('id')->value('id'),
            'minecraft_version' => '1.21.3',
            'ram_gb' => in_array($defaultRam,[2,4,8,12],true) ? (string)$defaultRam : 'custom',
            'ram_custom_gb' => $defaultRam,
            'disk_mb' => CreatorAccess::admin() ? $resourceDefaults['disk_mb'] : min($resourceDefaults['disk_mb'], (int) CreatorAccess::settings()['max_disk_mb']),
            'cpu' => CreatorAccess::admin() ? $resourceDefaults['cpu'] : min($resourceDefaults['cpu'], (int) CreatorAccess::settings()['max_cpu']),
            'cpu_choice' => (string) (CreatorAccess::admin() ? $resourceDefaults['cpu'] : min($resourceDefaults['cpu'], (int) CreatorAccess::settings()['max_cpu'])),
            'disk_choice' => (string) (CreatorAccess::admin() ? $resourceDefaults['disk_mb'] : min($resourceDefaults['disk_mb'], (int) CreatorAccess::settings()['max_disk_mb'])),
            'egg_id' => $game==='minecraft'?MinecraftCatalog::eggForType('vanilla')?->id:null,
            'skip_scripts' => false,
            'start_on_completion' => false,
            'server_color'=>\Simplify\Panel\Services\ServerAppearance::defaultColor(),
            'change_backup_schedule'=>false,
            'backup_interval_minutes'=>(int)\Simplify\Panel\Services\PerformanceSettings::get('backup_interval_minutes'),
            'backup_retention'=>(int)\Simplify\Panel\Services\PerformanceSettings::get('backup_retention'),
            'accept_eula' => false,
            'swap' => 0,
            'io' => 500,
            'backup_limit' => CreatorAccess::admin() ? 2 : min(2, (int) CreatorAccess::settings()['max_backups']),
            'database_limit' => 0,
            'allocation_limit' => 0,
            'oom_killer' => false,
            'environment_overrides' => [],
        ]);
        try {
            $disk = Storage::disk('local');
            $draftPath=$this->draftPath();
            $legacyPath='ssc/creator-drafts/' . (int) user()->id . '.enc';
            if (!$disk->exists($draftPath) && $this->creationGame!=='' && $disk->exists($legacyPath)) $draftPath=$legacyPath;
            if ($disk->exists($draftPath)) {
                $draft = json_decode(\Illuminate\Support\Facades\Crypt::decryptString($disk->get($draftPath)), true, 64, JSON_THROW_ON_ERROR);
                if (isset($draft['state']) && is_array($draft['state']) && ($draftPath===$this->draftPath() || $this->creationGame==='' || ($draft['state']['game']??'minecraft')===$this->creationGame)) {
                    $this->draftRestored = true;
                    $restoredStep=(int)($draft['step']??1);
                    $restoredStep=$this->restoreWizardStep($restoredStep,(int)($draft['wizard_version']??1),(array)($draft['wizard_steps']??[]));
                    $this->draftStep=max(1,min(count($this->wizardStepLabels()),$restoredStep));
                    $furthest=(int)($draft['furthest_step']??($draft['step']??1));
                    $furthest=$this->restoreWizardStep($furthest,(int)($draft['wizard_version']??1),(array)($draft['wizard_steps']??[]));
                    $this->furthestStep=max($this->draftStep,min(count($this->wizardStepLabels()),$furthest));
                    $this->editingAdvanced = (bool) ($draft['advanced'] ?? false);
                    $this->draftIcon = $draft['icon'] ?? null;
                    $restored = $draft['state'];
                    foreach (['cpu_choice'=>'cpu', 'disk_choice'=>'disk_mb'] as $choice=>$resource) {
                        if (!isset($restored[$choice])) {
                            $value = (string) ($restored[$resource] ?? $this->data[$resource]);
                            $restored[$choice] = array_key_exists($value, $this->resourceOptions($resource, $restored['server_type'] ?? 'vanilla')) ? $value : 'custom';
                        }
                    }
                    $this->form->fill(array_replace($this->data ?? [], $restored, ['server_icon' => null,'game'=>$game]));
                }
            }
        } catch (Throwable $exception) {
            report($exception);
            Notification::make()->title('Draft could not be restored')->warning()->send();
        }
        if (empty($this->data['server_color'])) $this->data['server_color']=\Simplify\Panel\Services\ServerAppearance::defaultColor();
    }

    public function wizardStepLabels(): array
    {
        // Other games have no Minecraft server properties step.
        $labels=$this->creationGame==='minecraft'?['Server Type','Version & Content','Server properties']:['Game','Server','Game settings'];
        foreach (\Simplify\Panel\Services\Extensions::webAdapter()?->creatorSteps($this) ?? [] as $step) $labels[]=(string)($step->getLabel() ?? 'Website display');
        if (\Simplify\Panel\Services\FeatureSettings::enabled('backups')) $labels[]='Backups';
        return [...$labels,'Review'];
    }

    private function restoreWizardStep(int $step,int $version,array $savedLabels=[]): int
    {
        $labels=$this->wizardStepLabels();
        if ($savedLabels) {
            for ($index=max(0,$step-1);$index<count($savedLabels);++$index) {
                $current=array_search($savedLabels[$index],$labels,true);
                if ($current!==false) return $current+1;
            }
            return count($labels);
        }
        if ($this->creationGame==='minecraft' && $version<2) $step=max(1,$step-1);
        if ($version<3 && in_array('Website display',$labels,true) && $step>=($this->creationGame==='minecraft'?4:5)) ++$step;
        return max(1,min(count($labels),$step));
    }

    public function hydrate(): void
    {
        if ($this->creationGame!=='') $this->data['game']=$this->creationGame;
        $this->syncResourceChoices();
    }

    /** Request updates arrive after hydrate(); re-apply presets so a hidden custom field cannot win. */
    public function updated(string $name): void
    {
        if (in_array($name, ['data.cpu_choice', 'data.disk_choice', 'data.cpu', 'data.disk_mb'], true)) $this->syncResourceChoices();
    }

    /** CPU/disk presets are plain radios; copy the chosen preset into the value fields once per request. */
    private function syncResourceChoices(): void
    {
        foreach (['cpu_choice' => 'cpu', 'disk_choice' => 'disk_mb'] as $choice => $field) {
            $value = $this->data[$choice] ?? 'custom';
            if ($value !== 'custom' && is_numeric($value)) $this->data[$field] = (int) $value;
        }
    }

    /**
     * Empty Game Server Login Token fields get a token from the creator's own Steam key when they turned
     * automatic tokens on (profile → Steam). One token per server, named after the server.
     */
    private function autoSteamTokens(array $state): array
    {
        $values = (array) ($state['game_variables'] ?? []);
        $egg = OtherGameCatalog::selected($state['game_egg_id'] ?? null);
        if (!$egg || !\Simplify\Panel\Services\SteamAccount::autoTokens(user())) return $values;
        foreach ($egg->variables as $variable) {
            $name = (string) $variable->env_variable;
            if (!in_array(strtoupper($name), \Simplify\Panel\Services\SteamAccount::TOKEN_VARIABLES, true) || trim((string) ($values[$name] ?? '')) !== '') continue;
            if (!CreatorAccess::admin() && !($variable->user_editable && $variable->user_viewable)) continue;
            try {
                $values[$name] = \Simplify\Panel\Services\SteamAccount::createTokenForGame(user(), (string) $egg->name, trim((string) ($state['name'] ?? '')) ?: (string) $egg->name);
            } catch (ValidationException $exception) {
                throw ValidationException::withMessages(['data.game_variables.'.$name => 'No Steam token could be created: '.collect($exception->errors())->flatten()->first().' Paste a token yourself or try again.']);
            }
        }
        return $values;
    }

    /** Steam art for the chosen game, if the add-on option is on and the store has a match. */
    public function steamArt(): ?array
    {
        if (!\Simplify\Panel\Services\SteamArt::enabled()) return null;
        $egg = OtherGameCatalog::selected($this->data['game_egg_id'] ?? null);
        return $egg ? $this->memo('steam-art-'.$egg->id, fn () => \Simplify\Panel\Services\SteamArt::find((string) $egg->name)) : null;
    }

    /** Leaving the Game step: an index game is downloaded and installed, then used like any installed egg. */
    public function installSelectedGame(): void
    {
        if (($this->creationGame ?: ($this->data['game'] ?? '')) !== 'other') return;
        $egg = \Simplify\Panel\Services\GameCatalog::resolve($this->data['game_key'] ?? null);
        if (!$egg) throw ValidationException::withMessages(['data.game_key' => 'Choose a game.']);
        $changed = (int) ($this->data['game_egg_id'] ?? 0) !== (int) $egg->id;
        $this->data['game_key'] = 'egg:'.$egg->id;
        $this->data['game_egg_id'] = $egg->id;
        $this->data['egg_id'] = $egg->id;
        if ($changed || empty($this->data['game_variables'])) $this->data['game_variables'] = OtherGameCatalog::defaults($egg);
        // On by default for every newly chosen game; a choice made for the same game is kept.
        if ($changed || !isset($this->data['steam_art'])) $this->data['steam_art'] = true;
    }

    public function form(Schema $schema): Schema
    {
        if ($this->creationGame!=='') $this->data['game']=$this->creationGame;
        return $schema
            ->statePath('data')
            ->components([
                Hidden::make('game')->default(fn () => $this->creationGame ?: 'minecraft')->visible(fn () => $this->creationGame!=='')->required()->in(fn () => [$this->creationGame ?: 'minecraft']),
                Wizard::make([
                    ...($this->creationGame==='minecraft' ? [] : [Step::make('Game')->afterValidation(fn () => $this->installSelectedGame())->schema([
                        Radio::make('game')->hiddenLabel()->extraAttributes(['class' => 'ssc-game-options'])->visible(fn () => $this->creationGame==='')
                            ->view('pelican-simplify::filament.admin.card-radio')
                            ->options(fn () => $this->creationGame!=='' ? [$this->creationGame=>$this->creationGame==='minecraft'?'Minecraft':'Other game'] : (CreatorAccess::admin() || CreatorAccess::settings()['minecraft_enabled']
                                ? ['minecraft' => 'Minecraft', 'other' => 'Other game'] : ['other' => 'Other game']))
                            ->descriptions(['minecraft' => 'Vanilla, plugins or a modpack.', 'other' => 'Search the games available on this panel.'])
                            ->required()->in(fn () => $this->creationGame!==''?[$this->creationGame]:['minecraft','other'])->live()->columns(2)
                            ->afterStateUpdated(function ($state, Set $set): void {
                                $set('game_egg_id', null);
                                $set('game_key', null);
                                $set('game_variables', []);
                                $set('egg_id', $state === 'minecraft' ? MinecraftCatalog::eggForType('vanilla')?->id : null);
                                $set('server_type', 'vanilla');
                                $set('properties_enabled', $state === 'minecraft');
                                $set('server_icon', null);
                                $set('environment_overrides', []);
                                $set('image', '');
                                $set('startup', '');
                            }),
                        // Installed games and the Pelican egg index in one searchable grid; an index game is installed on Next.
                        \Filament\Forms\Components\ViewField::make('game_key')->label('Find a game')
                            ->view('pelican-simplify::filament.admin.game-picker')
                            ->viewData(fn () => ['cards' => ($this->creationGame ?: ($this->data['game'] ?? '')) === 'other' ? \Simplify\Panel\Services\GameCatalog::cards() : []])
                            ->live()
                            ->required(fn (Get $get) => ($this->creationGame ?: $get('game')) === 'other')
                            ->visible(fn (Get $get) => ($this->creationGame ?: $get('game')) === 'other')
                            ->validationMessages(['required' => 'Choose a game.'])
                            ->afterStateUpdated(function ($state, Set $set): void {
                                $id = is_string($state) && str_starts_with($state, 'egg:') ? (int) substr($state, 4) : null;
                                $set('game_egg_id', $id);
                                $set('egg_id', $id);
                                $egg = $id ? OtherGameCatalog::selected($id) : null;
                                $set('game_variables', $egg ? OtherGameCatalog::defaults($egg) : []);
                                $set('steam_art', true);
                            }),
                        Hidden::make('game_egg_id'),
                    ])]),
                    Step::make('Type')
                        ->label(fn () => $this->creationGame === 'other' ? 'Server' : 'Server Type')
                        ->schema([
                            Radio::make('server_type')
                                ->label('Server type')->hiddenLabel()->visible(fn (Get $get) => ($this->creationGame ?: $get('game')) === 'minecraft')
                                ->view('pelican-simplify::filament.admin.card-radio')
                                ->options([
                                    'vanilla' => 'Vanilla',
                                    'plus' => 'Vanilla+',
                                    'modded' => 'Modded',
                                ])
                                ->descriptions([
                                    'vanilla' => 'Classic Minecraft. No mods or plugins.',
                                    'plus' => 'Paper server with plugin support.',
                                    'modded' => 'Install a complete Minecraft modpack.',
                                ])
                                ->columns(['default' => 1, 'md' => 3])
                                ->extraAttributes(['class' => 'ssc-card-options ssc-type-options'])
                                ->required()->live()
                                ->afterStateUpdated(function ($state, Set $set): void {
                                    $set('egg_id', MinecraftCatalog::eggForType((string) $state)?->id);
                                    $set('modpack_project', null);
                                    $set('featured_modpack', null);
                                    $set('modpack_version', null);
                                    $profile = \Simplify\Panel\Services\CreationDefaults::forType((string)$state);
                                    $cpu = $profile['cpu'];
                                    $cpu = CreatorAccess::admin() ? $cpu : min($cpu, (int) CreatorAccess::settings()['max_cpu']);
                                    $set('cpu', $cpu); $set('cpu_choice', (string) $cpu);
                                    $disk = $profile['disk_mb'];
                                    $ram = CreatorAccess::admin() ? $profile['ram_gb'] : min($profile['ram_gb'],max(1,(int)floor(CreatorAccess::settings()['max_memory_mb']/1024)));
                                    $set('ram_gb',in_array($ram,[2,4,8,12],true)?(string)$ram:'custom');
                                    $set('ram_custom_gb',$ram);
                                    $disk = CreatorAccess::admin() ? $disk : min($disk, (int) CreatorAccess::settings()['max_disk_mb']);
                                    $set('disk_mb', $disk); $set('disk_choice', (string) $disk);
                                }),
                            TextInput::make('name')
                                ->label('Server name')
                                ->required()->maxLength(255)
                                ->live(onBlur: true, condition: fn () => \Simplify\Panel\Services\Subdomains::active())
                                ->afterStateUpdated(fn ($state, $old, Set $set, Get $get) => $this->suggestSubdomain((string) $state, (string) $old, $set, $get))
                                ->placeholder(fn()=>$this->nameExample)
                                ->helperText('This name is used by the server and dashboard.')
                                ->suffixAction(
                                    Action::make('random_name')
                                        ->tooltip('Generate a random server name')
                                        ->extraAttributes(['x-on:click' => 'refreshCount++'])
                                        ->icon('tabler-dice-5')
                                        ->action(function (Set $set, Get $get): void {
                                            $old = (string) $get('name');
                                            $set('name', $name = (new RandomWordService())->word());
                                            $this->suggestSubdomain($name, $old, $set, $get);
                                        })
                                ),
                            TextInput::make('requested_subdomain')->label('Subdomain (optional)')
                                ->visible(fn () => \Simplify\Panel\Services\Subdomains::active())
                                ->suffix(fn () => '.'.\Simplify\Panel\Services\Subdomains::settings()['domain'])
                                ->maxLength(63)->placeholder(fn () => strtolower(preg_replace('/[^a-z0-9-]+/i', '', (string) $this->nameExample)) ?: 'oak')
                                ->dehydrateStateUsing(fn ($state) => strtolower(trim((string) $state)))
                                ->rules([fn (): \Closure => function (string $attribute, $value, \Closure $fail): void {
                                    if ($error = \Simplify\Panel\Services\Subdomains::check(strtolower(trim((string) $value)))) $fail($error);
                                }])
                                ->helperText('Players join with this address. It follows the server name until you change it; leave empty to use the port address.'),
                            View::make('pelican-simplify::filament.admin.server-colors'),
                            Hidden::make('server_color')->nullable()->rule('regex:/^#[0-9a-fA-F]{6}$/'),
                            // Steam banner and icon for other games, when the store has a match (looked up once, cached).
                            \Filament\Forms\Components\Toggle::make('steam_art')->label('Use the Steam banner and icon')->default(true)
                                ->helperText(fn () => ($art = $this->steamArt()) ? 'Found on Steam: '.$art['name'].'. Copied once to this panel; you can change it later in the server settings.' : null)
                                ->visible(fn () => $this->creationGame === 'other' && $this->steamArt() !== null),
                            View::make('pelican-simplify::filament.admin.steam-art-preview')
                                ->viewData(fn () => ['art' => $this->steamArt()])
                                ->visible(fn (Get $get) => $this->creationGame === 'other' && $get('steam_art') && $this->steamArt() !== null),
                            TextInput::make('server_alias')->label('Server alias (optional)')
                                ->maxLength(100)->placeholder('Optional internal reference')
                                ->helperText('Internal reference only. The dashboard uses Server name.'),
                            Select::make('node_id')
                                ->label('Node')->required()->searchable()->live()->dehydratedWhenHidden()
                                ->options(fn () => $this->memo('node-options', fn () => CreatorAccess::nodes()->pluck('name', 'id')->all()))
                                ->visible(fn () => $this->multipleNodes())
                                ->afterStateUpdated(function ($state, Set $set): void {
                                    $set('allocation_id', Node::find($state)?->allocations()->whereNull('server_id')->orderBy('id')->value('id'));
                                }),
                            Select::make('allocation_id')
                                ->dehydratedWhenHidden()->label('Port / allocation')->visible(fn () => $this->multipleNodes())
                                ->required()->searchable()
                                // Also built when hidden (single node): Select validates the auto-picked allocation against these options.
                                ->options(fn (Get $get) => $this->memo('allocations-'.$get('node_id'), fn () => Allocation::query()
                                    ->where('node_id', $get('node_id'))
                                    ->whereNull('server_id')
                                    ->get()->mapWithKeys(fn (Allocation $allocation) => [
                                        $allocation->id => $allocation->address ?? ($allocation->ip . ':' . $allocation->port),
                                    ])->all()))
                                ->helperText('Only free allocations are shown. Create an allocation under Nodes if this is empty.'),
                            Hidden::make('owner_id'),
                        ])->columns(1)
                        ->afterValidation(function (): void {
                            if (($this->data['game'] ?? '') === 'other' || ($this->data['server_type'] ?? '') !== 'modded') {
                                $this->prepareEgg();
                            }
                        }),
                    Step::make('Content')
                        ->label(fn () => $this->creationGame === 'other' ? 'Game settings' : 'Version & Content')
                        ->schema([
                            Section::make('Minecraft content')->visible(fn (Get $get) => ($this->creationGame ?: $get('game')) === 'minecraft')->schema([
                            Select::make('minecraft_version')
                                ->label('Minecraft version')
                                ->searchable()->required(fn (Get $get) => $get('server_type') !== 'modded')
                                ->options(fn () => $this->memo('minecraft-versions', fn () => MinecraftCatalog::versions()))
                                ->visible(fn (Get $get) => $get('server_type') !== 'modded')
                                ->helperText('Your chosen egg downloads this Minecraft version during installation.'),
                            Hidden::make('modpack_search')->dehydrated(false),
                            Radio::make('modpack_project')
                                ->label('Find a modpack')
                                ->view('pelican-simplify::filament.admin.modpack-picker')
                                ->options(fn (Get $get) => collect(ModpackCatalog::results($get('modpack_search'), $get('modpack_project')))->pluck('title', 'key')->all())
                                ->live()->required(fn (Get $get) => $get('server_type') === 'modded')
                                ->visible(fn (Get $get) => $get('server_type') === 'modded')
                                ->afterStateUpdated(function ($state, Set $set): void {
                                    $set('modpack_version', null);
                                    $set('egg_id', ModpackCatalog::egg($state)?->id);
                                })
                                ->helperText('Modrinth, FTB and supported Technic serverpacks in one list. CurseForge appears when an API key is configured.'),
                            Select::make('modpack_version')
                                ->label('Modpack version')
                                ->options(fn (Get $get) => ModpackCatalog::versions($get('modpack_project')))
                                ->searchable()->required(fn (Get $get) => $get('server_type') === 'modded')
                                ->visible(fn (Get $get) => $get('server_type') === 'modded')
                                ->helperText('The pack determines the Minecraft and modloader versions.'),
                            Section::make('Vanilla+ / Paper')
                                ->description('SSC will install Paper using your selected Minecraft version. Automatic browsing and installation of individual plugins is not included in this first test version.')
                                ->visible(fn (Get $get) => $get('server_type') === 'plus'),
                            ]),
                            Section::make('Game settings')->visible(fn (Get $get) => ($this->creationGame ?: $get('game')) === 'other')
                                ->description(fn (Get $get) => OtherGameCatalog::selected($get('game_egg_id'))?->description ?? 'Choose a game first.')
                                ->schema(function (Get $get): array {
                                    $egg = OtherGameCatalog::selected($get('game_egg_id'));
                                    if (!$egg) return [];
                                    [$main, $advanced] = OtherGameCatalog::fields($egg);
                                    return [
                                        \Filament\Schemas\Components\Grid::make(2)->schema($main),
                                        ...($advanced ? [Section::make('Advanced')->description('Install and Steam options; the defaults work for most servers.')->collapsible()->collapsed()->schema([\Filament\Schemas\Components\Grid::make(2)->schema($advanced)])] : []),
                                    ];
                                }),
                            Section::make('Resources')->description('Starting values follow your server type. Choose a preset or enter your own allocation.')->schema([
                            self::liveForVisibility(Radio::make('ram_gb')
                                ->view('pelican-simplify::filament.admin.card-radio')
                                ->label('RAM')
                                ->options(['2' => '2 GB', '4' => '4 GB', '8' => '8 GB', '12' => '12 GB', 'custom' => 'Custom'])
                                ->descriptions(['2' => 'Small worlds', '4' => 'Everyday play', '8' => 'More headroom', '12' => 'Larger modpacks', 'custom' => 'Choose your own'])
                                ->extraAttributes(['class' => 'ssc-ram-options'])
                                ->required()),
                            self::showWhen(TextInput::make('ram_custom_gb')
                                ->label('Custom RAM in GB')
                                ->numeric()->minValue(1)->maxValue(fn () => CreatorAccess::admin() ? 128 : max(1, (int) floor(CreatorAccess::settings()['max_memory_mb'] / 1024)))->step(1)
                                ->required(fn (Get $get) => $get('ram_gb') === 'custom'),
                                "\$get('ram_gb') === 'custom'", fn (Get $get) => $get('ram_gb') === 'custom'),
                            // Presets fill the custom fields on every request (see syncResourceChoices); no round trip per click.
                            self::liveForVisibility(Radio::make('cpu_choice')->label('CPU allocation')
                                ->view('pelican-simplify::filament.admin.card-radio')->extraAttributes(['class'=>'ssc-ram-options'])
                                ->options(fn (Get $get) => $this->resourceOptions('cpu', $get('server_type')))
                                ->default(fn (Get $get) => (string) $get('cpu'))->required()),
                            self::showWhen(TextInput::make('cpu')->label('Custom CPU (%)')->integer()->minValue(CreatorAccess::admin() ? 0 : 1)
                                ->maxValue(fn () => CreatorAccess::admin() ? 1600 : (int) CreatorAccess::settings()['max_cpu'])
                                ->required()->dehydratedWhenHidden()
                                ->helperText('100% = one CPU core; administrators may use 0 for unlimited.'),
                                "\$get('cpu_choice') === 'custom'", fn (Get $get) => $get('cpu_choice') === 'custom', true),
                            self::liveForVisibility(Radio::make('disk_choice')->label('Disk allocation')
                                ->view('pelican-simplify::filament.admin.card-radio')->extraAttributes(['class'=>'ssc-ram-options'])
                                ->options(fn (Get $get) => $this->resourceOptions('disk_mb', $get('server_type')))
                                ->default(fn (Get $get) => (string) $get('disk_mb'))->required()),
                            self::showWhen(TextInput::make('disk_mb')->label('Custom disk (MB)')->integer()->minValue(1024)
                                ->maxValue(fn () => CreatorAccess::admin() ? 10485760 : (int) CreatorAccess::settings()['max_disk_mb'])
                                ->required()->dehydratedWhenHidden(),
                                "\$get('disk_choice') === 'custom'", fn (Get $get) => $get('disk_choice') === 'custom', true),
                            ]),
                        ])
                        ->afterValidation(fn () => $this->prepareEgg()),
                    Step::make('Server properties')->visible(fn () => $this->creationGame !== 'other')
                        ->schema([
                            Section::make('Server properties')->visible(fn (Get $get) => ($this->creationGame ?: $get('game')) === 'minecraft')
                                ->description('Set common Minecraft options before creation. Applied after installation; other installer settings are preserved.')
                                ->schema([
                                    Toggle::make('properties_enabled')->label('Apply these settings')->columnSpanFull(),
                                    TextInput::make('max_players')->label('Maximum players')->integer()->minValue(1)->maxValue(1000)->required(),
                                    Textarea::make('motd')->label('MOTD')->rows(2)->maxLength(512)->required()->helperText('Server description. Minecraft § colour codes are supported.'),
                                    Select::make('difficulty')->options(['peaceful' => 'Peaceful', 'easy' => 'Easy', 'normal' => 'Normal', 'hard' => 'Hard'])->required(),
                                    Select::make('gamemode')->options(['survival' => 'Survival', 'creative' => 'Creative', 'adventure' => 'Adventure', 'spectator' => 'Spectator'])->required(),
                                    TextInput::make('view_distance')->label('View distance (chunks)')->integer()->minValue(2)->maxValue(32)->required(),
                                    TextInput::make('spawn_protection')->label('Spawn protection (blocks)')->integer()->minValue(0)->maxValue(1000)->required(),
                                    Toggle::make('pvp')->label('PvP'),
                                    Toggle::make('white_list')->label('Whitelist')->helperText('Players must be added to the whitelist after creation.'),
                                    Toggle::make('allow_flight')->label('Allow flight'),
                                ])->columns(2),
                            FileUpload::make('server_icon')->label('Server icon (optional)')->visible(fn (Get $get) => ($this->creationGame ?: $get('game')) === 'minecraft')
                                ->image()->acceptedFileTypes(['image/png'])->maxSize(100)
                                ->imageResizeMode('cover')->imageResizeTargetWidth('64')->imageResizeTargetHeight('64')
                                ->imageEditor()->imageEditorAspectRatios(['1:1'])->storeFiles(false)
                                ->helperText(fn (Get $get) => 'PNG, 64 × 64 pixels, up to 100 KB. Uploaded as server-icon.png after installation.' . ($get('server_type') === 'modded' ? ' Leave empty to use the modpack\'s icon.' : '')),
                        ]),
                    ... (\Simplify\Panel\Services\Extensions::webAdapter()?->creatorSteps($this) ?? []),
                    Step::make('Backups')->visible(fn()=>\Simplify\Panel\Services\FeatureSettings::enabled('backups'))->schema([
                        View::make('pelican-simplify::filament.admin.backup-defaults')->columnSpanFull(),
                        self::liveForVisibility(Toggle::make('change_backup_schedule')->label('Change backup schedule')->default(false)
                            ->visible(fn () => user()?->isRootAdmin())),
                        self::showWhen(TextInput::make('backup_interval_minutes')->label('Interval (minutes)')->integer()->minValue(30)->maxValue(10080)->required(),
                            "\$get('change_backup_schedule')", fn (Get $get) => $get('change_backup_schedule')),
                        self::showWhen(TextInput::make('backup_retention')->label('Recovery points')->integer()->minValue(1)->maxValue(100)->required(),
                            "\$get('change_backup_schedule')", fn (Get $get) => $get('change_backup_schedule')),
                    ]),
                    Step::make('Review')
                        ->schema([
                            View::make('pelican-simplify::filament.admin.overview')->columnSpanFull(),
                            Checkbox::make('accept_eula')->label('I accept the Minecraft EULA')->default(false)
                                ->visible(fn (Get $get) => ($this->creationGame ?: $get('game'))==='minecraft')
                                ->accepted(fn (Get $get) => ($this->creationGame ?: $get('game'))==='minecraft')
                                ->helperText(new HtmlString('<a href="https://www.minecraft.net/en-us/eula" target="_blank" rel="noopener noreferrer">Read the Minecraft EULA</a>'))
                                ->columnSpanFull(),
                            View::make('pelican-simplify::filament.admin.advanced-editor')
                                ->columnSpanFull()
                                ->schema([
                                    Select::make('egg_id')
                                        ->dehydratedWhenHidden()->label('Egg')->visible(fn () => CreatorAccess::admin())
                                        ->options(fn () => $this->memo('eggs', fn () => Egg::query()->orderBy('name')->pluck('name', 'id')->all()))
                                        ->searchable()->required()
                                        ->helperText('Automatically chosen and imported if missing. Existing eggs are kept. Only change if you know what you are doing.'),
                                    Select::make('java_version')->visible(fn (Get $get) => CreatorAccess::admin() && ($this->creationGame ?: $get('game')) === 'minecraft')->dehydratedWhenHidden()
                                        ->label('Java version')
                                        ->options(['auto' => 'Automatic (recommended)', '8' => 'Java 8', '11' => 'Java 11', '16' => 'Java 16', '17' => 'Java 17', '21' => 'Java 21', '25' => 'Java 25'])
                                        ->required()
                                        ->helperText('Chosen automatically from Minecraft / the selected modpack. Override for packs with special requirements.'),
                                    TextInput::make('image')->visible(fn () => CreatorAccess::admin())
                                        ->label('Docker image override')
                                        ->placeholder('Empty = automatic Java image')
                                        ->helperText('Optional. A custom image takes priority over the Java selector.'),
                                    Textarea::make('startup')->visible(fn () => CreatorAccess::admin())
                                        ->label('Startup command override')
                                        ->placeholder('Empty = default startup from egg'),
                                    KeyValue::make('environment_overrides')->visible(fn () => CreatorAccess::admin())
                                        ->label('Egg variable overrides')
                                        ->keyLabel('Environment variable')
                                        ->valueLabel('Value')
                                        ->helperText('Optional. Names must match the variables configured in your egg first.'),
                                    Toggle::make('skip_scripts')->visible(fn () => CreatorAccess::admin())
                                        ->label('Skip egg installation scripts')->default(false),
                                ])->columns(2),
                        ]),
                ])
                    ->nextAction(fn (Action $action) => $action->label('Next')->color('gray')->extraAttributes(['class'=>'ssc-next-button']))
                    ->previousAction(fn (Action $action) => $action->label('Back')->color('gray')->extraAttributes(['class'=>'ssc-back-button']))
                    ->startOnStep(fn () => $this->draftStep)
                    ->columnSpanFull()
                    ->submitAction(new HtmlString(view('pelican-simplify::filament.admin.create-action')->render())),
            ]);
    }

    public function resourceOptions(string $resource, ?string $type): array
    {
        $default = \Simplify\Panel\Services\CreationDefaults::forType($type ?? 'vanilla')[$resource];
        $limit = CreatorAccess::admin() ? ($resource === 'cpu' ? 1600 : 10485760) : (int) CreatorAccess::settings()[$resource === 'cpu' ? 'max_cpu' : 'max_disk_mb'];
        $default = min($default, $limit);
        $values = array_unique([...($resource === 'cpu' ? [100,150,200,400] : [5120,10240,20480,40960]), $default]);
        sort($values); $options = [];
        foreach ($values as $value) if ($value <= $limit) $options[(string) $value] = $resource === 'cpu' ? $value.'%' : round($value / 1024, 2).' GB';
        return $options + ['custom' => 'Custom'];
    }

    /** The subdomain follows the server name until someone types their own. */
    private function suggestSubdomain(string $name, string $old, Set $set, Get $get): void
    {
        if (!\Simplify\Panel\Services\Subdomains::active()) return;
        $current = (string) $get('requested_subdomain');
        if ($current === '' || $current === \Simplify\Panel\Services\Subdomains::suggest($old)) $set('requested_subdomain', \Simplify\Panel\Services\Subdomains::suggest($name));
    }

    public function availableServerColors(): array
    {
        return \Simplify\Panel\Services\ServerAppearance::choices();
    }

    public function create(ServerCreationService $service): void
    {
        if (!static::canAccess()) {
            abort(403);
        }

        try {
            $this->syncResourceChoices();
            $this->prepareEgg(false);
            $state = $this->form->getState();
            $state['start_on_completion']=false;
            if (empty($state['server_color'])) $state['server_color']=\Simplify\Panel\Services\ServerAppearance::defaultColor();
            $state['backup_enabled']=false;
            validator(['server_color'=>$state['server_color']??null],['server_color'=>'nullable|regex:/^#[0-9a-fA-F]{6}$/'])->validate();
            if (\Simplify\Panel\Services\FeatureSettings::enabled('automatic_backups') && ($state['change_backup_schedule']??false)) {
                abort_unless(user()?->isRootAdmin(),403);
                validator($state,['backup_interval_minutes'=>'required|integer|min:30|max:10080','backup_retention'=>'required|integer|min:1|max:100'])->validate();
            }
            CreatorAccess::assertState($state);
            if ($this->creationGame!=='' && ($state['game']??'minecraft')!==$this->creationGame) {
                throw ValidationException::withMessages(['data.game'=>'Open the creator for the selected game.']);
            }
            if (($state['game']??'minecraft')==='minecraft') {
                validator(['accept_eula'=>$state['accept_eula']??false],['accept_eula'=>'accepted'])->validate();
            }
            if (!CreatorAccess::admin() || CreatorAccess::nodes()->count() === 1) {
                $state['allocation_id'] = Allocation::query()->where('node_id', $state['node_id'] ?? 0)->whereNull('server_id')->orderBy('id')->value('id');
                // A subdomain needs a forwarded port; fall back to any free port when the range is full.
                if (\Simplify\Panel\Services\Subdomains::active() && trim((string) ($state['requested_subdomain'] ?? '')) !== '') {
                    $state['allocation_id'] = \Simplify\Panel\Services\Subdomains::allocationFor((int) ($state['node_id'] ?? 0)) ?? $state['allocation_id'];
                }
                $this->data['allocation_id'] = $state['allocation_id'];
            }
            $subdomain = \Simplify\Panel\Services\Subdomains::active() ? strtolower(trim((string) ($state['requested_subdomain'] ?? ''))) : '';
            if ($error = \Simplify\Panel\Services\Subdomains::check($subdomain)) throw ValidationException::withMessages(['data.requested_subdomain' => $error]);
            if (($state['game'] ?? 'minecraft') === 'other') {
                $state['properties_enabled'] = false;
                $state['server_icon'] = null;
                $state['website_background_id'] = 'auto';
            }
            $prepared = ServerProperties::prepare($state, true, ($state['game'] ?? 'minecraft') === 'minecraft' ? $this->draftIcon : null);
            validator(['server_alias'=>$state['server_alias']??''],['server_alias'=>'nullable|string|max:100'])->validate();
            if (($state['game'] ?? '') === 'other') $state['game_variables'] = $this->autoSteamTokens($state);
            $payload = $this->buildPayload($state);
            $websitePack = ($state['game'] ?? 'minecraft') === 'minecraft' ? \Simplify\Panel\Services\ModpackSnapshot::fromState($state, $payload['environment']) : null;
            $server = CreationGuard::create($service, $payload, $state);
            try {
                if (!empty($state['server_color']) && !Storage::disk('local')->put('simplify/appearance/'.$server->uuid.'.json',json_encode(['color'=>strtolower($state['server_color'])],JSON_THROW_ON_ERROR))) throw new \RuntimeException('Could not save server color.');
                if (\Simplify\Panel\Services\FeatureSettings::enabled('automatic_backups') && ($state['change_backup_schedule']??false)) \Simplify\Panel\Services\AutomaticBackups::save($server,['enabled'=>true,'interval_minutes'=>$state['backup_interval_minutes'],'retention'=>$state['backup_retention']]);
            } catch (Throwable $exception) {
                report($exception);Notification::make()->title('Server created; appearance or backup options could not be saved')->warning()->send();
            }
            try { $this->finishDraft(); } catch (Throwable $exception) { $this->draftCompleted = true; report($exception); $this->dispatch('ssc-draft-completed'); Notification::make()->title('Server created; draft cleanup failed')->body('Do not submit the saved draft again. Discard it before creating another server.')->warning()->send(); }
            try {
                \Simplify\Panel\Services\ServerMetadata::save($server,[
                    'server_alias'=>trim((string)($state['server_alias']??'')),
                    'game'=>$state['game']??'minecraft','modpack'=>$websitePack,'server_flavour'=>$state['server_type']??'vanilla',
                ]);
                \Simplify\Panel\Services\Extensions::webAdapter()?->saveCreated($server,$state);
            } catch(Throwable $exception) {
                report($exception);Notification::make()->title('Server created; metadata could not be saved')->body('Do not submit the server again.')->warning()->send();
            }
            if ($subdomain !== '') {
                try { \Simplify\Panel\Services\Subdomains::request($server, $subdomain); }
                catch (Throwable $exception) { report($exception);Notification::make()->title('Server created; the subdomain could not be saved')->body('Choose it again in the server\'s Network section.')->warning()->send(); }
            }
            if (($state['game'] ?? '') === 'other' && !empty($state['steam_art']) && ($art = $this->steamArt())) {
                // Downloaded after the response, so creating the server is not slowed down by Steam.
                app()->terminating(fn () => \Simplify\Panel\Services\SteamArt::apply($server, $art));
            }
            // The accepted EULA is written with the other settings once installation finishes.
            if (($state['game'] ?? 'minecraft') === 'minecraft' && !in_array(config('queue.default'), ['sync', 'null'], true)) {
                $prepared = [...$prepared, 'eula' => true];
                if (empty($prepared['icon']) && ModpackCatalog::safeUrl($websitePack['modpack_icon_url'] ?? null)) $prepared['icon_url'] = $websitePack['modpack_icon_url'];
            }
            $warnings = [];
            if ($prepared) {
                try {
                    ApplyServerProperties::dispatch($server->uuid, $prepared, false)->delay(now()->addSeconds(10));
                } catch (Throwable $exception) {
                    report($exception);
                    $warnings[] = 'Settings could not be queued. The server stays stopped; apply the settings before starting it.';
                }
            }
            if ($warnings) {
                Notification::make()->title('Server created; setup needs attention')->body(implode(' ', $warnings))->warning()->send();
            }
            Notification::make()->title('Server created')
                ->body(($state['game']??'minecraft')==='minecraft' ? 'Installation has started. Your EULA acceptance and settings will be applied before you start the server. The server remains stopped.' : 'Installation has started. Check the server for progress.')
                ->success()->send();
            $this->redirect(\Simplify\Panel\Services\FeatureSettings::enabled('dashboard') ? \Simplify\Panel\Filament\App\Pages\Dashboard::getUrl(['server'=>$server->uuid],panel:'app') : Console::getUrl(panel: 'server', tenant: $server));
        } catch (ValidationException $exception) {
            $this->editingAdvanced = true;
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            Notification::make()->title('Could not create server')
                ->body($exception->getMessage())->danger()->send();
        }
    }

    /** @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private function buildPayload(array $state): array
    {
        CreatorAccess::assertState($state);
        if (($state['game'] ?? 'minecraft') === 'other') {
            return $this->buildOtherPayload($state);
        }
        $type = (string) ($state['server_type'] ?? '');
        if (!in_array($type, ['vanilla', 'plus', 'modded'], true)) {
            throw ValidationException::withMessages(['data.server_type' => 'Choose a server type.']);
        }

        $ram = ($state['ram_gb'] ?? null) === 'custom'
            ? (int) ($state['ram_custom_gb'] ?? 0)
            : (int) ($state['ram_gb'] ?? 0);
        if ($ram < 1 || $ram > 128) {
            throw ValidationException::withMessages(['data.ram_gb' => 'RAM must be between 1 and 128 GB.']);
        }

        $nodeId = (int) ($state['node_id'] ?? 0);
        if (!CreatorAccess::nodes()->where('id', $nodeId)->exists()) {
            throw ValidationException::withMessages(['data.node_id' => 'You do not have access to this node.']);
        }

        $allocation = Allocation::query()
            ->where('node_id', $nodeId)
            ->whereNull('server_id')
            ->find((int) ($state['allocation_id'] ?? 0));
        if (!$allocation) {
            throw ValidationException::withMessages(['data.allocation_id' => 'This port is no longer available.']);
        }

        $egg = Egg::query()->find((int) ($state['egg_id'] ?? 0));
        if (!$egg) {
            throw ValidationException::withMessages(['data.egg_id' => 'The required egg is missing. Import it into Pelican first.']);
        }

        // Prevent selecting an incompatible egg accidentally in the normal workflow.
        $projectId = (string) ($state['modpack_project'] ?? '');
        $versionId = (string) ($state['modpack_version'] ?? '');
        $originalEgg = $type === 'modded' ? ModpackCatalog::egg($projectId) : MinecraftCatalog::eggForType($type);
        if (!$originalEgg) {
            throw ValidationException::withMessages(['data.egg_id' => 'The required egg is unavailable. Continue Version & Content again to import it automatically.']);
        }
        if (!CreatorAccess::admin() && (int) $egg->id !== (int) $originalEgg->id) {
            throw ValidationException::withMessages(['data.egg_id' => 'Use the egg selected by SSC.']);
        }
        $minecraftVersion = (string) ($state['minecraft_version'] ?? '');
        if ($type !== 'modded' && !preg_match('/^\d+\.\d+(?:\.\d+)?$/', $minecraftVersion)) {
            throw ValidationException::withMessages(['data.minecraft_version' => 'Select a valid Minecraft version.']);
        }

        $env = MinecraftCatalog::eggVariables($egg, $type, $minecraftVersion, $projectId, $versionId);
        if ($type === 'modded') {
            $env = ModpackCatalog::applyVariables($egg, $state, $env);
        }
        $overrides = $state['environment_overrides'] ?? [];
        if (!is_array($overrides)) {
            throw ValidationException::withMessages(['data.environment_overrides' => 'Invalid egg variable overrides.']);
        }
        if (is_array($overrides)) {
            foreach ($overrides as $key => $value) {
                if (!array_key_exists((string) $key, $env)) {
                    throw ValidationException::withMessages(['data.environment_overrides' => 'Unknown egg variable: ' . (string) $key]);
                }
                $env[(string) $key] = (string) $value;
            }
        }

        $name = trim((string) ($state['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 255) {
            throw ValidationException::withMessages(['data.name' => 'Enter a server name.']);
        }

        $cpu = (int) ($state['cpu'] ?? 0);
        $disk = (int) ($state['disk_mb'] ?? 0);
        if ($cpu < 0 || $cpu > 1600 || $disk < 1024) {
            throw ValidationException::withMessages(['data.cpu' => 'Invalid CPU or disk limit.']);
        }

        $java = JavaRuntime::major($state);
        foreach (['JAVA_VERSION', 'JDK_VERSION'] as $key) {
            if ($java !== null && array_key_exists($key, $env) && !array_key_exists($key, $overrides)) {
                $env[$key] = (string) $java;
            }
        }
        $startup = trim((string) ($state['startup'] ?? ''));

        return [
            'name' => $name,
            'node_id' => $nodeId,
            'owner_id' => user()?->id,
            'allocation_id' => $allocation->id,
            'egg_id' => $egg->id,
            'environment' => $env,
            'memory' => $ram * 1024,
            'cpu' => $cpu,
            'disk' => $disk,
            'swap' => (int) ($state['swap'] ?? 0),
            'io' => 500,
            'backup_limit' => (int) ($state['backup_limit'] ?? 3),
            'database_limit' => (int) ($state['database_limit'] ?? 0),
            'allocation_limit' => (int) ($state['allocation_limit'] ?? 0),
            'oom_killer' => false,
            'skip_scripts' => (bool) ($state['skip_scripts'] ?? false),
            'start_on_completion' => false,
            'image' => JavaRuntime::requireImage($state, $egg),
            'startup' => $startup !== '' ? $startup : collect($egg->startup_commands)->first(),
        ];
    }

    private function buildOtherPayload(array $state): array
    {
        $egg = OtherGameCatalog::selected($state['game_egg_id'] ?? null);
        if (!$egg) {
            throw ValidationException::withMessages(['data.game_egg_id' => 'Choose an approved game.']);
        }
        $name = trim((string) ($state['name'] ?? ''));
        $ram = ($state['ram_gb'] ?? '') === 'custom' ? (int) ($state['ram_custom_gb'] ?? 0) : (int) ($state['ram_gb'] ?? 0);
        $cpu = (int) ($state['cpu'] ?? 100);
        $disk = (int) ($state['disk_mb'] ?? 10240);
        if ($name === '' || mb_strlen($name) > 255 || $ram < 1 || $ram > 128 || $cpu < 0 || $cpu > 1600 || $disk < 1024) {
            throw ValidationException::withMessages(['data.name' => 'Enter a name and valid resource limits.']);
        }
        $nodeId = (int) ($state['node_id'] ?? 0);
        if (!CreatorAccess::nodes()->whereKey($nodeId)->exists()) {
            throw ValidationException::withMessages(['data.node_id' => 'You do not have access to this node.']);
        }
        $allocation = Allocation::query()->where('node_id', $nodeId)->whereNull('server_id')->find((int) ($state['allocation_id'] ?? 0));
        if (!$allocation) {
            throw ValidationException::withMessages(['data.allocation_id' => 'This port is no longer available.']);
        }
        $env = OtherGameCatalog::environment($egg, $state['game_variables'] ?? []);
        if (CreatorAccess::admin()) {
            foreach (($state['environment_overrides'] ?? []) as $key => $value) {
                if (!array_key_exists($key, $env)) {
                    throw ValidationException::withMessages(['data.environment_overrides' => 'Unknown egg variable.']);
                }
                $env[$key] = (string) $value;
            }
        }
        return ['name' => $name, 'owner_id' => user()->id, 'node_id' => $nodeId,
            'allocation_id' => $allocation->id, 'egg_id' => $egg->id, 'environment' => $env,
            'memory' => $ram * 1024, 'cpu' => $cpu, 'disk' => $disk, 'io' => 500,
            'swap' => (int) ($state['swap'] ?? 0), 'database_limit' => (int) ($state['database_limit'] ?? 0),
            'allocation_limit' => (int) ($state['allocation_limit'] ?? 0), 'backup_limit' => (int) ($state['backup_limit'] ?? 3),
            'oom_killer' => false, 'skip_scripts' => (bool) ($state['skip_scripts'] ?? false),
            'start_on_completion' => false,
            'image' => CreatorAccess::admin() && !empty($state['image']) ? $state['image'] : OtherGameCatalog::image($egg),
            'startup' => CreatorAccess::admin() && !empty($state['startup']) ? $state['startup'] : collect($egg->startup_commands)->first()];
    }

}

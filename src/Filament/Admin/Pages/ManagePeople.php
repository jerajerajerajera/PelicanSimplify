<?php
declare(strict_types=1);
namespace Simplify\Panel\Filament\Admin\Pages;

use App\Models\Role;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Illuminate\Validation\ValidationException;
use Simplify\Panel\Services\PeopleManager;
use Simplify\Panel\Services\RoleAccess;

/** People and roles in one place (root admins, "Let Simplify manage people & roles" on). */
final class ManagePeople extends Page
{
    protected static ?string $title = 'People & roles';
    protected static ?string $slug = 'simplify-people';
    protected string $view = 'pelican-simplify::admin-people';
    protected static bool $shouldRegisterNavigation = false;

    public string $tab = 'people';
    public string $search = '';
    public int $peoplePage = 1;
    private const PER_PAGE = 25;

    public static function canAccess(): bool
    {
        return PeopleManager::enabled() && (bool) user()?->isRootAdmin();
    }

    public function mount(): void
    {
        $this->tab = request()->query('tab') === 'roles' ? 'roles' : 'people';
    }

    public function updatedSearch(): void { $this->peoplePage = 1; }

    /** One page of people for the view. */
    public function people(): array
    {
        $query = User::query()->with('roles:id,name')->withCount('servers')->orderBy('username');
        $term = trim($this->search);
        if ($term !== '') $query->where(fn ($q) => $q->where('username', 'like', '%'.addcslashes($term, '%_\\').'%')->orWhere('email', 'like', '%'.addcslashes($term, '%_\\').'%'));
        $total = (clone $query)->count();
        $this->peoplePage = max(1, min($this->peoplePage, (int) ceil(max(1, $total) / self::PER_PAGE)));
        $rows = $query->forPage($this->peoplePage, self::PER_PAGE)->get()->map(fn (User $user) => [
            'id' => $user->id,
            'username' => $user->username,
            'email' => $user->email,
            'roles' => $user->roles->pluck('name')->all(),
            'root' => $user->isRootAdmin(),
            'servers' => $user->servers_count,
            'mfa' => filled($user->mfa_app_secret) || (bool) $user->mfa_email_enabled,
            'self' => (int) $user->id === (int) user()->id,
        ])->all();
        return ['rows' => $rows, 'total' => $total, 'pages' => (int) ceil(max(1, $total) / self::PER_PAGE)];
    }

    public function roles(): array
    {
        $profiles = collect(RoleAccess::all()['roles'])->keyBy('role_id');
        return Role::query()->withCount(['users', 'permissions'])->orderByRaw('name = ? desc', [Role::ROOT_ADMIN])->orderBy('name')->get()->map(fn (Role $role) => [
            'id' => $role->id,
            'name' => $role->name,
            'root' => $role->isRootAdmin(),
            'members' => $role->users_count,
            'permissions' => $role->permissions_count,
            'abilities' => count($profiles->get($role->id)['abilities'] ?? []),
        ])->all();
    }

    public function setPage(int $page): void { $this->peoplePage = max(1, $page); }

    private function userFields(): array
    {
        return [
            TextInput::make('username')->label(__('pelican-simplify::people.form.username'))->required()->maxLength(255)->regex('/^[A-Za-z0-9_.-]+$/'),
            TextInput::make('email')->label(__('pelican-simplify::people.form.email'))->email()->required()->maxLength(255),
            CheckboxList::make('roles')->label(__('pelican-simplify::people.form.roles'))->columns(2)
                ->options(fn () => Role::query()->orderBy('name')->pluck('name', 'id')->all())
                ->helperText(__('pelican-simplify::people.form.root_admin_gives_full_access_to')),
        ];
    }

    /** Turns validation messages from the service into form errors. */
    private function guarded(callable $run, string $done): void
    {
        try {
            $run();
            Notification::make()->title($done)->success()->send();
        } catch (ValidationException $exception) {
            Notification::make()->title(__('pelican-simplify::people.form.not_saved'))->body(collect($exception->errors())->flatten()->first())->danger()->send();
            throw $exception;
        }
    }

    public function addPersonAction(): Action
    {
        return Action::make('addPerson')->label(__('pelican-simplify::people.form.add_person'))->icon('tabler-user-plus')->modalHeading(__('pelican-simplify::people.form.add_a_person'))
            ->modalDescription(__('pelican-simplify::people.form.they_get_an_email_with_a'))
            ->schema($this->userFields())->modalSubmitActionLabel(__('pelican-simplify::people.form.add_and_send_invite'))
            ->action(fn (array $data) => $this->guarded(fn () => PeopleManager::createUser($data), 'Person added; invite sent'));
    }

    public function editPersonAction(): Action
    {
        return Action::make('editPerson')->modalHeading(__('pelican-simplify::people.form.edit_person'))->modalSubmitActionLabel(__('pelican-simplify::people.form.save'))->schema($this->userFields())
            ->fillForm(function (array $arguments): array {
                $user = User::query()->findOrFail((int) ($arguments['user'] ?? 0));
                return ['username' => $user->username, 'email' => $user->email, 'roles' => $user->roles()->pluck('id')->all()];
            })
            ->action(fn (array $data, array $arguments) => $this->guarded(fn () => PeopleManager::updateUser(User::query()->findOrFail((int) $arguments['user']), $data), 'Person saved'));
    }

    public function resetPasswordAction(): Action
    {
        return Action::make('resetPassword')->requiresConfirmation()->modalHeading(__('pelican-simplify::people.form.send_a_password_reset_link'))
            ->modalDescription(__('pelican-simplify::people.form.they_get_an_email_with_a_2'))
            ->modalSubmitActionLabel(__('pelican-simplify::people.form.send_link'))
            ->action(function (array $arguments): void {
                $status = PeopleManager::sendPasswordReset(User::query()->findOrFail((int) $arguments['user']));
                $sent = $status === \Illuminate\Support\Facades\Password::RESET_LINK_SENT;
                Notification::make()->title($sent ? 'Reset link sent' : 'Could not send the link')->body($sent ? null : __($status))->{$sent ? 'success' : 'warning'}()->send();
            });
    }

    public function deletePersonAction(): Action
    {
        return Action::make('deletePerson')->requiresConfirmation()->color('danger')->modalHeading(__('pelican-simplify::people.form.delete_this_person'))
            ->modalDescription(__('pelican-simplify::people.form.their_account_and_sign_in_are'))
            ->modalSubmitActionLabel(__('pelican-simplify::people.form.delete'))
            ->action(fn (array $arguments) => $this->guarded(fn () => PeopleManager::deleteUser(User::query()->findOrFail((int) $arguments['user'])), 'Person deleted'));
    }

    private function roleFields(): array
    {
        $groups = [];
        foreach (PeopleManager::permissionGroups() as $model => $options) {
            $groups[] = CheckboxList::make('permissions_'.$model)->label(ucfirst(trim(preg_replace('/(?<!^)[A-Z]/', ' $0', $model) ?? $model)))->options($options)->columns(3)->bulkToggleable();
        }
        $limits = array_map(fn (string $key, array $limit) => TextInput::make('simplify.'.$key)->label(str_replace(['Max ', ' mb'], ['Max ', ' (MB)'], ucfirst(str_replace('_', ' ', $key))))->integer()->minValue($limit[1])->maxValue($limit[2])->required(), array_keys(RoleAccess::LIMITS), RoleAccess::LIMITS);
        return [
            TextInput::make('name')->label(__('pelican-simplify::people.form.role_name'))->required()->maxLength(64),
            Section::make('Simplify permissions')->description(__('pelican-simplify::people.form.what_members_can_do_on_their'))->schema([
                CheckboxList::make('simplify.abilities')->hiddenLabel()->columns(2)
                    ->options(array_map(fn (array $ability) => $ability[0], \Simplify\Panel\Services\Translations::rows('settings.abilities', RoleAccess::ABILITIES)))
                    ->descriptions(array_map(fn (array $ability) => $ability[1], \Simplify\Panel\Services\Translations::rows('settings.abilities', RoleAccess::ABILITIES))),
                \Filament\Schemas\Components\Grid::make(3)->schema($limits),
            ]),
            Section::make('Admin panel access')->description(__('pelican-simplify::people.form.which_parts_of_the_admin_panel'))
                ->collapsible()->collapsed()->schema($groups),
        ];
    }

    private function roleData(array $data): array
    {
        $permissions = [];
        foreach ($data as $key => $value) if (str_starts_with((string) $key, 'permissions_') && is_array($value)) array_push($permissions, ...$value);
        return [(string) ($data['name'] ?? ''), $permissions, (array) ($data['simplify'] ?? [])];
    }

    public function addRoleAction(): Action
    {
        return Action::make('addRole')->label(__('pelican-simplify::people.form.add_role'))->icon('tabler-shield-plus')->modalHeading(__('pelican-simplify::people.form.add_a_role'))->modalSubmitActionLabel(__('pelican-simplify::people.form.add_role'))->slideOver()
            ->schema($this->roleFields())
            ->fillForm(fn (): array => ['simplify' => ['abilities' => [], ...RoleAccess::limitDefaults()]])
            ->action(fn (array $data) => $this->guarded(fn () => PeopleManager::saveRole(null, ...$this->roleData($data)), 'Role added'));
    }

    public function editRoleAction(): Action
    {
        return Action::make('editRole')->modalHeading(__('pelican-simplify::people.form.edit_role'))->modalSubmitActionLabel(__('pelican-simplify::people.form.save_role'))->slideOver()->schema($this->roleFields())
            ->fillForm(function (array $arguments): array {
                $role = Role::query()->findOrFail((int) ($arguments['role'] ?? 0));
                abort_if($role->isRootAdmin(), 403);
                $data = ['name' => $role->name, 'simplify' => PeopleManager::profile((int) $role->id)];
                $owned = $role->permissions()->pluck('name')->all();
                foreach (PeopleManager::permissionGroups() as $model => $options) $data['permissions_'.$model] = array_values(array_intersect(array_keys($options), $owned));
                return $data;
            })
            ->action(fn (array $data, array $arguments) => $this->guarded(fn () => PeopleManager::saveRole(Role::query()->findOrFail((int) $arguments['role']), ...$this->roleData($data)), 'Role saved'));
    }

    public function deleteRoleAction(): Action
    {
        return Action::make('deleteRole')->requiresConfirmation()->color('danger')->modalHeading(__('pelican-simplify::people.form.delete_this_role'))
            ->modalDescription(__('pelican-simplify::people.form.members_keep_their_account_but_lose'))
            ->modalSubmitActionLabel(__('pelican-simplify::people.form.delete_role'))
            ->action(fn (array $arguments) => $this->guarded(fn () => PeopleManager::deleteRole(Role::query()->findOrFail((int) $arguments['role'])), 'Role deleted'));
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}

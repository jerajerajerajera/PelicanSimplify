<?php

declare(strict_types=1);

namespace Simplify\Panel\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * People and roles when "Let Simplify manage people & roles" is on. Built on Pelican's own users and roles
 * (so admin permissions and other plugins keep working); Simplify's abilities are layered on top per role.
 *
 * Security rules, enforced here and not only in the page:
 * - only root admins manage people and roles;
 * - nobody removes their own root access, and the last root admin keeps it and cannot be deleted;
 * - passwords of other people are never set or shown: new people and resets get an email link;
 * - only permissions Pelican knows are stored; the Root Admin role is locked.
 */
final class PeopleManager
{
    public static function enabled(): bool
    {
        return FeatureSettings::enabled('people_manager');
    }

    public static function authorize(): void
    {
        abort_unless((bool) user()?->isRootAdmin(), 403);
    }

    /** Every Pelican admin permission as "prefix model" => label, grouped per model. */
    public static function permissionGroups(): array
    {
        $groups = [];
        foreach (Role::getPermissionList() as $model => $prefixes) {
            foreach ($prefixes as $prefix) $groups[$model][$prefix.' '.$model] = ucfirst(strtolower(preg_replace('/(?<!^)[A-Z]/', ' $0', $prefix) ?? $prefix));
        }
        return $groups;
    }

    /** @param array{username: string, email: string, roles?: array<int>} $data */
    public static function createUser(array $data): User
    {
        self::authorize();
        $data = self::validateUser($data, null);
        return DB::transaction(function () use ($data): User {
            // No password: Pelican generates a random one and emails a link to choose their own.
            $user = app(\App\Services\Users\UserCreationService::class)->handle(['username' => $data['username'], 'email' => $data['email']]);
            self::syncRoles($user, $data['roles']);
            return $user;
        });
    }

    /** @param array{username: string, email: string, roles?: array<int>} $data */
    public static function updateUser(User $target, array $data): User
    {
        self::authorize();
        $data = self::validateUser($data, $target);
        return DB::transaction(function () use ($target, $data): User {
            app(\App\Services\Users\UserUpdateService::class)->handle($target, ['username' => $data['username'], 'email' => $data['email']]);
            self::syncRoles($target, $data['roles']);
            return $target->refresh();
        });
    }

    public static function deleteUser(User $target): void
    {
        self::authorize();
        $actor = user();
        if ((int) $target->id === (int) $actor->id) throw ValidationException::withMessages(['user' => __('pelican-simplify::people.errors.you_cannot_delete_your_own_account')]);
        if ($target->isRootAdmin() && self::rootAdminCount() <= 1) throw ValidationException::withMessages(['user' => __('pelican-simplify::people.errors.the_last_root_admin_cannot_be')]);
        if ($target->servers()->exists()) throw ValidationException::withMessages(['user' => __('pelican-simplify::people.errors.this_person_still_owns_servers_move')]);
        $target->delete();
    }

    /** Emails a reset link; the password itself is never handled here. */
    public static function sendPasswordReset(User $target): string
    {
        self::authorize();
        $broker = Password::broker(\Filament\Facades\Filament::getPanel('app')->getAuthPasswordBroker());
        return $broker->sendResetLink(['email' => $target->email]);
    }

    /**
     * @param array<string> $permissions Pelican admin permissions ("update server")
     * @param array{abilities?: array<string>}|array<string, mixed> $simplify Simplify abilities and limits
     */
    public static function saveRole(?Role $role, string $name, array $permissions, array $simplify): Role
    {
        self::authorize();
        $name = trim($name);
        if ($role?->isRootAdmin() || strcasecmp($name, Role::ROOT_ADMIN) === 0) throw ValidationException::withMessages(['name' => __('pelican-simplify::people.errors.the_root_admin_role_is_managed')]);
        validator(['name' => $name], ['name' => ['required', 'string', 'max:64', Rule::unique('roles', 'name')->ignore($role?->id)]])->validate();
        $known = array_merge(...array_values(array_map('array_keys', self::permissionGroups()) ?: [[]]));
        $permissions = array_values(array_intersect(array_unique(array_map('strval', $permissions)), $known));
        return DB::transaction(function () use ($role, $name, $permissions, $simplify): Role {
            $role ??= new Role(['guard_name' => Role::DEFAULT_GUARD_NAME]);
            $role->name = $name;
            $role->guard_name = Role::DEFAULT_GUARD_NAME;
            $role->save();
            $models = array_map(fn (string $permission) => \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $permission, 'guard_name' => Role::DEFAULT_GUARD_NAME]), $permissions);
            $role->syncPermissions($models);
            self::saveProfile((int) $role->id, $simplify);
            return $role;
        });
    }

    public static function deleteRole(Role $role): void
    {
        self::authorize();
        if ($role->isRootAdmin()) throw ValidationException::withMessages(['role' => __('pelican-simplify::people.errors.the_root_admin_role_cannot_be')]);
        DB::transaction(function () use ($role): void {
            $id = (int) $role->id;
            $role->delete();
            $all = RoleAccess::all();
            $all['roles'] = array_values(array_filter($all['roles'], fn (array $profile) => (int) $profile['role_id'] !== $id));
            RoleAccess::save($all);
        });
    }

    /** Simplify's abilities and limits for one role, stored next to Everyone in RoleAccess. */
    public static function profile(int $roleId): array
    {
        foreach (RoleAccess::all()['roles'] as $profile) if ((int) $profile['role_id'] === $roleId) return $profile;
        return ['role_id' => $roleId, 'abilities' => [], ...RoleAccess::limitDefaults()];
    }

    private static function saveProfile(int $roleId, array $simplify): void
    {
        $all = RoleAccess::all();
        $roles = array_values(array_filter($all['roles'], fn (array $profile) => (int) $profile['role_id'] !== $roleId));
        $roles[] = ['role_id' => $roleId, ...$simplify, 'abilities' => array_values(array_intersect((array) ($simplify['abilities'] ?? []), array_keys(RoleAccess::ABILITIES)))];
        $all['roles'] = $roles;
        RoleAccess::save($all);
    }

    private static function validateUser(array $data, ?User $target): array
    {
        $valid = validator($data, [
            'username' => ['required', 'string', 'between:1,255', 'regex:/^[A-Za-z0-9_.-]+$/', Rule::unique('users', 'username')->ignore($target?->id)],
            'email' => ['required', 'email', 'between:1,255', Rule::unique('users', 'email')->ignore($target?->id)],
            'roles' => ['array'],
            'roles.*' => ['integer', Rule::exists('roles', 'id')],
        ], ['username.regex' => __('pelican-simplify::people.errors.use_letters_numbers_dots_dashes_and')])->validate();
        $valid['roles'] = array_values(array_unique(array_map('intval', $valid['roles'] ?? [])));
        return $valid;
    }

    /** Role changes, with the root-admin safety rules. */
    private static function syncRoles(User $target, array $roleIds): void
    {
        $root = Role::getRootAdmin();
        $wasRoot = $target->isRootAdmin();
        $staysRoot = in_array((int) $root->id, $roleIds, true);
        if ($wasRoot && !$staysRoot) {
            if ((int) $target->id === (int) user()->id) throw ValidationException::withMessages(['roles' => __('pelican-simplify::people.errors.you_cannot_remove_your_own_root')]);
            if (self::rootAdminCount() <= 1) throw ValidationException::withMessages(['roles' => __('pelican-simplify::people.errors.the_last_root_admin_keeps_root')]);
        }
        $target->syncRoles(Role::query()->whereIn('id', $roleIds)->get());
    }

    private static function rootAdminCount(): int
    {
        return (int) Role::getRootAdmin()->users()->count();
    }
}

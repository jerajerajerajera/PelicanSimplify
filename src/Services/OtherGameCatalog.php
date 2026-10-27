<?php

declare(strict_types=1);

namespace Simplify\Panel\Services;

use App\Models\Egg;
use Illuminate\Validation\ValidationException;

final class OtherGameCatalog
{
    public static function options(): array
    {
        return CreatorAccess::eggs()->pluck('name', 'id')->all();
    }

    public static function selected(mixed $id): ?Egg
    {
        return CreatorAccess::eggs()->find((int) $id);
    }

    public static function environment(Egg $egg, array $values): array
    {
        $result = [];
        $editable = [];
        foreach ($egg->variables as $variable) {
            $name = (string) $variable->env_variable;
            $editable[$name] = CreatorAccess::admin() || ($variable->user_editable && $variable->user_viewable);
            $value = $editable[$name] && array_key_exists($name, $values) ? $values[$name] : ($variable->default_value ?? '');
            if (!is_scalar($value) && $value !== null) {
                throw ValidationException::withMessages(['data.game_variables.' . $name => __('pelican-simplify::steam.errors.enter_a_valid_value')]);
            }
            $result[$name] = (string) ($value ?? '');
        }
        foreach ($values as $name => $value) {
            if (!array_key_exists($name, $editable) || !$editable[$name]) {
                throw ValidationException::withMessages(['data.game_variables' => __('pelican-simplify::steam.errors.this_game_variable_cannot_be_changed')]);
            }
        }
        return $result;
    }

    public static function image(Egg $egg): string
    {
        $image = (string) (collect($egg->docker_images)->first() ?? '');
        if ($image === '') {
            throw ValidationException::withMessages(['data.game_egg_id' => __('pelican-simplify::steam.errors.this_egg_has_no_docker_image')]);
        }
        return $image;
    }

    /** Install and engine variables most people never change; they go under "Advanced". */
    private const ADVANCED = '/^(SRCDS_|STEAM_|AUTO_?UPDATE|INSTALL_|WINDOWS_INSTALL|VALIDATE|BRANCH|BETA|.*_PORT$|.*_BRANCH$|LD_LIBRARY|WINE|CONSOLE_FILTER)/i';

    /** "Create with Steam" next to a Game Server Login Token field, using the person's connected Steam key. */
    private static function steamTokenAction(\Filament\Forms\Components\TextInput $field, string $path, string $gameName): void
    {
        $field->password()->revealable()->suffixAction(
            \Filament\Actions\Action::make('steam_token_'.md5($path))->icon('tabler-brand-steam')->tooltip(__('pelican-simplify::steam.token.create_tooltip'))
                ->visible(fn () => SteamAccount::connected(user()))
                ->action(function (\Filament\Schemas\Components\Utilities\Set $set, $livewire) use ($path, $gameName): void {
                    try {
                        $memo = trim((string) ($livewire->data['name'] ?? '')) ?: $gameName;
                        $set($path, SteamAccount::createTokenForGame(user(), $gameName, $memo));
                        \Filament\Notifications\Notification::make()->title(__('pelican-simplify::steam.token.created'))->body(__('pelican-simplify::steam.token.created_body', ['memo' => mb_substr($memo, 0, 32)]))->success()->send();
                    } catch (\Illuminate\Validation\ValidationException $exception) {
                        \Filament\Notifications\Notification::make()->title(__('pelican-simplify::steam.token.not_created'))->body(collect($exception->errors())->flatten()->first())->danger()->send();
                    }
                })
        )->hint(fn () => !SteamAccount::connected(user()) ? __('pelican-simplify::steam.token.connect_hint')
            : (SteamAccount::autoTokens(user()) ? __('pelican-simplify::steam.token.auto_hint') : null));
    }

    /** Starting values for the game settings form (toggles as true/false). */
    public static function defaults(Egg $egg): array
    {
        $values = [];
        foreach ($egg->variables as $variable) {
            if (!CreatorAccess::admin() && !($variable->user_editable && $variable->user_viewable)) continue;
            $rules = is_array($variable->rules) ? $variable->rules : explode('|', (string) $variable->rules);
            $default = (string) ($variable->default_value ?? '');
            $values[$variable->env_variable] = in_array('boolean', $rules, true) ? in_array(strtolower($default), ['1', 'true'], true) : $default;
        }
        return $values;
    }

    /**
     * Form fields for an egg's variables, typed from their validation rules: a fixed list becomes a select,
     * true/false a toggle, numbers a number field. Returns [main fields, advanced fields].
     */
    public static function fields(Egg $egg): array
    {
        $main = [];
        $advanced = [];
        foreach ($egg->variables as $variable) {
            if (!CreatorAccess::admin() && !($variable->user_editable && $variable->user_viewable)) continue;
            $rules = is_array($variable->rules) ? $variable->rules : explode('|', (string) $variable->rules);
            $rules = array_values(array_filter(array_map('trim', $rules)));
            // An empty token field is filled at creation time when the person has automatic Steam tokens on.
            $autoToken = in_array(strtoupper((string) $variable->env_variable), SteamAccount::TOKEN_VARIABLES, true) && SteamAccount::autoTokens(user());
            if ($autoToken) $rules = array_values(array_diff($rules, ['required']));
            $in = null;
            foreach ($rules as $rule) if (str_starts_with($rule, 'in:')) $in = array_map('trim', explode(',', substr($rule, 3)));
            $path = 'game_variables.' . $variable->env_variable;
            $default = (string) ($variable->default_value ?? '');
            $boolean = in_array('boolean', $rules, true) || ($in !== null && count($in) === 2 && !array_diff(array_map('strtolower', $in), ['0', '1', 'true', 'false']));
            if ($boolean) {
                // Keep the egg's own spelling (0/1 or true/false) when saving the toggle.
                [$off, $on] = $in === null ? ['0', '1'] : (in_array(strtolower($in[0]), ['1', 'true'], true) ? [$in[1], $in[0]] : [$in[0], $in[1]]);
                $field = \Filament\Forms\Components\Toggle::make($path)->default(in_array(strtolower($default), ['1', 'true'], true))
                    ->formatStateUsing(fn ($state) => is_bool($state) ? $state : in_array(strtolower((string) $state), ['1', 'true'], true))
                    ->dehydrateStateUsing(fn ($state) => $state ? $on : $off);
            } elseif ($in !== null) {
                $field = \Filament\Forms\Components\Select::make($path)->options(array_combine($in, $in))->default($default)->native(false)->rules($rules);
            } else {
                $field = \Filament\Forms\Components\TextInput::make($path)->default($default)->rules($rules ?: ['nullable']);
                if (array_intersect($rules, ['numeric', 'integer'])) $field->numeric();
                if (preg_match('/PASS(WORD)?|SECRET|TOKEN/i', (string) $variable->env_variable)) $field->password()->revealable();
                if (in_array(strtoupper((string) $variable->env_variable), SteamAccount::TOKEN_VARIABLES, true)) self::steamTokenAction($field, $path, (string) $egg->name);
            }
            $field->label(trim(preg_replace('/^\[[^\]]*\]\s*/', '', (string) $variable->name)))->helperText($variable->description ?: null);
            if (in_array('required', $rules, true) && !$boolean) $field->required();
            preg_match(self::ADVANCED, (string) $variable->env_variable) ? $advanced[] = $field : $main[] = $field;
        }
        return [$main, $advanced];
    }
}

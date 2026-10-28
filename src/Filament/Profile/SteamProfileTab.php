<?php

declare(strict_types=1);

namespace Simplify\Panel\Filament\Profile;

use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Validation\ValidationException;
use Simplify\Panel\Services\SteamAccount;

/**
 * "Steam" tab on the profile page. Linking uses Pelican's own Steam sign-in (OAuth); creating game server
 * login tokens needs the account's Web API key, because Steam offers no permission flow for that.
 */
final class SteamProfileTab
{
    private static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered || !class_exists(\App\Filament\Pages\Auth\EditProfile::class) || !enum_exists(\App\Enums\TabPosition::class)) return;
        self::$registered = true;
        \App\Filament\Pages\Auth\EditProfile::registerCustomTabs(\App\Enums\TabPosition::After, self::make());
    }

    public static function make(): Tab
    {
        return Tab::make('steam')->label(__('pelican-simplify::steam.profile.steam'))->icon('tabler-brand-steam')->schema(fn (): array => self::schema());
    }

    private static function steamSignInEnabled(): bool
    {
        try {
            foreach (app(\App\Extensions\OAuth\OAuthService::class)->getEnabled() as $schema) if ($schema->getId() === 'steam') return true;
        } catch (\Throwable) {
        }
        return false;
    }

    private static function schema(): array
    {
        $user = user();
        if ($user === null) return [];
        $status = SteamAccount::status($user);
        $linked = $status['linked'];
        $linkText = $linked !== null
            ? __('pelican-simplify::steam.profile.linked_to', ['account' => $linked])
            : (self::steamSignInEnabled() ? __('pelican-simplify::steam.profile.not_linked') : __('pelican-simplify::steam.profile.sign_in_off'));
        $keyText = $status['connected']
            ? ($status['steamid'] ? __('pelican-simplify::steam.profile.key_ending_account', ['hint' => $status['hint'], 'account' => $status['steamid']]) : __('pelican-simplify::steam.profile.key_ending', ['hint' => $status['hint']])).' '.($status['auto'] ? __('pelican-simplify::steam.profile.auto_on') : __('pelican-simplify::steam.profile.auto_off'))
            : __('pelican-simplify::steam.profile.no_key');

        return [
            Section::make(__('pelican-simplify::steam.profile.steam_account'))->description(__('pelican-simplify::steam.profile.linking_proves_which_steam_account_is'))->schema([
                \Filament\Schemas\Components\Text::make($linkText),
            ]),
            Section::make(__('pelican-simplify::steam.profile.login_tokens'))
                ->description(__('pelican-simplify::steam.profile.games_such_as_cs2_unturned_and'))
                ->schema([
                    \Filament\Schemas\Components\Text::make($keyText),
                    Actions::make([
                        Action::make('simplify_steam_connect')->button()->label($status['connected'] ? __('pelican-simplify::steam.profile.replace_key') : __('pelican-simplify::steam.profile.connect_key'))->icon('tabler-key')
                            ->modalHeading(__('pelican-simplify::steam.profile.connect_your_steam_web_api_key'))
                            ->modalDescription(__('pelican-simplify::steam.profile.create_or_copy_your_key_at'))
                            ->schema([TextInput::make('key')->label(__('pelican-simplify::steam.profile.steam_web_api_key'))->password()->required()->maxLength(32)->autocomplete(false)])
                            ->modalSubmitActionLabel(__('pelican-simplify::steam.profile.connect'))
                            ->action(function (array $data, Action $action): void {
                                try {
                                    SteamAccount::save(user(), (string) $data['key']);
                                    Notification::make()->title(__('pelican-simplify::steam.profile.steam_key_connected'))->success()->send();
                                } catch (ValidationException $exception) {
                                    Notification::make()->title(__('pelican-simplify::steam.profile.key_not_connected'))->body(collect($exception->errors())->flatten()->first())->danger()->send();
                                    $action->halt();
                                }
                            }),
                        Action::make('simplify_steam_auto')->button()->label($status['auto'] ? __('pelican-simplify::steam.profile.turn_auto_off') : __('pelican-simplify::steam.profile.turn_auto_on'))->icon('tabler-wand')
                            ->visible($status['connected'])
                            ->tooltip(__('pelican-simplify::steam.profile.automatic_a_new_server_for_a'))
                            ->action(function () use ($status): void {
                                SteamAccount::setAuto(user(), !$status['auto']);
                                Notification::make()->title($status['auto'] ? __('pelican-simplify::steam.profile.auto_now_off') : __('pelican-simplify::steam.profile.auto_now_on'))->success()->send();
                            }),
                        Action::make('simplify_steam_disconnect')->button()->label(__('pelican-simplify::steam.profile.disconnect'))->icon('tabler-unlink')->color('danger')
                            ->visible($status['connected'])->requiresConfirmation()
                            ->modalDescription(__('pelican-simplify::steam.profile.simplify_forgets_your_key_tokens_already'))
                            ->action(function (): void {
                                SteamAccount::remove(user());
                                Notification::make()->title(__('pelican-simplify::steam.profile.steam_key_disconnected'))->success()->send();
                            }),
                    ]),
                ]),
        ];
    }
}

<?php

declare(strict_types=1);

namespace Simplify\Panel\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * A person's own Steam Web API key, used to create Game Server Login Tokens (GSLT) for games that need
 * one (CS2, Unturned, Insurgency: Sandstorm…). The key is stored encrypted per user, is only ever used
 * for that user's own requests, and is never shown again after saving (only its last four characters).
 */
final class SteamAccount
{
    private const DIR = 'simplify/steam/';
    private const API = 'https://api.steampowered.com/IGameServersService/';

    /** Egg variables that take a Game Server Login Token. */
    public const TOKEN_VARIABLES = ['STEAM_ACC', 'GSLT', 'GSLTT', 'GSLT_TOKEN', 'STEAM_LOGIN_TOKEN', 'TOWER_LOGIN_TOKEN', 'STEAM_TOKEN', 'SRCDS_TOKEN', 'STEAM_GSLT'];

    public static function status(object $user): array
    {
        $data = self::read($user);
        return ['connected' => $data !== null, 'hint' => $data['hint'] ?? null, 'saved_at' => $data['saved_at'] ?? null,
            'steamid' => $data['steamid'] ?? null, 'auto' => (bool) ($data['auto'] ?? true), 'linked' => self::linkedSteamId($user)];
    }

    /** The SteamID linked with Pelican's "Sign in with Steam" (profile → OAuth), if any. */
    public static function linkedSteamId(object $user): ?string
    {
        $id = $user->oauth['steam'] ?? null;
        return is_scalar($id) && preg_match('/^\d{17}$/', (string) $id) ? (string) $id : null;
    }

    /** Create tokens by themselves when a new server needs one and the field was left empty. */
    public static function autoTokens(?object $user): bool
    {
        return $user !== null && (bool) (self::read($user)['auto'] ?? false);
    }

    public static function setAuto(object $user, bool $on): void
    {
        $data = self::read($user);
        if ($data === null) return;
        $data['auto'] = $on;
        Storage::disk('local')->put(self::path($user), json_encode($data, JSON_THROW_ON_ERROR));
    }

    public static function connected(?object $user): bool
    {
        return $user !== null && self::read($user) !== null;
    }

    /** Checks the key with Steam before storing it. */
    public static function save(object $user, string $key): array
    {
        $key = strtoupper(trim($key));
        if (!preg_match('/^[A-F0-9]{32}$/', $key)) throw ValidationException::withMessages(['key' => __('pelican-simplify::steam.errors.a_steam_web_api_key_has')]);
        try {
            $response = Http::timeout(8)->connectTimeout(3)->get(self::API.'GetAccountList/v1/', ['key' => $key]);
        } catch (Throwable) {
            throw ValidationException::withMessages(['key' => __('pelican-simplify::steam.errors.steam_could_not_be_reached_try')]);
        }
        if (in_array($response->status(), [401, 403], true)) throw ValidationException::withMessages(['key' => __('pelican-simplify::steam.errors.steam_did_not_accept_this_key')]);
        if (!$response->successful()) throw ValidationException::withMessages(['key' => __('pelican-simplify::steam.errors.status', ['status' => $response->status()])]);
        // Steam reports whose key this is; with a linked Steam account the two must be the same person.
        $owner = $response->json('response.actor');
        $owner = is_scalar($owner) && preg_match('/^\d{17}$/', (string) $owner) ? (string) $owner : null;
        $linked = self::linkedSteamId($user);
        if ($linked !== null && $owner !== null && $owner !== $linked) throw ValidationException::withMessages(['key' => __('pelican-simplify::steam.errors.this_key_belongs_to_a_different')]);
        $data = ['key' => Crypt::encryptString($key), 'hint' => substr($key, -4), 'saved_at' => now()->toIso8601String(), 'steamid' => $owner, 'auto' => true];
        if (!Storage::disk('local')->put(self::path($user), json_encode($data, JSON_THROW_ON_ERROR))) throw new \RuntimeException('Could not save the Steam key.');
        return self::status($user);
    }

    public static function remove(object $user): void
    {
        Storage::disk('local')->delete(self::path($user));
    }

    /**
     * Creates a GSLT for a game. $appId is the game's own app (CS2: 730), not the dedicated-server app.
     * Steam limits how many tokens an account may hold and requires a phone-verified, non-limited account.
     */
    public static function createToken(object $user, int $appId, string $memo): string
    {
        $data = self::read($user);
        if ($data === null) throw ValidationException::withMessages(['token' => __('pelican-simplify::steam.errors.connect_your_steam_key_first_profile')]);
        try {
            $key = Crypt::decryptString($data['key']);
        } catch (Throwable) {
            self::remove($user);
            throw ValidationException::withMessages(['token' => __('pelican-simplify::steam.errors.your_saved_steam_key_could_not')]);
        }
        try {
            $response = Http::asForm()->timeout(10)->connectTimeout(3)->post(self::API.'CreateAccount/v1/', ['key' => $key, 'appid' => $appId, 'memo' => mb_substr(trim($memo) ?: 'Pelican server', 0, 32)]);
        } catch (Throwable) {
            throw ValidationException::withMessages(['token' => __('pelican-simplify::steam.errors.steam_could_not_be_reached_try')]);
        }
        $token = $response->successful() ? $response->json('response.login_token') : null;
        if (!is_string($token) || !preg_match('/^[A-Za-z0-9]{16,64}$/', $token)) {
            throw ValidationException::withMessages(['token' => in_array($response->status(), [401, 403], true)
                ? __('pelican-simplify::steam.errors.refused')
                : __('pelican-simplify::steam.errors.no_token')]);
        }
        return $token;
    }

    /** Token for a game picked in the creator: the game's Steam app is looked up by name. */
    public static function createTokenForGame(object $user, string $gameName, string $memo): string
    {
        $art = SteamArt::find($gameName);
        if (!$art) throw ValidationException::withMessages(['token' => __('pelican-simplify::steam.errors.this_game_could_not_be_found')]);
        return self::createToken($user, (int) $art['appid'], $memo);
    }

    private static function read(object $user): ?array
    {
        $data = JsonFile::read(self::path($user));
        return is_string($data['key'] ?? null) ? $data : null;
    }

    private static function path(object $user): string
    {
        return self::DIR.((int) $user->id).'.json';
    }
}

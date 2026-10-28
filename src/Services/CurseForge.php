<?php
declare(strict_types=1);
namespace Simplify\Panel\Services;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
/**
 * Optional CurseForge search for plugins and mods. CurseForge's API needs a key per panel (free from
 * console.curseforge.com); a root admin saves one under Simplify settings and may let people add their own.
 * Stored encrypted, sent only to CurseForge.
 */
final class CurseForge
{
    public const FILE = 'simplify/curseforge.json';
    public const API = 'https://api.curseforge.com/v1/';

    public static function hasKey(): bool
    {
        return self::key() !== null;
    }
    /** The signed-in user's own key when the admin allows that (bigger hosts), else the panel's key. */
    public static function key(): ?string
    {
        if (self::allowUserKeys() && ($own = self::decrypted(JsonFile::read(self::userFile())['key'] ?? null))) return $own;
        return self::decrypted(JsonFile::read(self::FILE)['key'] ?? null);
    }
    public static function panelHasKey(): bool
    {
        return self::decrypted(JsonFile::read(self::FILE)['key'] ?? null) !== null;
    }
    public static function allowUserKeys(): bool
    {
        return !empty(JsonFile::read(self::FILE)['user_keys']);
    }
    public static function userHasKey(): bool
    {
        return self::allowUserKeys() && self::decrypted(JsonFile::read(self::userFile())['key'] ?? null) !== null;
    }
    private static function decrypted(mixed $stored): ?string
    {
        if (!is_string($stored) || $stored === '') return null;
        try { return (string) decrypt($stored); } catch (\Throwable) { return null; } // APP_KEY changed: ask for the key again.
    }
    private static function userFile(): string
    {
        return 'simplify/curseforge-users/'.(int) user()?->id.'.json';
    }
    /** Panel settings: an empty $key keeps the saved one, $remove forgets it; $userKeys lets people add their own. */
    public static function save(string $key, bool $remove = false, bool $userKeys = false): void
    {
        abort_unless(user()?->isRootAdmin(), 403);
        $current = JsonFile::read(self::FILE);
        $key = trim($key);
        $data = ['key' => $remove ? null : ($key !== '' ? encrypt($key) : ($current['key'] ?? null)), 'user_keys' => $userKeys];
        if (!Storage::disk('local')->put(self::FILE, json_encode($data, JSON_THROW_ON_ERROR))) throw new \RuntimeException('Could not save the CurseForge key.');
    }
    /** A person's own key, when the admin allows it; an empty key removes it. */
    public static function saveUserKey(string $key): void
    {
        abort_unless(user() && self::allowUserKeys(), 403);
        $key = trim($key);
        abort_if(strlen($key) > 200, 422, 'This is not a CurseForge API key.');
        if ($key !== '' && !self::test($key)['ok']) abort(422, 'CurseForge did not accept this key.');
        $data = $key === '' ? [] : ['key' => encrypt($key)];
        if (!Storage::disk('local')->put(self::userFile(), json_encode($data, JSON_THROW_ON_ERROR))) throw new \RuntimeException('Could not save your CurseForge key.');
    }
    /** Tries the typed key, or the saved one, with one small search. */
    public static function test(string $key = ''): array
    {
        $key = trim($key) ?: (string) self::decrypted(JsonFile::read(self::FILE)['key'] ?? null);
        if ($key === '') return ['ok' => false, 'message' => 'Enter a CurseForge API key first.'];
        try {
            $response = Http::acceptJson()->withHeaders(['x-api-key' => $key])->connectTimeout(3)->timeout(8)->get(self::API.'mods/search', ['gameId' => 432, 'classId' => 5, 'pageSize' => 1]);
        } catch (\Throwable) {
            return ['ok' => false, 'message' => 'CurseForge did not answer. Try again in a moment.'];
        }
        if ($response->status() === 403 || $response->status() === 401) return ['ok' => false, 'message' => 'CurseForge did not accept this key.'];
        return $response->successful() ? ['ok' => true, 'message' => 'Plugin search now includes CurseForge.'] : ['ok' => false, 'message' => 'CurseForge answered with error '.$response->status().'.'];
    }
    /** Headers for requests to CurseForge, or null without a key. */
    public static function headers(): ?array
    {
        $key = self::key();
        return $key === null ? null : ['x-api-key' => $key];
    }
}

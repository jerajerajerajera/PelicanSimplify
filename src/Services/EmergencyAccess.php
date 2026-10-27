<?php
declare(strict_types=1);
namespace Simplify\Panel\Services;
use Illuminate\Support\Facades\Storage;
/**
 * Temporary addresses for the emergency "Explain panel errors" page, made with `php artisan simplify:explain --url`
 * for when signing in to the panel fails. One address at a time: a new one replaces the old. Only a hash is stored,
 * on the local disk (not the database or cache, which may be what is broken).
 */
final class EmergencyAccess
{
    public const FILE = 'simplify/explain-access.json';
    public const MINUTES = 60;

    /** A new token, valid for MINUTES minutes. */
    public static function issue(?int $now = null): string
    {
        $token = bin2hex(random_bytes(20));
        $data = ['hash' => hash('sha256', $token), 'expires' => ($now ?? time()) + self::MINUTES * 60];
        if (!Storage::disk('local')->put(self::FILE, json_encode($data, JSON_THROW_ON_ERROR))) throw new \RuntimeException('Could not save the temporary address.');
        return $token;
    }

    public static function valid(string $token, ?int $now = null): bool
    {
        $data = JsonFile::read(self::FILE);
        if (!is_string($data['hash'] ?? null) || (int) ($data['expires'] ?? 0) < ($now ?? time())) return false;
        return preg_match('/^[0-9a-f]{40}$/', $token) === 1 && hash_equals($data['hash'], hash('sha256', $token));
    }
}

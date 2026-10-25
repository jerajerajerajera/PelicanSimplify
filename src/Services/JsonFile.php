<?php
declare(strict_types=1);
namespace Simplify\Panel\Services;
use Illuminate\Support\Facades\Storage;
/** Small JSON documents on the local disk (settings, per-server metadata). */
final class JsonFile
{
    /** The decoded array, or $default when the file is missing, empty or not a JSON object/array (a damaged file is reported). */
    public static function read(string $path, ?array $default = []): ?array
    {
        // One read instead of exists() + get(): a missing file reads as null.
        $raw = Storage::disk('local')->get($path);
        if ($raw === null || $raw === '') return $default;
        try { $data = json_decode((string) $raw, true, 512, JSON_THROW_ON_ERROR); }
        catch (\Throwable $e) { report($e); return $default; }
        return is_array($data) ? $data : $default;
    }
}

<?php

declare(strict_types=1);

namespace Simplify\Panel\Services;

use Illuminate\Validation\ValidationException;

/** RAM, CPU and disk limits from the server page, checked the same way as in the creator. */
final class ResourceLimits
{
    /**
     * Validated limits in Pelican's units (MB and %). RAM may be any amount of MB, so servers created
     * elsewhere (6000 MB) can be saved unchanged; administrators may use 0 for unlimited, as in the creator.
     */
    public static function from(array $state): array
    {
        $unlimited = CreatorAccess::admin();
        $ram = filter_var($state['memory_gb'] ?? null, FILTER_VALIDATE_FLOAT);
        $memory = $ram === false ? -1 : (int) round($ram * 1024);
        $disk = filter_var($state['disk_mb'] ?? null, FILTER_VALIDATE_INT);
        $cpu = filter_var($state['cpu'] ?? null, FILTER_VALIDATE_INT);
        $allowed = fn (int|false $value, int $max): bool => $value !== false && $value <= $max && ($value >= 1 || ($unlimited && $value === 0));
        if (!$allowed($memory, 131072)) throw ValidationException::withMessages(['data.memory_gb' => 'Enter RAM between 1 and 128 GB.']);
        if (!$allowed($cpu, 1600)) throw ValidationException::withMessages(['data.cpu' => 'Enter a CPU limit between 1 and 1600%.']);
        if (!$allowed($disk, 10485760)) throw ValidationException::withMessages(['data.disk_mb' => 'Enter a valid storage limit in MB.']);
        return ['memory' => $memory, 'cpu' => $cpu, 'disk' => $disk];
    }

    /** The pending-change form of the limits, which the server page reads back into its fields. */
    public static function pending(array $limits): array
    {
        return ['memory_gb' => $limits['memory'] / 1024, 'cpu' => $limits['cpu'], 'disk_mb' => $limits['disk']];
    }
}

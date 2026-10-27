<?php

declare(strict_types=1);

namespace Simplify\Panel\Features;

/**
 * Pelican's own "Accept the EULA" popup, switched off while Simplify's server page is on: Simplify asks for
 * the EULA itself (in the "Your server is ready" step and when a start stops on it), so a player never gets
 * both. Only loaded on Pelican versions that have console features.
 */
final class MinecraftEula extends \App\Extensions\Features\Schemas\MinecraftEulaSchema
{
    public function authorize(\App\Models\User $user, \App\Models\Server $server): bool
    {
        return !\Simplify\Panel\Services\FeatureSettings::enabled('dashboard') && parent::authorize($user, $server);
    }
}

<?php
declare(strict_types=1);
namespace Simplify\Panel\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Simplify\Panel\Services\AppIcon;
use Simplify\Panel\Services\WebApp;

/** Manifest, icons and service worker of the installable web app (see WebApp). */
final class WebAppController
{
    public function manifest(): mixed
    {
        return response()->json(WebApp::manifest(), 200, ['Content-Type' => 'application/manifest+json', 'Cache-Control' => 'public, max-age=3600'], JSON_UNESCAPED_SLASHES);
    }

    public function icon(string $size): mixed
    {
        abort_unless($size === 'maskable' || in_array((int) $size, WebApp::ICON_SIZES, true), 404);
        return $this->png(AppIcon::path($size), 'app-icon-'.($size === 'maskable' ? 512 : (int) $size).'.png');
    }

    public function badge(): mixed
    {
        return $this->png(AppIcon::path('badge'), 'app-badge-96.png');
    }

    /** The panel's own upload when there is one, else Simplify's bundled file. */
    private function png(?string $uploaded, string $bundled): mixed
    {
        $headers = ['Content-Type' => 'image/png', 'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => request()->query('v') ? 'public, max-age=31536000, immutable' : 'public, max-age=3600'];
        if ($uploaded !== null && Storage::disk('local')->exists($uploaded)) return response(Storage::disk('local')->get($uploaded), 200, $headers);
        return response()->file(plugin_path('pelican-simplify', 'resources/branding/'.$bundled), $headers);
    }

    /** Served from the root scope, so the offline page covers every panel page. Never cached by the browser. */
    public function worker(): mixed
    {
        return response(WebApp::worker(), 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Service-Worker-Allowed' => WebApp::scope(),
            'Cache-Control' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}

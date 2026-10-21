<?php
declare(strict_types=1);
namespace Simplify\Panel\Http\Controllers;

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
        $size = (int) $size;
        abort_unless(in_array($size, WebApp::ICON_SIZES, true), 404);
        return response()->file(plugin_path('pelican-simplify', 'resources/branding/app-icon-'.$size.'.png'), [
            'Content-Type' => 'image/png', 'Cache-Control' => request()->query('v') ? 'public, max-age=31536000, immutable' : 'public, max-age=86400',
        ]);
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

<?php
declare(strict_types=1);
namespace Simplify\Panel\Http\Controllers;
use Illuminate\Http\Request;
use Simplify\Panel\Services\WebsiteEmbed;
/**
 * The Website embed add-on for other websites: the server list as JSON, the script that draws it, and a small page
 * for an iframe. Public and read-only, so every response allows any origin and nothing depends on a session.
 */
final class EmbedController
{
    private const HEADERS = ['Access-Control-Allow-Origin' => '*', 'X-Content-Type-Options' => 'nosniff'];

    public function servers(): mixed
    {
        abort_unless(WebsiteEmbed::enabled(), 404);
        return response()->json(WebsiteEmbed::payload(), 200, [...self::HEADERS, 'Cache-Control' => 'public, max-age='.WebsiteEmbed::TTL], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function script(Request $request): mixed
    {
        abort_unless(WebsiteEmbed::enabled(), 404);
        $path = plugin_path('pelican-simplify', 'resources/views/js/embed.js');
        $etag = '"'.substr(hash_file('sha256', $path), 0, 16).'"';
        $headers = [...self::HEADERS, 'Content-Type' => 'application/javascript; charset=utf-8', 'Cache-Control' => 'public, max-age=300', 'ETag' => $etag];
        if (trim((string) $request->header('If-None-Match')) === $etag) return response('', 304, $headers);
        return response((string) file_get_contents($path), 200, $headers);
    }

    /** A page that only holds the list, for websites that prefer an iframe. Query options match the script's data-* attributes. */
    public function frame(Request $request): mixed
    {
        abort_unless(WebsiteEmbed::enabled(), 404);
        $attributes = '';
        $rules = ['server' => WebsiteEmbed::ID, 'game' => WebsiteEmbed::GAME, 'servers' => '/^[A-Za-z0-9]{1,16}(,[A-Za-z0-9]{1,16}){0,99}$/'];
        foreach (['style', 'accent', 'corners', 'players', 'modpack', 'join', 'server', 'game', 'servers'] as $key) {
            $value = $request->query($key);
            if (is_string($value) && preg_match($rules[$key] ?? '/^[#A-Za-z0-9_-]{1,40}$/', $value)) $attributes .= ' data-'.$key.'="'.e($value).'"';
        }
        // ?page=1 is the list as a page of its own (a status page to link to): some room around it and a background that follows the style.
        $page = $request->query('page') === '1';
        $dark = 'html{background:#0b0b0e}';
        $style = $page ? match ($request->query('style')) { 'dark' => $dark, 'light' => 'html{background:#fafafa}', default => 'html{background:#fafafa}@media (prefers-color-scheme:dark){'.$dark.'}' }.'body{padding:24px 16px;max-width:1100px;margin:0 auto}'
            : 'html,body{background:transparent}body{padding:2px}';
        $html = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            .'<title>'.e((string) config('app.name', 'Pelican')).' servers</title><style>html,body{margin:0}'.$style.'</style></head>'
            .'<body><div id="simplify-servers"></div><script src="'.e(url('/simplify/embed.js')).'"'.$attributes.' defer></script></body></html>';
        return response($html, 200, ['Content-Type' => 'text/html; charset=utf-8', 'Cache-Control' => 'public, max-age=300',
            'Content-Security-Policy' => 'frame-ancestors *', 'X-Content-Type-Options' => 'nosniff']);
    }
}

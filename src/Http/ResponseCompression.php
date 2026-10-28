<?php
declare(strict_types=1);
namespace Simplify\Panel\Http;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
/**
 * Gzips text responses (pages, Livewire round trips, Simplify's CSS and JS) when the browser accepts it and nothing
 * else did. Many panels sit behind a web server that only compresses HTML, or nothing at all; a Simplify page then
 * sends dashboard.js at 190 KB instead of 50 KB and every Server Maker step at 280 KB instead of 25 KB. A web server
 * or CDN that compresses already leaves these responses alone, because the encoding is set.
 *
 * It runs on RequestHandled, after the middleware stack, because Livewire and Filament add their script tags to the
 * finished HTML at that point too; compressing earlier would hide the page from them.
 */
final class ResponseCompression
{
    private const TYPES = ['text/html', 'application/json', 'text/css', 'application/javascript', 'text/javascript', 'application/manifest+json', 'image/svg+xml', 'text/plain'];
    private const MIN_BYTES = 1400;

    public static function apply(Request $request, mixed $response): void
    {
        try {
            if ($response instanceof Response && (new self())->eligible($request, $response)) (new self())->encode($response);
        } catch (\Throwable) {
            // A response that cannot be compressed is sent as it is.
        }
    }

    private function eligible(Request $request, Response $response): bool
    {
        if ($response instanceof BinaryFileResponse || $response instanceof StreamedResponse) return false;
        if (!function_exists('gzencode') || filter_var(ini_get('zlib.output_compression'), FILTER_VALIDATE_BOOLEAN)) return false;
        if ($response->headers->has('Content-Encoding') || $request->isMethod('HEAD') || !$response->isSuccessful()) return false;
        if (!preg_match('/(?:^|,)\s*gzip\s*(?:;|,|$)/i', (string) $request->headers->get('Accept-Encoding', ''))) return false;
        $type = strtolower(trim(explode(';', (string) $response->headers->get('Content-Type', ''))[0]));
        if (!in_array($type, self::TYPES, true)) return false;
        $content = $response->getContent();
        return is_string($content) && strlen($content) >= self::MIN_BYTES;
    }

    private function encode(Response $response): void
    {
        $encoded = gzencode((string) $response->getContent(), 6);
        if (!is_string($encoded) || strlen($encoded) >= strlen((string) $response->getContent())) return;
        $response->setContent($encoded);
        $response->headers->set('Content-Encoding', 'gzip');
        $response->headers->set('Content-Length', (string) strlen($encoded));
        $vary = array_filter(array_map('trim', explode(',', (string) $response->headers->get('Vary', ''))));
        if (!in_array('Accept-Encoding', $vary, true) && !in_array('*', $vary, true)) $vary[] = 'Accept-Encoding';
        $response->headers->set('Vary', implode(', ', $vary));
    }
}

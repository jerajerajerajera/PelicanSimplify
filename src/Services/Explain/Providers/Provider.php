<?php
declare(strict_types=1);
namespace Simplify\Panel\Services\Explain\Providers;
/**
 * One AI service that can check a log for "Explain this error": how to ask it, how to read its answer and how to
 * put its errors in plain words. Every request is made with the admin's own key, only to the service's own API host.
 */
abstract class Provider
{
    /** Short company name used in messages, such as "Anthropic". */
    abstract public static function name(): string;
    /** Choice in Simplify settings, such as "Claude by Anthropic". */
    abstract public static function label(): string;
    /** model id => label, the default first. */
    abstract public static function models(): array;
    /** Placeholder for the key field. */
    abstract public static function keyPlaceholder(): string;
    /** Where to create a key and what it costs, under the key field. */
    abstract public static function keyHelp(): string;
    /** ['url' => ..., 'headers' => [...], 'body' => [...]] asking for an answer that follows $schema. */
    abstract public static function request(string $key, string $model, string $system, array $schema, string $question): array;
    /** A tiny request that proves the key and model work. */
    abstract public static function testRequest(string $key, string $model): array;
    /** The JSON text of the answer, or null when the service declined, blocked or cut it off. */
    abstract public static function text(array $body): ?string;
    /** The error type or code from an error response, for failure(). */
    protected static function errorCode(array $body): string
    {
        return strtolower((string) ($body['error']['type'] ?? $body['error']['code'] ?? $body['error']['status'] ?? ''));
    }
    /** A short, plain message for a failed request, never including the key. */
    public static function failure(int $status, array $body): string
    {
        $name = static::name();
        $code = static::errorCode($body);
        $message = strtolower((string) ($body['error']['message'] ?? ''));
        return match (true) {
            $status === 401 || str_contains($code, 'authentication') || str_contains($code, 'invalid_api_key') || str_contains($message, 'api key not valid') => 'The '.$name.' API key is not valid. An administrator can enter a new one in Simplify settings.',
            $status === 402 || str_contains($code, 'insufficient_quota') || str_contains($message, 'credit') || str_contains($message, 'billing') => 'The '.$name.' account behind this key has no credit left. An administrator can add credit or check billing with '.$name.'.',
            $status === 403 || str_contains($code, 'permission') => 'This '.$name.' API key may not use the chosen model. An administrator can pick another model in Simplify settings.',
            $status === 404 => 'The chosen '.$name.' model is not available for this key. An administrator can pick another model in Simplify settings.',
            $status === 429 || str_contains($code, 'rate_limit') || str_contains($code, 'resource_exhausted') => $name.' is busy for this key right now. Try again in a minute.',
            $status === 529 || str_contains($code, 'overloaded') || $status >= 500 => $name.' is temporarily unavailable. Try again in a minute.',
            default => $name.' could not answer this question'.($status ? ' (error '.$status.')' : '').'. Try again later.',
        };
    }
}

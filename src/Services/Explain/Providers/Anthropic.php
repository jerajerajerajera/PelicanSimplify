<?php
declare(strict_types=1);
namespace Simplify\Panel\Services\Explain\Providers;
/** Claude through the Messages API (raw HTTP: a Pelican plugin cannot add the SDK as a composer package). */
final class Anthropic extends Provider
{
    public const ENDPOINT = 'https://api.anthropic.com/v1/messages';
    public static function name(): string { return 'Anthropic'; }
    public static function label(): string { return __('pelican-simplify::explain.providers.anthropic.label'); }
    public static function models(): array
    {
        // @english: model names; ErrorExplainer::models() translates the note in brackets (explain.model_notes).
        return [
            'claude-opus-5-5' => 'Claude Opus 5.5 (best answers, a few cents)',
            'claude-sonnet-5-5' => 'Claude Sonnet 5.5 (about a cent)',
            'claude-haiku-5-5' => 'Claude Haiku 5.5 (cheapest)',
        ];
    }
    public static function keyPlaceholder(): string { return 'sk-ant-…'; }
    public static function keyHelp(): string
    {
        return __('pelican-simplify::explain.providers.anthropic.key_help');
    }
    public static function request(string $key, string $model, string $system, array $schema, string $question): array
    {
        $body = [
            'model' => $model,
            'max_tokens' => 8000,
            'system' => $system,
            'output_config' => ['effort' => 'low', 'format' => ['type' => 'json_schema', 'schema' => $schema]],
            'messages' => [['role' => 'user', 'content' => $question]],
        ];
        // Opus and Sonnet may decline a request that looks like a security topic; Anthropic then retries it on a fitting model.
        if ($model !== 'claude-haiku-5-5') $body['fallbacks'] = 'default';
        return ['url' => self::ENDPOINT, 'headers' => self::headers($key, isset($body['fallbacks'])), 'body' => $body];
    }
    public static function testRequest(string $key, string $model): array
    {
        return ['url' => self::ENDPOINT, 'headers' => self::headers($key, false), 'body' => ['model' => $model, 'max_tokens' => 64, 'output_config' => ['effort' => 'low'], 'messages' => [['role' => 'user', 'content' => 'Reply with OK.']]]];
    }
    public static function text(array $body): ?string
    {
        if (($body['stop_reason'] ?? null) === 'refusal') return null;
        foreach ((array) ($body['content'] ?? []) as $block) {
            if (($block['type'] ?? null) === 'text') return (string) ($block['text'] ?? '');
        }
        return null;
    }
    private static function headers(string $key, bool $fallbacks): array
    {
        $headers = ['x-api-key' => $key, 'anthropic-version' => '2023-06-01'];
        if ($fallbacks) $headers['anthropic-beta'] = 'server-side-fallback-2026-07-01';
        return $headers;
    }
}

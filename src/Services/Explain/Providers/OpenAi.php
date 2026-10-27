<?php
declare(strict_types=1);
namespace Simplify\Panel\Services\Explain\Providers;
/** GPT through the OpenAI Responses API, with a strict JSON schema and without storing the request. */
final class OpenAi extends Provider
{
    public const ENDPOINT = 'https://api.openai.com/v1/responses';
    public static function name(): string { return 'OpenAI'; }
    public static function label(): string { return 'GPT by OpenAI'; }
    public static function models(): array
    {
        return [
            'gpt-6.1-sol' => 'GPT-6.1 Sol (good answers, about a cent)',
            'gpt-6-astra' => 'GPT-6 Astra (best answers, a few cents)',
            'gpt-6-luna' => 'GPT-6 Luna (cheapest)',
        ];
    }
    public static function keyPlaceholder(): string { return 'sk-…'; }
    public static function keyHelp(): string
    {
        return 'Create a key at platform.openai.com → API keys. Every question is billed to that OpenAI account, usually a few cents or less; set a monthly budget there to be safe.';
    }
    public static function request(string $key, string $model, string $system, array $schema, string $question): array
    {
        return ['url' => self::ENDPOINT, 'headers' => ['Authorization' => 'Bearer '.$key], 'body' => [
            'model' => $model,
            'instructions' => $system,
            'input' => $question,
            'max_output_tokens' => 8000,
            'reasoning' => ['effort' => 'low'],
            'text' => ['format' => ['type' => 'json_schema', 'name' => 'log_check', 'schema' => $schema, 'strict' => true]],
            'store' => false,
        ]];
    }
    public static function testRequest(string $key, string $model): array
    {
        return ['url' => self::ENDPOINT, 'headers' => ['Authorization' => 'Bearer '.$key], 'body' => ['model' => $model, 'input' => 'Reply with OK.', 'max_output_tokens' => 64, 'reasoning' => ['effort' => 'low'], 'store' => false]];
    }
    public static function text(array $body): ?string
    {
        foreach ((array) ($body['output'] ?? []) as $item) {
            if (($item['type'] ?? null) !== 'message') continue;
            foreach ((array) ($item['content'] ?? []) as $part) {
                if (($part['type'] ?? null) === 'refusal') return null;
                if (($part['type'] ?? null) === 'output_text') return (string) ($part['text'] ?? '');
            }
        }
        return null;
    }
}

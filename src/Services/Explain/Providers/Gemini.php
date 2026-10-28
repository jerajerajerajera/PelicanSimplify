<?php
declare(strict_types=1);
namespace Simplify\Panel\Services\Explain\Providers;
/** Gemini through the Gemini API generateContent call, with a JSON schema for the answer. */
final class Gemini extends Provider
{
    public const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/';
    public static function name(): string { return 'Google'; }
    public static function label(): string { return __('pelican-simplify::explain.providers.gemini.label'); }
    public static function models(): array
    {
        // @english: model names; ErrorExplainer::models() translates the note in brackets (explain.model_notes).
        return [
            'gemini-3.8-flash' => 'Gemini 3.8 Flash (good answers, under a cent)',
            'gemini-3.5-flash-lite' => 'Gemini 3.5 Flash-Lite (cheapest)',
        ];
    }
    public static function keyPlaceholder(): string { return 'AIza…'; }
    public static function keyHelp(): string
    {
        return __('pelican-simplify::explain.providers.gemini.key_help');
    }
    public static function request(string $key, string $model, string $system, array $schema, string $question): array
    {
        return ['url' => self::ENDPOINT.rawurlencode($model).':generateContent', 'headers' => ['x-goog-api-key' => $key], 'body' => [
            'systemInstruction' => ['parts' => [['text' => $system]]],
            'contents' => [['role' => 'user', 'parts' => [['text' => $question]]]],
            'generationConfig' => ['responseMimeType' => 'application/json', 'responseJsonSchema' => self::plain($schema), 'maxOutputTokens' => 8000],
        ]];
    }
    public static function testRequest(string $key, string $model): array
    {
        return ['url' => self::ENDPOINT.rawurlencode($model).':generateContent', 'headers' => ['x-goog-api-key' => $key], 'body' => [
            'contents' => [['role' => 'user', 'parts' => [['text' => 'Reply with OK.']]]],
            'generationConfig' => ['maxOutputTokens' => 64],
        ]];
    }
    public static function text(array $body): ?string
    {
        if (!empty($body['promptFeedback']['blockReason'])) return null;
        $candidate = $body['candidates'][0] ?? null;
        if (!is_array($candidate) || in_array($candidate['finishReason'] ?? null, ['SAFETY', 'RECITATION', 'BLOCKLIST', 'PROHIBITED_CONTENT', 'SPII'], true)) return null;
        $text = '';
        foreach ((array) ($candidate['content']['parts'] ?? []) as $part) {
            if (empty($part['thought'])) $text .= (string) ($part['text'] ?? '');
        }
        return $text !== '' ? $text : null;
    }
    /** The schema without additionalProperties, which Gemini's schema support does not need. */
    private static function plain(array $schema): array
    {
        unset($schema['additionalProperties']);
        foreach ($schema as $name => $value) {
            if (is_array($value)) $schema[$name] = self::plain($value);
        }
        return $schema;
    }
}

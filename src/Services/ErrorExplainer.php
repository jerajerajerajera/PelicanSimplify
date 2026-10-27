<?php
declare(strict_types=1);
namespace Simplify\Panel\Services;
use App\Models\Server;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
/**
 * "Explain this error": the last console lines of a server, explained in plain words by Claude.
 * Optional: it only shows once a root admin saves their own Anthropic API key, and every question is paid
 * by that key. The key is stored encrypted and never leaves the server except to api.anthropic.com.
 */
final class ErrorExplainer
{
    public const FILE = 'simplify/error-explainer.json';
    public const ENDPOINT = 'https://api.anthropic.com/v1/messages';
    /** model id => label shown in Simplify settings. */
    public const MODELS = [
        'claude-opus-5-5' => 'Claude Opus 5.5 (best answers, a few cents)',
        'claude-sonnet-5-5' => 'Claude Sonnet 5.5 (about a cent)',
        'claude-haiku-5-5' => 'Claude Haiku 5.5 (cheapest)',
    ];
    public const AUDIENCES = ['everyone' => 'Everyone who can see the console', 'admins' => 'Administrators only'];
    /** Lines sent per question: Wings keeps at most 100 for its log endpoint. */
    public const LINES = 100;
    /** Questions per person per 10 minutes, and for the whole panel per day. */
    public const PER_USER = 6;
    public const PER_DAY = 200;
    private const SCHEMA = ['type' => 'object', 'additionalProperties' => false, 'required' => ['problem', 'cause', 'steps', 'found'], 'properties' => [
        'found' => ['type' => 'boolean', 'description' => 'False when the lines show no error or problem at all.'],
        'problem' => ['type' => 'string', 'description' => 'One or two short sentences: what went wrong, in plain words.'],
        'cause' => ['type' => 'string', 'description' => 'The most likely cause, quoting the decisive log line or mod/plugin name when there is one.'],
        'steps' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Up to five concrete things to try, most likely fix first.'],
    ]];
    private const SYSTEM = "You help people who run game servers (often Minecraft, sometimes other games) on a Pelican panel. "
        ."You get the last lines of a server's console. Explain what went wrong for someone who is not a developer: short, friendly sentences, no jargon without a short explanation. "
        ."Point at the line or the mod, plugin or file that causes the problem when the log shows it. Suggest steps that can be done from a game panel: change a setting, remove or update a mod, restore a backup, give the server more memory, accept the EULA, check a file. "
        ."Never invent details that are not in the log; say so when the log is not conclusive. Parts like [hidden] were removed for privacy. "
        ."If the lines show a normal start or nothing wrong, set found to false and say that the console looks fine. Answer in English.";

    /** ['model'=>..., 'audience'=>..., 'has_key'=>bool]; the key itself never leaves this class. */
    public static function settings(): array
    {
        $data = JsonFile::read(self::FILE);
        return ['model' => isset(self::MODELS[$data['model'] ?? null]) ? $data['model'] : array_key_first(self::MODELS), 'audience' => isset(self::AUDIENCES[$data['audience'] ?? null]) ? $data['audience'] : 'everyone', 'has_key' => is_string($data['key'] ?? null) && $data['key'] !== ''];
    }
    /** Saves the choices; an empty $key keeps the saved one, $remove forgets it. */
    public static function save(array $input, bool $remove = false): void
    {
        abort_unless(user()?->isRootAdmin(), 403);
        $current = JsonFile::read(self::FILE);
        $key = trim((string) ($input['key'] ?? ''));
        $next = [
            'model' => isset(self::MODELS[$input['model'] ?? null]) ? $input['model'] : self::settings()['model'],
            'audience' => isset(self::AUDIENCES[$input['audience'] ?? null]) ? $input['audience'] : self::settings()['audience'],
            'key' => $remove ? null : ($key !== '' ? encrypt($key) : ($current['key'] ?? null)),
        ];
        if (!Storage::disk('local')->put(self::FILE, json_encode($next, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT))) throw new \RuntimeException('Could not save the error explanation settings.');
    }
    private static function key(): ?string
    {
        $stored = JsonFile::read(self::FILE)['key'] ?? null;
        if (!is_string($stored) || $stored === '') return null;
        try { return (string) decrypt($stored); } catch (\Throwable) { return null; } // APP_KEY changed: ask for the key again.
    }
    /** Whether the signed-in user sees the button at all. */
    public static function availableFor($user): bool
    {
        if (!$user || !self::settings()['has_key']) return false;
        return self::settings()['audience'] === 'everyone' || $user->isAdmin();
    }

    /** Removes what should not leave the panel: addresses, e-mail addresses, tokens and passwords. */
    public static function sanitize(array $lines): array
    {
        $patterns = [
            '/\b(password|passwd|secret|token|api[_-]?key|authorization)(\s*[:=]\s*)\S+/i' => '$1$2[hidden]',
            '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i' => '[hidden email]',
            '/\b(?:\d{1,3}\.){3}\d{1,3}\b/' => '[hidden ip]',
            '/\b(?:[0-9a-f]{1,4}:){3,7}[0-9a-f]{1,4}\b/i' => '[hidden ip]',
            '/\b(?=[A-Za-z0-9_\-]*\d)(?=[A-Za-z0-9_\-]*[A-Za-z])[A-Za-z0-9_\-]{32,}\b/' => '[hidden]',
        ];
        $clean = [];
        foreach ($lines as $line) {
            $line = preg_replace('/\x1b\[[0-9;?]*[ -\/]*[@-~]/', '', (string) $line) ?? '';
            $line = rtrim(preg_replace(array_keys($patterns), array_values($patterns), $line) ?? '');
            if ($line !== '') $clean[] = mb_substr($line, 0, 600);
        }
        return array_slice($clean, -self::LINES);
    }

    /** The Messages API request for these lines. */
    public static function payload(array $lines, string $model, string $game = ''): array
    {
        $payload = [
            'model' => $model,
            'max_tokens' => 8000,
            'system' => self::SYSTEM,
            'output_config' => ['effort' => 'low', 'format' => ['type' => 'json_schema', 'schema' => self::SCHEMA]],
            'messages' => [['role' => 'user', 'content' => ($game !== '' ? "Game or software: {$game}\n" : '')."Console, oldest line first:\n<console>\n".implode("\n", $lines)."\n</console>\nWhat went wrong, and what can I try?"]],
        ];
        // Opus and Sonnet may decline a request that looks like a security topic; Anthropic then retries it on a fitting model.
        if ($model !== 'claude-haiku-5-5') $payload['fallbacks'] = 'default';
        return $payload;
    }
    /** Reads the answer from an API response body; null when there is no usable answer. */
    public static function answer(array $body): ?array
    {
        if (($body['stop_reason'] ?? null) === 'refusal') return null;
        foreach ((array) ($body['content'] ?? []) as $block) {
            if (($block['type'] ?? null) !== 'text') continue;
            $data = json_decode((string) ($block['text'] ?? ''), true);
            if (!is_array($data) || !is_string($data['problem'] ?? null)) continue;
            return ['found' => ($data['found'] ?? true) !== false, 'problem' => trim($data['problem']), 'cause' => trim((string) ($data['cause'] ?? '')),
                'steps' => array_values(array_slice(array_filter(array_map(fn ($s) => trim((string) $s), (array) ($data['steps'] ?? [])), fn ($s) => $s !== ''), 0, 5))];
        }
        return null;
    }
    /** A short, plain message for a failed API call, never including the key. */
    public static function failure(int $status, array $body): string
    {
        $type = (string) ($body['error']['type'] ?? '');
        return match (true) {
            $status === 401 || $type === 'authentication_error' => 'The Anthropic API key is not valid. An administrator can enter a new one in Simplify settings.',
            $status === 403 || $type === 'permission_error' => 'This Anthropic API key may not use the chosen model. An administrator can pick another model in Simplify settings.',
            $status === 402 || str_contains(strtolower((string) ($body['error']['message'] ?? '')), 'credit') => 'The Anthropic account behind this key has no credit left. An administrator can add credit at console.anthropic.com.',
            $status === 429 || $type === 'rate_limit_error' => 'Anthropic is busy for this key right now. Try again in a minute.',
            $status === 529 || $type === 'overloaded_error' || $status >= 500 => 'Anthropic is temporarily unavailable. Try again in a minute.',
            default => 'Anthropic could not answer this question'.($status ? ' (error '.$status.')' : '').'. Try again later.',
        };
    }

    private static function call(string $key, array $payload): array
    {
        try {
            $headers = ['x-api-key' => $key, 'anthropic-version' => '2023-06-01'];
            if (isset($payload['fallbacks'])) $headers['anthropic-beta'] = 'server-side-fallback-2026-07-01';
            $response = Http::withHeaders($headers)
                ->acceptJson()->asJson()->connectTimeout(5)->timeout(90)->post(self::ENDPOINT, $payload);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'Could not reach api.anthropic.com from the panel. Check that the panel server may connect to the internet.'];
        }
        $body = (array) ($response->json() ?? []);
        if (!$response->successful()) return ['ok' => false, 'error' => self::failure($response->status(), $body)];
        return ['ok' => true, 'body' => $body];
    }
    /** Settings "Test" button: a tiny request that proves the key and model work. */
    public static function test(?string $key = null, ?string $model = null): array
    {
        abort_unless(user()?->isRootAdmin(), 403);
        $key = trim((string) $key) ?: self::key();
        if (!$key) return ['ok' => false, 'message' => 'Enter an API key first.'];
        $model = isset(self::MODELS[$model]) ? $model : self::settings()['model'];
        $result = self::call($key, ['model' => $model, 'max_tokens' => 64, 'output_config' => ['effort' => 'low'], 'messages' => [['role' => 'user', 'content' => 'Reply with OK.']]]);
        return $result['ok'] ? ['ok' => true, 'message' => 'The key works with '.explode(' (', self::MODELS[$model])[0].'.'] : ['ok' => false, 'message' => preg_replace('/ An administrator .*$/', '', $result['error'])];
    }

    /** The last console lines of a server, read from Wings (the same log the console shows). */
    public static function consoleLines(Server $s): array
    {
        $repository = app(\App\Repositories\Daemon\DaemonServerRepository::class)->setServer($s);
        if (in_array($s->status?->value, ['installing', 'install_failed', 'reinstall_failed'], true)) {
            return array_slice(preg_split('/\r?\n/', (string) $repository->getInstallLogs()) ?: [], -self::LINES);
        }
        return (array) ($repository->getHttpClient()->timeout(10)->get('/api/servers/'.$s->uuid.'/logs', ['size' => self::LINES])->throw()->json('data') ?? []);
    }
    /** Explains the console of $s for the signed-in user; answers stay cached a few minutes for the same lines. */
    public static function explain(Server $s): array
    {
        $user = user();
        if (!self::availableFor($user) || !($key = self::key())) return ['ok' => false, 'error' => 'Error explanations are not set up on this panel.'];
        try { $lines = self::sanitize(self::consoleLines($s)); }
        catch (\Throwable $e) { report($e); return ['ok' => false, 'error' => 'Could not read the console from Wings. Try again when the node is reachable.']; }
        if ($lines === []) return ['ok' => false, 'error' => 'The console is empty, so there is nothing to explain yet.'];
        $settings = self::settings();
        $cacheKey = 'simplify.explain.'.sha1($settings['model'].implode("\n", $lines));
        if (is_array($cached = Cache::get($cacheKey))) return ['ok' => true, ...$cached];
        $personal = 'simplify.explain.user.'.$user->id;
        if (RateLimiter::tooManyAttempts($personal, self::PER_USER)) return ['ok' => false, 'error' => 'You asked a lot of questions in a short time. Try again in '.max(1, (int) ceil(RateLimiter::availableIn($personal) / 60)).' minute(s).'];
        if (RateLimiter::tooManyAttempts('simplify.explain.panel', self::PER_DAY)) return ['ok' => false, 'error' => 'This panel reached its daily limit of '.self::PER_DAY.' explanations. Try again tomorrow.'];
        RateLimiter::hit($personal, 600);
        RateLimiter::hit('simplify.explain.panel', 86400);
        $result = self::call($key, self::payload($lines, $settings['model'], (string) $s->egg?->name));
        if (!$result['ok']) return $result;
        $answer = self::answer($result['body']);
        if (!$answer) return ['ok' => false, 'error' => 'Claude could not explain these lines. Try again after the error shows up again in the console.'];
        $answer += ['lines' => count($lines), 'model' => explode(' (', self::MODELS[$settings['model']])[0]];
        Cache::put($cacheKey, $answer, now()->addMinutes(10));
        return ['ok' => true, ...$answer];
    }
}

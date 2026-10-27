<?php
declare(strict_types=1);
namespace Simplify\Panel\Services;
use App\Models\Server;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Simplify\Panel\Services\Explain\Providers\Anthropic;
use Simplify\Panel\Services\Explain\Providers\Gemini;
use Simplify\Panel\Services\Explain\Providers\OpenAi;
use Simplify\Panel\Services\Explain\Providers\Provider;
/**
 * "Explain this error": the last console lines of a server, explained in plain words by Claude, GPT or Gemini.
 * Optional: it only shows once a root admin saves their own API key for one of them, and every question is paid
 * by that key. Keys are stored encrypted and never leave the server except to that service's own API.
 */
final class ErrorExplainer
{
    public const FILE = 'simplify/error-explainer.json';
    /** provider id => class; the first is the default. */
    public const PROVIDERS = ['anthropic' => Anthropic::class, 'openai' => OpenAi::class, 'gemini' => Gemini::class];
    public const AUDIENCES = ['everyone' => 'Everyone who can see the console', 'admins' => 'Administrators only'];
    /** Lines sent per question: Wings keeps at most 100 for its log endpoint. */
    public const LINES = 100;
    /** Questions per person per 10 minutes, and for the whole panel per day. */
    public const PER_USER = 6;
    public const PER_DAY = 200;
    /** Glob of the panel log files; storage/logs/*.log when null. */
    public static ?string $panelLogs = null;
    // @english: the answer format and instructions are for the model; answerLanguage() asks for the reader's language.
    private const SCHEMA = ['type' => 'object', 'additionalProperties' => false, 'required' => ['found', 'summary', 'issues'], 'properties' => [
        'found' => ['type' => 'boolean', 'description' => 'True only when the log shows a real problem: a crash, a failed start or install, something that stops a feature, a player or the server from working. False for a normal log, also when it has harmless warnings.'],
        'summary' => ['type' => 'string', 'description' => 'One or two short sentences in plain words: what went wrong, or when found is false, that everything seems in order.'],
        'issues' => ['type' => 'array', 'description' => 'At most four. Real problems first, then warnings someone might still want to look at. Empty when there is nothing worth mentioning.', 'items' => [
            'type' => 'object', 'additionalProperties' => false, 'required' => ['title', 'cause', 'steps'], 'properties' => [
                'title' => ['type' => 'string', 'description' => 'A few words naming the problem. When found is false, phrase it as something the person may notice, for example "My panel feels slow" or "Players get disconnected".'],
                'cause' => ['type' => 'string', 'description' => 'What it means and the most likely cause, quoting the decisive log line or mod/plugin name when there is one. Say so when it is harmless.'],
                'steps' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Up to five concrete things to try, most likely fix first. Empty when nothing needs to be done.'],
            ],
        ]],
    ]];
    // @english
    private const SYSTEM = "You help people who run game servers (often Minecraft, sometimes other games) on a Pelican panel. "
        ."You get the last lines of a server's console, or for panel administrators the panel's own log or the Wings daemon log. First check whether anything is actually wrong, then explain it for someone who is not a developer: short, friendly sentences, no jargon without a short explanation. "
        ."Point at the line or the mod, plugin, file or setting that causes a problem when the log shows it. Suggest steps that can be done from a game panel: change a setting, remove or update a mod, restore a backup, give the server more memory, accept the EULA, check a file. For panel and Wings logs, steps an administrator can take on the panel or node machine are fine too. "
        ."Never invent details that are not in the log; say so when the log is not conclusive. Parts like [hidden] were removed for privacy. "
        ."Most logs are fine. Servers print many warnings that are normal and need no action: deprecation notices, \"Can't keep up!\", missing optional mods or config defaults, slow chunk loading, unknown or bad packets from scanner bots, players disconnecting, a single failed request or timeout that did not repeat (for example a few connection timeouts to Wings, such as cURL error 28, while everything else works), entries from hours or days ago that did not come back, and similar. "
        ."Set found to true only for a real problem: a crash, a failed start or install, an error that keeps repeating, or something that clearly stops the server, a feature or a player from working. "
        ."Otherwise set found to false and say that everything seems in order; list what the log does show as issues someone can open when they notice it (for example slow answers from Wings as \"My panel feels slow\"), and say when they are harmless. Answer in English.";

    /** The provider class for an id; the default for an unknown one. */
    public static function provider(?string $id): string
    {
        return self::PROVIDERS[$id] ?? self::PROVIDERS[array_key_first(self::PROVIDERS)];
    }
    /** model id => label for one provider; the note in brackets ("cheapest") in the reader's language. */
    public static function models(?string $provider): array
    {
        return array_map(fn (string $label) => preg_replace_callback('/\(([^)]+)\)$/', fn ($note) => '('.Translations::text('explain.model_notes.'.Translations::key($note[1]), $note[1]).')', $label), self::provider($provider)::models());
    }
    /** provider id => label for Simplify settings. */
    public static function providers(): array
    {
        return array_map(fn (string $class) => $class::label(), self::PROVIDERS);
    }
    /** Encrypted keys per provider; a key saved before there was a choice belongs to Anthropic. */
    private static function storedKeys(array $data): array
    {
        $keys = array_filter((array) ($data['keys'] ?? []), fn ($key) => is_string($key) && $key !== '');
        if (is_string($data['key'] ?? null) && $data['key'] !== '' && !isset($keys['anthropic'])) $keys['anthropic'] = $data['key'];
        return array_intersect_key($keys, self::PROVIDERS);
    }
    /** ['provider'=>..., 'model'=>..., 'audience'=>..., 'has_key'=>bool, 'saved_keys'=>[provider=>bool]]; the keys themselves never leave this class. */
    public static function settings(): array
    {
        $data = JsonFile::read(self::FILE);
        $provider = isset(self::PROVIDERS[$data['provider'] ?? null]) ? $data['provider'] : array_key_first(self::PROVIDERS);
        $models = self::models($provider);
        $keys = self::storedKeys($data);
        return ['provider' => $provider, 'model' => isset($models[$data['model'] ?? null]) ? $data['model'] : array_key_first($models), 'audience' => isset(self::AUDIENCES[$data['audience'] ?? null]) ? $data['audience'] : 'everyone',
            'has_key' => isset($keys[$provider]), 'saved_keys' => array_map(fn ($id) => isset($keys[$id]), array_combine(array_keys(self::PROVIDERS), array_keys(self::PROVIDERS)))];
    }
    /** Saves the choices; an empty $key keeps the saved one of the chosen provider, $remove forgets it. Other providers' keys stay. */
    public static function save(array $input, bool $remove = false): void
    {
        abort_unless(user()?->isRootAdmin(), 403);
        $current = JsonFile::read(self::FILE);
        $settings = self::settings();
        $provider = isset(self::PROVIDERS[$input['provider'] ?? null]) ? $input['provider'] : $settings['provider'];
        $models = self::models($provider);
        $keys = self::storedKeys($current);
        $key = trim((string) ($input['key'] ?? ''));
        if ($remove) unset($keys[$provider]);
        elseif ($key !== '') $keys[$provider] = encrypt($key);
        $next = [
            'provider' => $provider,
            'model' => isset($models[$input['model'] ?? null]) ? $input['model'] : ($provider === $settings['provider'] ? $settings['model'] : array_key_first($models)),
            'audience' => isset(self::AUDIENCES[$input['audience'] ?? null]) ? $input['audience'] : $settings['audience'],
            'keys' => $keys,
        ];
        if (!Storage::disk('local')->put(self::FILE, json_encode($next, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT))) throw new \RuntimeException('Could not save the error explanation settings.');
    }
    private static function key(?string $provider = null): ?string
    {
        $stored = self::storedKeys(JsonFile::read(self::FILE))[$provider ?? self::settings()['provider']] ?? null;
        if ($stored === null) return null;
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
        // @english: placeholders the model sees
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

    /** The question for these lines, the same for every provider. */
    public static function question(array $lines, string $context = ''): string
    {
        return ($context !== '' ? $context."\n" : '')."Log, oldest line first:\n<log>\n".implode("\n", $lines)."\n</log>\nIs everything working, or is something wrong here?";
    }
    /** The request to a provider for these lines: ['url', 'headers', 'body']. */
    public static function request(string $provider, string $key, string $model, array $lines, string $context = ''): array
    {
        return self::provider($provider)::request($key, $model, self::SYSTEM, self::SCHEMA, self::question($lines, $context));
    }
    /** Reads the answer from a provider's response body; null when there is no usable answer. */
    public static function answer(array $body, ?string $provider = null): ?array
    {
        $text = self::provider($provider)::text($body);
        $data = $text === null ? null : json_decode($text, true);
        if (!is_array($data) || !is_string($data['summary'] ?? null)) return null;
        $issues = [];
        foreach (array_slice((array) ($data['issues'] ?? []), 0, 4) as $issue) {
            if (!is_array($issue) || trim((string) ($issue['title'] ?? '')) === '') continue;
            $issues[] = ['title' => trim((string) $issue['title']), 'cause' => trim((string) ($issue['cause'] ?? '')),
                'steps' => array_values(array_slice(array_filter(array_map(fn ($s) => trim((string) $s), (array) ($issue['steps'] ?? [])), fn ($s) => $s !== ''), 0, 5))];
        }
        return ['found' => ($data['found'] ?? true) !== false, 'summary' => trim($data['summary']), 'issues' => $issues];
    }
    /** A short, plain message for a failed API call, never including the key. */
    public static function failure(int $status, array $body, ?string $provider = null): string
    {
        return self::provider($provider)::failure($status, $body);
    }
    /** The model's name without its price note, for "Checked by …". */
    public static function modelName(?string $provider, string $model): string
    {
        return explode(' (', self::models($provider)[$model] ?? $model)[0];
    }

    /** Sends a provider request (only to that provider's own API host) and returns the decoded body or a plain error. */
    private static function call(string $provider, array $request): array
    {
        /** @var class-string<Provider> $class */
        $class = self::provider($provider);
        try {
            $response = Http::withHeaders($request['headers'])
                ->acceptJson()->asJson()->connectTimeout(5)->timeout(90)->post($request['url'], $request['body']);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => __('pelican-simplify::explain.errors.unreachable', ['host' => parse_url($request['url'], PHP_URL_HOST)])];
        }
        $body = (array) ($response->json() ?? []);
        if (!$response->successful()) return ['ok' => false, 'error' => $class::failure($response->status(), $body)];
        return ['ok' => true, 'body' => $body];
    }
    /** Settings "Test" button: a tiny request that proves the key and model work (the typed key, or the saved one). */
    public static function test(?string $key = null, ?string $model = null, ?string $provider = null): array
    {
        abort_unless(user()?->isRootAdmin(), 403);
        $provider = isset(self::PROVIDERS[$provider]) ? $provider : self::settings()['provider'];
        $key = trim((string) $key) ?: self::key($provider);
        if (!$key) return ['ok' => false, 'message' => __('pelican-simplify::explain.errors.no_key')];
        $models = self::models($provider);
        $model = isset($models[$model]) ? $model : (self::settings()['provider'] === $provider ? self::settings()['model'] : array_key_first($models));
        $result = self::call($provider, self::provider($provider)::testRequest($key, $model));
        return $result['ok'] ? ['ok' => true, 'message' => __('pelican-simplify::explain.test.works', ['model' => self::modelName($provider, $model)])] : ['ok' => false, 'message' => preg_replace('/ An administrator .*$/', '', $result['error'])];
    }

    /** Where the lines come from: label for the popup, how the question introduces them, text when there are none. */
    // @english: the second text introduces the lines to the model; labels and empty texts are shown translated (explain.sources).
    public const SOURCES = [
        'console' => ['Console', null, 'The console is empty, so there is nothing to explain yet.'],
        'panel' => ['Panel log', 'Pelican panel log (Laravel), warnings and errors only, newest last.', 'The panel log has no warnings or errors right now.'],
        'wings' => ['Wings log', 'Wings daemon log of the node this server runs on', 'The Wings log of this node is empty.'],
        'plugin' => ['Plugin', 'Minecraft server log lines about a plugin that failed when the server started. Simplify switched that plugin off (renamed it to .jar.disabled) so the server starts cleanly; explain why it failed and how to fix it, for example a newer version or a missing dependency.', 'There are no saved log lines for this plugin.'],
    ];
    /** The panel log and the Wings log can hold anyone's details, so only root admins may send them. */
    public static function logsFor($user): bool
    {
        return (bool) ($user && self::settings()['has_key'] && $user->isRootAdmin());
    }
    /** The sources the signed-in user may explain, for a server page ($s) or the overview (null). */
    public static function sourcesFor($user, ?Server $s): array
    {
        $sources = [];
        if ($s && self::availableFor($user)) $sources[] = 'console';
        if (self::logsFor($user)) { $sources[] = 'panel'; if ($s) $sources[] = 'wings'; }
        return $sources;
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
    /** The end of the newest panel log file. */
    public static function panelLogText(?string $pattern = null): string
    {
        $files = glob($pattern ?? self::$panelLogs ?? storage_path('logs').'/*.log') ?: [];
        if ($files === []) return '';
        usort($files, fn (string $a, string $b) => [filemtime($b), $b] <=> [filemtime($a), $a]);
        $file = new \SplFileObject($files[0], 'r');
        $size = $file->getSize();
        $file->fseek(max(0, $size - 262144));
        return $size > 0 ? (string) $file->fread(min($size, 262144)) : '';
    }
    /** Warnings and errors from a Laravel log: the last eight entries, each its message and first stack frames. */
    public static function panelEntries(string $text): array
    {
        $entries = preg_split('/\r?\n(?=\[\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2})/', $text) ?: [];
        $wanted = array_values(array_filter($entries, fn (string $entry) => (bool) preg_match('/^\[[^\]]+\] [\w-]+\.(WARNING|ERROR|CRITICAL|ALERT|EMERGENCY):/', ltrim($entry))));
        $lines = [];
        foreach (array_slice($wanted, -8) as $entry) array_push($lines, ...array_slice(preg_split('/\r?\n/', trim($entry)) ?: [], 0, 12));
        return $lines;
    }
    /** The log part of a Wings diagnostics report (everything after its logs heading), last lines only. */
    public static function wingsLog(string $report): array
    {
        $lines = preg_split('/\r?\n/', $report) ?: [];
        foreach ($lines as $index => $line) {
            if (preg_match('/^\W*(latest\s+)?(wings\s+)?logs?\b/i', trim($line))) { $lines = array_slice($lines, $index + 1); break; }
        }
        return array_slice(array_values(array_filter($lines, fn ($line) => trim($line, " \t=-") !== '')), -self::LINES);
    }
    /** Where the question comes from, so the model knows whether the panel itself is up. */
    // @english
    public const ASKED_FROM = [
        'panel' => 'The panel itself is up: an administrator is using it to ask this.',
        'emergency' => "Asked from Simplify's emergency page, which works without the normal panel pages: the panel may be partly broken, for example showing error 500.",
        'terminal' => 'Asked from a terminal on the panel server: the panel website may be down.',
    ];
    private static function lines(?Server $s, string $source, $node = null): array
    {
        return match ($source) {
            'console' => self::consoleLines($s),
            'plugin' => [], // Only with the lines Simplify saved when it switched the plugin off (explainPlugin).
            'panel' => self::panelEntries(self::panelLogText()),
            'wings' => self::wingsLog((string) app(\App\Repositories\Daemon\DaemonSystemRepository::class)->setNode($node ?? $s->node)->getDiagnostics(self::LINES, false, true)->throw()->body()),
        };
    }
    /** The company behind the chosen AI, such as "OpenAI", for texts about its key. */
    public static function providerName(): string
    {
        return self::provider(self::settings()['provider'])::name();
    }
    /** The saved key of the chosen AI for the emergency page and the terminal, which check access themselves. */
    public static function savedKey(): ?string
    {
        return self::key();
    }
    /** Runs $callback; when the cache or its store is broken (a half-working panel), $fallback instead of an error. */
    private static function guarded(callable $callback, mixed $fallback = null): mixed
    {
        try { return $callback(); } catch (\Throwable $e) { report($e); return $fallback; }
    }

    /** Asks for the answer in the reader's language; the instructions to the model stay English. */
    private static function answerLanguage(): string
    {
        $locale = str_replace('_', '-', Translations::locale());
        if ($locale === '' || str_starts_with($locale, 'en')) return '';
        $name = class_exists(\Locale::class) ? \Locale::getDisplayLanguage($locale, 'en') : $locale;
        // @english
        return 'Write the summary, the titles, the causes and the steps in '.$name.' ('.$locale.').';
    }
    /** Explains a log for the signed-in user; answers stay cached a few minutes for the same lines. */
    public static function explain(?Server $s, string $source = 'console'): array
    {
        $user = user();
        if (!in_array($source, self::sourcesFor($user, $s), true) || !($key = self::key())) return ['ok' => false, 'error' => __('pelican-simplify::explain.errors.not_set_up')];
        return self::ask($key, $source, $s, null, 'simplify.explain.user.'.$user->id, $source === 'console' ? '' : self::ASKED_FROM['panel']);
    }
    /** Explains the saved log lines of a plugin Simplify switched off, for people who may read this server's console. */
    public static function explainPlugin(Server $s, array $lines): array
    {
        $user = user();
        if (!in_array('console', self::sourcesFor($user, $s), true) || !($key = self::key())) return ['ok' => false, 'error' => __('pelican-simplify::explain.errors.not_set_up')];
        return self::ask($key, 'plugin', $s, null, 'simplify.explain.user.'.$user->id, '', self::LINES, array_values(array_filter($lines, 'is_string')));
    }
    /**
     * The question itself, without access checks: explain() for the panel pages, the emergency page and
     * simplify:explain call it after checking who asks. $personal is the rate limit of one person (none in the terminal),
     * $limit the most lines sent and $asked one of ASKED_FROM.
     */
    public static function ask(string $key, string $source, ?Server $s = null, $node = null, ?string $personal = null, string $asked = '', int $limit = self::LINES, ?array $given = null): array
    {
        if (!isset(self::SOURCES[$source])) return ['ok' => false, 'error' => __('pelican-simplify::explain.errors.unknown_log')];
        try { $lines = array_slice(self::sanitize($given ?? self::lines($s, $source, $node)), -max(1, min(self::LINES, $limit))); }
        catch (\Throwable $e) { report($e); return ['ok' => false, 'error' => match ($source) { 'panel' => __('pelican-simplify::explain.errors.read_panel'), 'wings' => __('pelican-simplify::explain.errors.read_wings'), default => __('pelican-simplify::explain.errors.read_console') }]; }
        if ($lines === []) return ['ok' => true, 'found' => false, 'summary' => Translations::text('explain.sources.'.$source.'.empty', self::SOURCES[$source][2]), 'issues' => [], 'lines' => 0, 'model' => null, 'source' => $source];
        $settings = self::settings();
        $cacheKey = 'simplify.explain.'.sha1($settings['provider'].$settings['model'].$source.self::answerLanguage().implode("\n", $lines));
        if (is_array($cached = self::guarded(fn () => Cache::get($cacheKey)))) return ['ok' => true, ...$cached];
        if ($personal && self::guarded(fn () => RateLimiter::tooManyAttempts($personal, self::PER_USER), false)) return ['ok' => false, 'error' => trans_choice('pelican-simplify::explain.errors.too_many', max(1, (int) ceil(RateLimiter::availableIn($personal) / 60)))];
        if (self::guarded(fn () => RateLimiter::tooManyAttempts('simplify.explain.panel', self::PER_DAY), false)) return ['ok' => false, 'error' => __('pelican-simplify::explain.errors.daily_limit', ['count' => self::PER_DAY])];
        self::guarded(function () use ($personal): void {
            if ($personal) RateLimiter::hit($personal, 600);
            RateLimiter::hit('simplify.explain.panel', 86400);
        });
        // @english
        $context = self::SOURCES[$source][1] ?? ($s?->egg?->name ? 'Game or software: '.$s->egg->name : '');
        // The time lets the model see that a warning from hours ago is not a problem now.
        // @english
        $context = trim($context.($asked !== '' ? ' '.$asked : '')."\nCurrent time on the panel: ".date('Y-m-d H:i:s T')."\n".self::answerLanguage());
        $provider = $settings['provider'];
        $result = self::call($provider, self::request($provider, $key, $settings['model'], $lines, $context));
        if (!$result['ok']) return $result;
        $answer = self::answer($result['body'], $provider);
        if (!$answer) return ['ok' => false, 'error' => __('pelican-simplify::explain.errors.no_answer', ['model' => self::modelName($provider, $settings['model'])])];
        $answer += ['lines' => count($lines), 'model' => self::modelName($provider, $settings['model']), 'source' => $source];
        self::guarded(fn () => Cache::put($cacheKey, $answer, now()->addMinutes(10)));
        return ['ok' => true, ...$answer];
    }
}

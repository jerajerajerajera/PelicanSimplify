<?php
declare(strict_types=1);
namespace Simplify\Panel\Http\Controllers;

use Illuminate\Http\Request;
use Simplify\Panel\Services\EmergencyAccess;
use Simplify\Panel\Services\ErrorExplainer;
use Simplify\Panel\Services\Translations;

/**
 * Emergency page for "Explain this error": the panel log and the Wings logs explained when the panel itself breaks.
 * Plain HTML from this class only (no Filament, Livewire, Blade or plugin assets), so it keeps working when those fail.
 * Root admins open /simplify/explain with their normal session; when even that fails, `php artisan simplify:explain --url`
 * prints a temporary address that needs no session at all. Every error is caught, so this page never answers 500.
 */
final class EmergencyExplainController
{
    /** /simplify/explain: signed in through the normal session. */
    public function session(Request $request)
    {
        try {
            $user = user();
            if (!$user) return $this->page(401, '<p>'.self::t('sign_in', 'Sign in to the panel first, then open this page again.').'</p>'.$this->terminalHint());
            if (!$user->isRootAdmin()) return $this->page(403, '<p>'.self::t('owner_only', 'Only the panel owner (a root administrator) can use this page.').'</p>');
            return $this->handle($request, 'simplify.explain.user.'.$user->id, csrf_token());
        } catch (\Throwable $e) {
            return $this->broken($e);
        }
    }

    /** /simplify/explain/access/{token}: the temporary address from the terminal, no session needed. */
    public function token(Request $request, string $token)
    {
        try {
            if (!EmergencyAccess::valid($token)) return $this->page(403, '<p>'.self::t('expired', 'This address has expired or was replaced by a newer one.').'</p>'.$this->terminalHint());
            return $this->handle($request, 'simplify.explain.access', null);
        } catch (\Throwable $e) {
            return $this->broken($e);
        }
    }

    private function handle(Request $request, string $personal, ?string $csrf)
    {
        $key = ErrorExplainer::savedKey();
        if (!$key) return $this->page(200, '<p>'.self::t('no_key', 'No :provider API key is saved on this panel. A root administrator adds one in Simplify settings, under Error explanations.', ['provider' => ErrorExplainer::providerName()]).'</p>'
            .'<p>'.self::t('no_key_terminal', 'When the panel does not open, run :command on the panel server. It asks for a key and does not save it.', [], ['command' => '<code>php artisan simplify:explain</code>']).'</p>');
        [$nodes, $nodeError] = $this->nodes();
        $result = null;
        $asked = null;
        if ($request->isMethod('POST')) {
            $source = $request->filled('node') ? 'wings' : 'panel';
            $node = $source === 'wings' ? ($nodes[(int) $request->input('node')] ?? null) : null;
            if ($source === 'wings' && !$node) $result = ['ok' => false, 'error' => self::plain('choose_node', 'Choose a node.')];
            else $result = ErrorExplainer::ask($key, $source, null, $node, $personal, ErrorExplainer::ASKED_FROM['emergency']);
            $asked = $source === 'panel' ? self::plain('panel_log', 'Panel log') : ($node ? self::plain('wings_log_of', 'Wings log of :node', ['node' => $node->name]) : self::plain('wings_log', 'Wings log'));
        }
        return $this->page(200, $this->form($nodes, $nodeError, $csrf).($result ? $this->answer((string) $asked, $result) : ''));
    }

    /** Nodes by id; [[], message] when the database cannot be reached. */
    private function nodes(): array
    {
        try {
            return [\App\Models\Node::query()->orderBy('name')->get()->keyBy('id')->all(), null];
        } catch (\Throwable $e) {
            report($e);
            return [[], self::plain('no_database', 'The database could not be reached, so the Wings logs are not available here. The panel log often shows why.')];
        }
    }

    private function form(array $nodes, ?string $nodeError, ?string $csrf): string
    {
        $hidden = $csrf ? '<input type="hidden" name="_token" value="'.self::e($csrf).'">' : '';
        $html = '<p class="lead">'.self::t('lead', 'Use this page when the panel shows an error such as 500 and its normal pages do not open. The AI chosen in Simplify settings reads the log, says whether something is wrong and how to fix it.').'</p>';
        $html .= '<form method="post" class="ask" onsubmit="this.classList.add(\'busy\')">'.$hidden;
        $html .= '<button type="submit" name="source" value="panel">'.self::t('check_panel', 'Check the panel log').'</button>';
        foreach ($nodes as $id => $node) {
            $html .= '<button type="submit" name="node" value="'.(int) $id.'" class="secondary">'.self::t('check_wings', 'Check Wings log of :node', ['node' => (string) $node->name]).'</button>';
        }
        $html .= '<p class="wait">'.self::t('wait', 'Checking the log. This can take up to a minute.').'</p></form>';
        if ($nodeError) $html .= '<p class="note">'.self::e($nodeError).'</p>';
        return $html;
    }

    private function answer(string $asked, array $result): string
    {
        if (!$result['ok']) return '<section class="result bad" role="alert"><h2>'.self::e($asked).'</h2><p>'.self::e((string) $result['error']).'</p></section>';
        $found = (bool) $result['found'];
        $html = '<section class="result '.($found ? 'bad' : 'good').'"><h2>'.self::e($asked).': '.($found ? self::t('attention', 'Something needs attention') : self::t('in_order', 'Everything seems in order')).'</h2>';
        $html .= '<p>'.self::e((string) $result['summary']).'</p>';
        if ($result['issues']) $html .= $found ? '' : '<p class="note">'.self::t('noticed', 'Noticed something?').'</p>';
        foreach ($result['issues'] as $issue) {
            $html .= '<details'.($found ? ' open' : '').'><summary>'.self::e($issue['title']).'</summary>';
            if ($issue['cause'] !== '') $html .= '<p>'.self::e($issue['cause']).'</p>';
            if ($issue['steps']) $html .= '<ol>'.implode('', array_map(fn ($step) => '<li>'.self::e($step).'</li>', $issue['steps'])).'</ol>';
            $html .= '</details>';
        }
        if ($result['model']) $html .= '<p class="note">'.self::e(self::choice('checked', (int) $result['lines'], ['model' => (string) $result['model']])).'</p>';
        return $html.'</section>';
    }

    private function terminalHint(): string
    {
        return '<p>'.self::t('terminal_hint', 'When the panel does not open, run :command on the panel server, or :url_command for a temporary address to this page.', [], ['command' => '<code>php artisan simplify:explain</code>', 'url_command' => '<code>php artisan simplify:explain --url</code>']).'</p>';
    }

    /** This page failed too: show what happened instead of a 500. */
    private function broken(\Throwable $e)
    {
        try { report($e); } catch (\Throwable) {}
        $message = ErrorExplainer::sanitize([get_class($e).': '.$e->getMessage()])[0] ?? get_class($e);
        return $this->page(200, '<section class="result bad" role="alert"><h2>'.self::t('broken', 'This page could not finish').'</h2><p>'.self::e($message).'</p></section>'.$this->terminalHint());
    }

    private function page(int $status, string $body)
    {
        $html = '<!doctype html><html lang="'.self::e(str_replace('_', '-', self::locale())).'"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">'
            .'<title>'.self::t('title', 'Explain panel errors').'</title><style>'.self::CSS.'</style></head><body><main><h1>'.self::t('title', 'Explain panel errors').'</h1>'.$body.'</main></body></html>';
        return response($html, $status, [
            'Content-Type' => 'text/html; charset=utf-8',
            'Cache-Control' => 'no-store',
            // The temporary address carries its token, so it must not leak through a Referer header or a frame.
            'Referrer-Policy' => 'no-referrer',
            'X-Frame-Options' => 'DENY',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; script-src-attr 'unsafe-inline'; form-action 'self'; frame-ancestors 'none'",
        ]);
    }

    /** A text in the reader's language, or the English given when the translations cannot load (this page must always work). */
    private static function plain(string $key, string $english, array $replace = []): string
    {
        try { return Translations::text('explain.emergency.'.$key, $english, $replace); }
        catch (\Throwable) { return strtr($english, array_combine(array_map(fn ($name) => ':'.$name, array_keys($replace)), array_map('strval', $replace)) ?: []); }
    }

    /** plain(), escaped for HTML; $html placeholders take code samples as they are. */
    private static function t(string $key, string $english, array $replace = [], array $html = []): string
    {
        $text = self::e(self::plain($key, $english, $replace));
        foreach ($html as $name => $value) $text = str_replace(':'.$name, $value, $text);
        return $text;
    }

    /** One of the 'one|many' forms of a text, by count. */
    private static function choice(string $key, int $count, array $replace = []): string
    {
        try { return trans_choice('pelican-simplify::explain.emergency.'.$key, $count, $replace); }
        catch (\Throwable) { return self::plain($key, $count === 1 ? ':count line checked by :model.' : ':count lines checked by :model.', ['count' => $count] + $replace); }
    }

    private static function locale(): string
    {
        try { return Translations::locale(); } catch (\Throwable) { return 'en'; }
    }

    private static function e(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private const CSS = <<<'CSS'
:root{color-scheme:light dark;--bg:#f4f5f7;--card:#fff;--text:#1d2129;--muted:#5d6472;--line:#dde0e6;--accent:#2563eb;--on-accent:#fff;--good:#15803d;--bad:#b45309}
@media (prefers-color-scheme:dark){:root{--bg:#111318;--card:#1a1d24;--text:#e8eaef;--muted:#9aa1ae;--line:#2c313b;--accent:#60a5fa;--on-accent:#0b1220;--good:#4ade80;--bad:#fbbf24}}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--text);font:15px/1.5 system-ui,-apple-system,"Segoe UI",sans-serif}
main{max-width:720px;margin:0 auto;padding:32px 16px}h1{font-size:22px;margin:0 0 8px}h2{font-size:16px;margin:0 0 6px}
p{margin:0 0 10px}.lead,.note{color:var(--muted)}.note{font-size:13px}code{font:13px ui-monospace,monospace;background:var(--card);border:1px solid var(--line);border-radius:6px;padding:1px 5px}
.ask{display:flex;flex-wrap:wrap;gap:8px;margin:16px 0}button{font:inherit;font-weight:600;border:1px solid var(--accent);background:var(--accent);color:var(--on-accent);border-radius:10px;padding:9px 14px;cursor:pointer}
button.secondary{background:var(--card);color:var(--text);border-color:var(--line)}.wait{display:none;width:100%;color:var(--muted)}.busy .wait{display:block}.busy button{opacity:.5;pointer-events:none}
.result{background:var(--card);border:1px solid var(--line);border-left:4px solid var(--good);border-radius:12px;padding:16px;margin-top:16px}.result.bad{border-left-color:var(--bad)}
details{border-top:1px solid var(--line);padding:8px 0}summary{font-weight:600;cursor:pointer}ol{margin:6px 0 0;padding-left:20px}
CSS;
}

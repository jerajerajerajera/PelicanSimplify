<?php
declare(strict_types=1);
namespace Simplify\Panel\Services;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
/**
 * Finds plugins and mods on several sites and works out which file to install for this server.
 * Modrinth for both; Hangar (PaperMC) and SpigotMC (via Spiget) for plugins. Projects that only link to a GitHub
 * release are installed from that release. The parsing is separate from the requests so it can be tested offline.
 */
final class PluginSources
{
    public const LABELS = ['modrinth' => 'Modrinth', 'hangar' => 'Hangar', 'spigot' => 'SpigotMC', 'curseforge' => 'CurseForge', 'github' => 'GitHub'];
    private const ORDER = ['modrinth' => 0, 'hangar' => 1, 'spigot' => 2, 'curseforge' => 3];
    /** CurseForge: Minecraft, its classes for Bukkit plugins and for mods, and its mod loader numbers. */
    private const CURSEFORGE = ['game' => 432, 'plugins' => 5, 'mods' => 6, 'loaders' => ['forge' => 1, 'fabric' => 4, 'quilt' => 5, 'neoforge' => 6]];
    private const AGENT = 'PelicanSimplify (github.com/jerajerajerajera/PelicanSimplify)';
    /** Hosts Wings may download from: the sites' own file servers and GitHub release assets. */
    private const HOSTS = ['cdn.modrinth.com', 'hangarcdn.papermc.io', 'api.spiget.org', 'github.com', 'edge.forgecdn.net', 'mediafilez.forgecdn.net'];

    /** Several GETs at once. A site that fails or is slow gives null instead of failing the search. CurseForge URLs get the saved key. */
    public static function fetchAll(array $urls): array
    {
        if (!$urls) return [];
        $headers = fn (string $url) => ['User-Agent' => self::AGENT, ...(str_starts_with($url, CurseForge::API) ? (CurseForge::headers() ?? []) : [])];
        $responses = Http::pool(fn (Pool $pool) => array_map(fn ($key) => $pool->as((string) $key)->acceptJson()->withHeaders($headers($urls[$key]))->connectTimeout(3)->timeout(8)->get($urls[$key]), array_keys($urls)));
        $out = [];
        foreach ($urls as $key => $_) {
            $response = $responses[$key] ?? null;
            $out[$key] = $response instanceof Response && $response->successful() ? $response->json() : null;
        }
        return $out;
    }

    /** "1.21.4" -> "1.21", "26.1.2" -> "26.1": plugins usually work across a version line, mods do not. */
    public static function line(string $version): string
    {
        return preg_match('/^(\d+\.\d+)/', $version, $m) ? $m[1] : $version;
    }

    public static function fits(array $versions, string $minecraft, bool $exact): bool
    {
        if ($minecraft === '') return false;
        if (in_array($minecraft, $versions, true)) return true;
        if ($exact) return false;
        foreach ($versions as $version) if (is_string($version) && self::line($version) === self::line($minecraft)) return true;
        return false;
    }

    /** The loaders Modrinth lists a server's plugins or mods under. */
    public static function loaders(string $loader): array
    {
        return in_array($loader, ['paper', 'purpur'], true) ? ['paper', 'spigot', 'bukkit'] : ($loader === 'spigot' ? ['spigot', 'bukkit'] : [$loader]);
    }

    public static function searchUrls(string $query, string $loader, string $kind, bool $curseforge = false): array
    {
        $query = trim(mb_substr($query, 0, 80));
        $types = $kind === 'plugins' ? ['project_type:plugin', 'project_type:mod'] : ['project_type:mod'];
        $facets = json_encode([$types, array_map(fn ($l) => 'categories:'.$l, self::loaders($loader))]);
        $urls = ['modrinth' => 'https://api.modrinth.com/v2/search?'.http_build_query(['query' => $query, 'limit' => 15, 'index' => $query === '' ? 'downloads' : 'relevance', 'facets' => $facets])];
        // CurseForge only with a key saved by an admin; mods only for the loaders it knows.
        $cfLoader = self::CURSEFORGE['loaders'][$loader] ?? null;
        if ($curseforge && ($kind === 'plugins' || $cfLoader)) $urls['curseforge'] = CurseForge::API.'mods/search?'.http_build_query(['gameId' => self::CURSEFORGE['game'], 'classId' => self::CURSEFORGE[$kind === 'plugins' ? 'plugins' : 'mods'], 'searchFilter' => $query, 'sortField' => 2, 'sortOrder' => 'desc', 'pageSize' => 15, ...($kind === 'plugins' ? [] : ['modLoaderType' => $cfLoader])]);
        if ($kind !== 'plugins') return $urls;
        if (in_array($loader, ['paper', 'purpur'], true)) $urls['hangar'] = 'https://hangar.papermc.io/api/v1/projects?'.http_build_query(['q' => $query, 'limit' => 15, 'platform' => 'PAPER', ...($query === '' ? ['sort' => '-downloads'] : [])]);
        $urls['spigot'] = $query === ''
            ? 'https://api.spiget.org/v2/resources/free?'.http_build_query(['size' => 15, 'sort' => '-downloads'])
            : 'https://api.spiget.org/v2/search/resources/'.rawurlencode($query).'?'.http_build_query(['field' => 'name', 'size' => 15, 'sort' => '-downloads']);
        return $urls;
    }

    /** Search results of every site in one list: one card per plugin, best source first, the other sites under "also". */
    public static function merge(array $results, string $query, string $minecraft, string $kind): array
    {
        $exact = $kind !== 'plugins';
        $items = [];
        foreach ($results['modrinth']['hits'] ?? [] as $h) {
            if (!is_array($h) || empty($h['project_id'])) continue;
            $items[] = self::item('modrinth', (string) $h['project_id'], $h['title'] ?? '', $h['description'] ?? '', $h['icon_url'] ?? '', $h['author'] ?? '', (int) ($h['downloads'] ?? 0),
                'https://modrinth.com/'.(($h['project_type'] ?? '') === 'mod' ? 'mod' : 'plugin').'/'.rawurlencode((string) ($h['slug'] ?? $h['project_id'])), self::fits($h['versions'] ?? [], $minecraft, $exact));
        }
        foreach ($results['hangar']['result'] ?? [] as $p) {
            $owner = (string) ($p['namespace']['owner'] ?? '');
            $slug = (string) ($p['namespace']['slug'] ?? '');
            if (!preg_match('/^[\w.-]{1,64}$/', $owner) || !preg_match('/^[\w.-]{1,64}$/', $slug)) continue;
            $items[] = self::item('hangar', $slug, $p['name'] ?? $slug, $p['description'] ?? '', $p['avatarUrl'] ?? '', $owner, (int) ($p['stats']['downloads'] ?? 0),
                'https://hangar.papermc.io/'.rawurlencode($owner).'/'.rawurlencode($slug), self::fits($p['supportedPlatforms']['PAPER'] ?? [], $minecraft, false));
        }
        foreach (is_array($results['spigot'] ?? null) ? $results['spigot'] : [] as $r) {
            if (!is_array($r) || !empty($r['premium']) || empty($r['id'])) continue;
            $icon = (string) ($r['icon']['url'] ?? '');
            $items[] = self::item('spigot', (string) (int) $r['id'], $r['name'] ?? '', $r['tag'] ?? '', $icon !== '' ? 'https://www.spigotmc.org/'.ltrim($icon, '/') : '', '', (int) ($r['downloads'] ?? 0),
                'https://www.spigotmc.org/resources/'.(int) $r['id'].'/', self::fits($r['testedVersions'] ?? [], $minecraft, false));
        }
        foreach ($results['curseforge']['data'] ?? [] as $c) {
            if (!is_array($c) || empty($c['id'])) continue;
            $versions = array_column(array_filter($c['latestFilesIndexes'] ?? [], 'is_array'), 'gameVersion');
            $page = (string) ($c['links']['websiteUrl'] ?? '');
            $items[] = self::item('curseforge', (string) (int) $c['id'], $c['name'] ?? '', $c['summary'] ?? '', $c['logo']['thumbnailUrl'] ?? '', $c['authors'][0]['name'] ?? '', (int) ($c['downloadCount'] ?? 0),
                str_starts_with($page, 'https://www.curseforge.com/') ? $page : 'https://www.curseforge.com/minecraft/', self::fits($versions, $minecraft, $exact));
        }
        // Each site already sorts by relevance: take their results in turns, made-for-this-version and exact names first.
        $wanted = self::same($query);
        $rank = [];
        foreach ($items as $i => $item) $items[$i]['rank'] = $rank[$item['source']] = ($rank[$item['source']] ?? -1) + 1;
        $order = fn ($x) => [$x['fits'], $wanted !== '' && self::same($x['title']) === $wanted, $wanted === '' ? $x['downloads'] : -$x['rank'], $x['downloads']];
        usort($items, fn ($a, $b) => $order($b) <=> $order($a));
        // One card per plugin, in the place of its best result. The card installs from the preferred site that has a
        // release for this version (Modrinth, then Hangar, then SpigotMC); the other sites go under "also".
        $groups = [];
        foreach ($items as $item) if (($same = self::same($item['title'])) !== '') $groups[$same][] = $item;
        $cards = [];
        foreach ($groups as $group) {
            usort($group, fn ($a, $b) => [$b['fits'], -self::ORDER[$b['source']]] <=> [$a['fits'], -self::ORDER[$a['source']]]);
            $card = array_shift($group);
            foreach ($group as $other) {
                if ($other['source'] === $card['source'] || in_array($other['source'], array_column($card['also'], 'source'), true)) continue;
                $card['also'][] = ['source' => $other['source'], 'label' => $other['label'], 'key' => $other['key'], 'fits' => $other['fits'], 'page' => $other['page']];
                $card['downloads'] = max($card['downloads'], $other['downloads']);
                if ($card['icon'] === '') $card['icon'] = $other['icon'];
                if ($card['description'] === '') $card['description'] = $other['description'];
            }
            unset($card['rank']);
            $cards[] = $card;
        }
        return array_slice($cards, 0, 24);
    }

    private static function item(string $source, string $id, mixed $title, mixed $description, mixed $icon, mixed $author, int $downloads, string $page, bool $fits): array
    {
        $icon = (string) $icon;
        return ['key' => $source.':'.$id, 'source' => $source, 'label' => self::LABELS[$source], 'title' => mb_substr(trim((string) $title), 0, 80), 'description' => mb_substr(trim((string) $description), 0, 240),
            'icon' => str_starts_with($icon, 'https://') ? $icon : '', 'author' => mb_substr((string) $author, 0, 60), 'downloads' => $downloads, 'page' => $page, 'fits' => $fits, 'also' => []];
    }

    private static function same(string $title): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower($title)) ?? '';
    }

    /** "modrinth:AANobbMI" -> ['modrinth', 'AANobbMI'], or null when the key is not one of ours. */
    public static function parseKey(string $key): ?array
    {
        if (!preg_match('/^(modrinth|hangar|spigot|curseforge):([\w.-]{1,128})$/', $key, $m)) return null;
        if (in_array($m[1], ['spigot', 'curseforge'], true) && !ctype_digit($m[2])) return null;
        return [$m[1], $m[2]];
    }

    /** The file that "Install" downloads for each key, looked up for all keys at once. */
    public static function resolveMany(array $keys, string $minecraft, string $loader, string $kind): array
    {
        $urls = [];
        $parsed = [];
        foreach (array_slice(array_unique($keys), 0, 24) as $key) {
            if (!is_string($key) || !($p = self::parseKey($key))) continue;
            if (!in_array($p[0], ['modrinth', 'curseforge'], true) && $kind !== 'plugins') continue;
            if ($p[0] === 'curseforge' && !CurseForge::hasKey()) continue;
            $parsed[$key] = $p;
            [$source, $id] = $p;
            if ($source === 'modrinth') $urls[$key] = 'https://api.modrinth.com/v2/project/'.rawurlencode($id).'/version?'.http_build_query(['loaders' => json_encode(self::loaders($loader))]);
            if ($source === 'hangar') $urls[$key] = 'https://hangar.papermc.io/api/v1/projects/'.rawurlencode($id).'/versions?'.http_build_query(['limit' => 25, 'platform' => 'PAPER']);
            if ($source === 'curseforge') $urls[$key] = CurseForge::API.'mods/'.$id.'/files?'.http_build_query(['pageSize' => 30, ...($kind === 'plugins' ? [] : ['modLoaderType' => self::CURSEFORGE['loaders'][$loader] ?? 0])]);
            if ($source === 'spigot') {
                $urls[$key] = 'https://api.spiget.org/v2/resources/'.$id;
                $urls[$key.'#version'] = 'https://api.spiget.org/v2/resources/'.$id.'/versions/latest';
            }
        }
        $data = self::fetchAll($urls);
        $out = [];
        $github = [];
        foreach ($parsed as $key => [$source, $id]) {
            $out[$key] = self::choose($source, $id, $data[$key] ?? null, $data[$key.'#version'] ?? null, $minecraft, $kind);
            if (isset($out[$key]['github'])) $github[$key] = $out[$key]['github'];
        }
        // Sites that only link to a GitHub release: look up that release's .jar.
        foreach (self::fetchAll($github) as $key => $release) {
            $out[$key] = self::fromRelease($out[$key], $release);
        }
        return $out;
    }

    public static function resolve(string $key, string $minecraft, string $loader, string $kind): array
    {
        return self::resolveMany([$key], $minecraft, $loader, $kind)[$key] ?? ['installable' => false, 'reason' => 'This plugin was not found.'];
    }

    /** Picks the newest release made for this Minecraft version, or the newest release at all with fits=false. */
    public static function choose(string $source, string $id, mixed $data, mixed $latest, string $minecraft, string $kind): array
    {
        $none = ['installable' => false, 'source' => $source, 'label' => self::LABELS[$source], 'reason' => self::LABELS[$source].' did not answer. Try again in a moment.'];
        if (!is_array($data)) return $none;
        $exact = $kind !== 'plugins';
        if ($source === 'modrinth') {
            $versions = array_values(array_filter($data, fn ($v) => is_array($v) && ($v['version_type'] ?? '') === 'release')) ?: array_values(array_filter($data, 'is_array'));
            $pick = null;
            foreach ($versions as $v) if (self::fits($v['game_versions'] ?? [], $minecraft, $exact)) { $pick = $v; break; }
            $fits = $pick !== null;
            $pick ??= $versions[0] ?? null;
            if (!$pick) return [...$none, 'reason' => 'This project has no downloadable release for your server software.'];
            $file = null;
            foreach ($pick['files'] ?? [] as $f) if (str_ends_with(strtolower((string) ($f['filename'] ?? '')), '.jar') && ($file === null || !empty($f['primary']))) $file = $f;
            $needs = [];
            foreach ($pick['dependencies'] ?? [] as $d) if (($d['dependency_type'] ?? '') === 'required' && !empty($d['project_id'])) $needs[] = (string) $d['project_id'];
            return self::result($source, (string) ($pick['version_number'] ?? ''), (string) ($pick['id'] ?? ''), $file['filename'] ?? '', $file['url'] ?? '', $pick['game_versions'] ?? [], $fits, $needs, [], null);
        }
        if ($source === 'hangar') {
            $versions = array_values(array_filter($data['result'] ?? [], 'is_array'));
            $pick = null;
            foreach ([true, false] as $releaseOnly) {
                foreach ($versions as $v) if ((!$releaseOnly || strcasecmp((string) ($v['channel']['name'] ?? ''), 'Release') === 0) && self::fits($v['platformDependencies']['PAPER'] ?? [], $minecraft, false)) { $pick = $v; break 2; }
            }
            $fits = $pick !== null;
            $pick ??= $versions[0] ?? null;
            if (!$pick) return [...$none, 'reason' => 'This project has no Paper release on Hangar.'];
            $download = $pick['downloads']['PAPER'] ?? [];
            $names = [];
            foreach ($pick['pluginDependencies']['PAPER'] ?? [] as $d) if (!empty($d['required']) && !empty($d['name'])) $names[] = (string) $d['name'];
            $name = (string) ($pick['name'] ?? '');
            $filename = (string) ($download['fileInfo']['name'] ?? '');
            return self::result($source, $name, $name, $filename, (string) ($download['downloadUrl'] ?? ''), $pick['platformDependencies']['PAPER'] ?? [], $fits, [], $names, $download['externalUrl'] ?? null);
        }
        if ($source === 'curseforge') {
            // Release files first; a file without downloadUrl is one its author does not allow other apps to download.
            $files = array_values(array_filter($data['data'] ?? [], fn ($f) => is_array($f) && ($f['isAvailable'] ?? true) !== false));
            usort($files, fn ($a, $b) => [(int) (($a['releaseType'] ?? 1) === 1), (string) ($a['fileDate'] ?? '')] <=> [(int) (($b['releaseType'] ?? 1) === 1), (string) ($b['fileDate'] ?? '')]);
            $files = array_reverse($files);
            $pick = null;
            foreach ($files as $f) if (self::fits($f['gameVersions'] ?? [], $minecraft, $exact)) { $pick = $f; break; }
            $fits = $pick !== null;
            $pick ??= $files[0] ?? null;
            if (!$pick) return [...$none, 'reason' => 'This project has no files on CurseForge.'];
            $filename = (string) ($pick['fileName'] ?? '');
            $version = self::fromFilename($filename)[1] ?: (string) ($pick['displayName'] ?? '');
            $result = self::result($source, $version, (string) ($pick['id'] ?? ''), $filename, (string) ($pick['downloadUrl'] ?? ''), $pick['gameVersions'] ?? [], $fits, [], [], null);
            return $result['installable'] ? $result : [...$result, 'reason' => 'The author only allows downloads on the CurseForge website.'];
        }
        // SpigotMC: Spiget only offers the newest version, and lists the versions it was tested on.
        $tested = $data['testedVersions'] ?? [];
        $name = is_array($latest) ? (string) ($latest['name'] ?? '') : '';
        $external = ($data['file']['type'] ?? '') === 'external';
        $isJar = strtolower((string) ($data['file']['type'] ?? '')) === '.jar';
        $filename = self::slug((string) ($data['name'] ?? 'plugin')).($name !== '' ? '-'.self::slug($name) : '').'.jar';
        return self::result($source, $name, (string) ($data['version']['id'] ?? ''), $isJar ? $filename : '', $isJar ? 'https://api.spiget.org/v2/resources/'.$id.'/download' : '', $tested, self::fits($tested, $minecraft, false), [], [], $external ? ($data['file']['externalUrl'] ?? null) : null);
    }

    private static function result(string $source, string $version, string $versionId, string $filename, string $url, array $versions, bool $fits, array $needs, array $needNames, mixed $external): array
    {
        $versions = array_values(array_filter($versions, 'is_string'));
        $result = ['installable' => false, 'source' => $source, 'label' => self::LABELS[$source], 'version' => mb_substr($version, 0, 60), 'version_id' => mb_substr($versionId, 0, 128),
            'filename' => $filename, 'url' => $url, 'fits' => $fits, 'for' => self::range($versions), 'needs' => $needs, 'need_names' => $needNames, 'reason' => ''];
        if (self::downloadable($url, $filename)) return [...$result, 'installable' => true];
        $release = is_string($external) ? self::releaseApi($external) : null;
        if ($release) return [...$result, 'github' => $release, 'reason' => 'Looking up the GitHub release…'];
        return [...$result, 'reason' => 'This plugin can only be downloaded from its own website.'];
    }

    /** "https://github.com/o/r/releases/tag/v1" -> the GitHub API URL of that release (or of the latest one). */
    public static function releaseApi(string $url): ?string
    {
        if (!preg_match('#^https://github\.com/([\w.-]{1,100})/([\w.-]{1,100})/releases(?:/tag/([^/?\#]{1,100}))?/?(?:[?\#].*)?$#', $url, $m)) return null;
        return 'https://api.github.com/repos/'.$m[1].'/'.$m[2].'/releases/'.(!empty($m[3]) ? 'tags/'.rawurlencode(rawurldecode($m[3])) : 'latest');
    }

    /** The main .jar of a GitHub release: not sources/javadoc/api, and the shortest name (EssentialsX, not EssentialsXChat). */
    public static function fromRelease(array $result, mixed $release): array
    {
        unset($result['github']);
        $assets = [];
        foreach (is_array($release) ? ($release['assets'] ?? []) : [] as $a) {
            $name = (string) ($a['name'] ?? '');
            if (str_ends_with(strtolower($name), '.jar') && !preg_match('/(sources|javadoc|[-_]api)[-_.]/i', $name)) $assets[] = $a;
        }
        usort($assets, fn ($a, $b) => strlen((string) $a['name']) <=> strlen((string) $b['name']));
        $asset = $assets[0] ?? null;
        if (!$asset || !self::downloadable((string) ($asset['browser_download_url'] ?? ''), (string) $asset['name'])) return [...$result, 'reason' => 'The linked GitHub release has no plugin file.'];
        return [...$result, 'installable' => true, 'via' => 'GitHub', 'filename' => (string) $asset['name'], 'url' => (string) $asset['browser_download_url'], 'version' => $result['version'] ?: ltrim((string) ($release['tag_name'] ?? ''), 'v'), 'reason' => ''];
    }

    public static function downloadable(string $url, string $filename): bool
    {
        if (!preg_match('/^[a-zA-Z0-9._+ -]{1,120}\.jar$/', $filename) || parse_url($url, PHP_URL_SCHEME) !== 'https') return false;
        $host = parse_url($url, PHP_URL_HOST);
        if (!in_array($host, self::HOSTS, true)) return false;
        return $host !== 'github.com' || preg_match('#^/[\w.-]+/[\w.-]+/releases/download/#', (string) parse_url($url, PHP_URL_PATH)) === 1;
    }

    /** ["1.20.6", "1.21", "1.21.4"] -> "1.20.6 – 1.21.4". */
    public static function range(array $versions): string
    {
        $versions = array_values(array_filter($versions, fn ($v) => preg_match('/^\d+\.\d+(\.\d+)?$/', $v) === 1));
        if (!$versions) return '';
        usort($versions, 'version_compare');
        $first = $versions[0];
        $last = end($versions);
        return $first === $last ? $first : $first.' – '.$last;
    }

    private static function slug(string $text): string
    {
        return trim(preg_replace('/[^a-zA-Z0-9._+-]+/', '-', $text) ?? '', '-.') ?: 'plugin';
    }

    /** "LuckPerms-Bukkit-5.4.141.jar" -> ['LuckPerms Bukkit', '5.4.141'] for files installed by hand. */
    public static function fromFilename(string $file): array
    {
        $base = preg_replace('/\.jar(\.disabled)?$/i', '', $file) ?? $file;
        if (preg_match('/^(.+?)[-_ ]v?(\d+(?:\.\d+)+[\w.+-]*)$/', $base, $m)) return [trim(str_replace(['-', '_'], ' ', $m[1])), $m[2]];
        return [trim(str_replace(['-', '_'], ' ', $base)), ''];
    }

    /**
     * Plugins that failed when the server started, from its log: "Could not load 'plugins/x.jar'" and other error lines
     * naming the file, or "Error occurred while enabling Name v1 (Is it up to date?)". $watched is [key => ['filename',
     * 'title']]; the result is [key => ['reason' => the log line, 'lines' => it and the lines around it]].
     */
    public static function failures(string $log, array $watched): array
    {
        $out = [];
        $lines = preg_split('/\R/', $log) ?: [];
        foreach ($lines as $index => $line) {
            if (!preg_match('/\b(ERROR|SEVERE|FATAL)\b|Error occurred while enabling|Could not load/i', $line)) continue;
            $enabling = preg_match('/(?:Error occurred while enabling|Could not load plugin) \'?([^\'\s]+)/i', $line, $m) ? self::same($m[1]) : '';
            foreach ($watched as $key => $t) {
                if (isset($out[$key])) continue;
                $file = (string) ($t['filename'] ?? '');
                $names = array_filter([self::same((string) ($t['title'] ?? '')), self::same(self::fromFilename($file)[0])], fn ($n) => strlen($n) >= 3);
                $named = $enabling !== '' && strlen($enabling) >= 3 && array_filter($names, fn ($n) => str_starts_with($n, $enabling) || str_starts_with($enabling, $n));
                if (($file !== '' && str_contains($line, $file)) || $named) $out[$key] = $index;
            }
        }
        // The line itself (without its time stamp) and the lines around it, the cause and first stack frames, for Explain.
        return array_map(fn ($at) => ['reason' => mb_substr(trim(preg_replace('/^\[[^\]]*\]:?\s*/', '', $lines[$at]) ?? $lines[$at]), 0, 240),
            'lines' => array_map(fn ($l) => mb_substr($l, 0, 400), array_slice($lines, max(0, $at - 2), 40))], $out);
    }
}

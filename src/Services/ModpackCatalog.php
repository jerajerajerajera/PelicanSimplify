<?php

declare(strict_types=1);

namespace Simplify\Panel\Services;

use App\Models\Egg;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Throwable;

/** Normalized cards and installation adapters; provider IDs never share a namespace. */
final class ModpackCatalog
{
    public static function split(?string $key): ?array
    {
        if (!$key || !preg_match('/^(modrinth|ftb|technic|curseforge):([a-zA-Z0-9_-]{1,128})$/', $key, $parts)) {
            return null;
        }
        if (in_array($parts[1], ['ftb', 'curseforge'], true) && !ctype_digit($parts[2])) {
            return null;
        }
        return [$parts[1], $parts[2]];
    }

    public static function json(string $url, array $query = [], array $headers = []): array
    {
        try {
            $response = Http::acceptJson()->connectTimeout(2)->timeout(6)
                ->withHeaders(['User-Agent' => 'PelicanSimplify/0.2.0', ...$headers])->get($url, $query);
            $data = $response->successful() ? $response->json() : null;
            return is_array($data) ? $data : [];
        } catch (Throwable) {
            return [];
        }
    }

    public static function safeUrl(mixed $url): ?string
    {
        return is_string($url) && filter_var($url, FILTER_VALIDATE_URL) && parse_url($url, PHP_URL_SCHEME) === 'https' && !parse_url($url, PHP_URL_USER)
            ? $url : null;
    }

    public static function art(array $data): ?string
    {
        foreach ($data['art'] ?? [] as $art) {
            if (($art['type'] ?? '') === 'square' && ($url = self::safeUrl($art['url'] ?? null))) {
                return $url;
            }
        }
        return null;
    }

    public static function card(string $provider, string $id, array $data): array
    {
        $slug = (string) ($data['slug'] ?? $id);
        $source = ['modrinth' => 'Modrinth', 'ftb' => 'FTB', 'curseforge' => 'CurseForge'][$provider] ?? 'Technic';
        return [
            'key' => $provider . ':' . $id, 'provider' => $provider, 'source' => $source, 'id' => $id,
            'title' => mb_substr((string) ($data['title'] ?? $data['name'] ?? $id), 0, 100),
            'description' => mb_substr(trim(strip_tags((string) ($data['synopsis'] ?? $data['summary'] ?? $data['description'] ?? ''))), 0, 220),
            'icon_url' => self::safeUrl($data['icon_url'] ?? $data['logo']['thumbnailUrl'] ?? null) ?? self::art($data),
            'url' => match ($provider) {
                'modrinth' => 'https://modrinth.com/modpack/' . rawurlencode($slug),
                'ftb' => 'https://www.feed-the-beast.com/modpacks/' . rawurlencode($id . '-' . $slug),
                'curseforge' => self::safeUrl($data['links']['websiteUrl'] ?? null) ?? 'https://www.curseforge.com/minecraft/modpacks/' . rawurlencode($slug),
                default => 'https://www.technicpack.net/',
            },
        ];
    }

    public static function technic(): array
    {
        $path = plugin_path('pelican-simplify', 'resources/catalog/technic.json');
        return json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }

    public static function ftbData(string $id): array
    {
        return Cache::remember('ssc.ftb.pack.' . $id, 1800, function () use ($id): array {
            $data = self::json('https://api.feed-the-beast.com/v1/modpacks/public/modpack/' . $id);
            if (!$data || ($data['status'] ?? '') === 'error') {
                $data = self::json('https://api.modpacks.ch/public/modpack/' . $id);
            }
            return (string) ($data['id'] ?? '') === $id && !empty($data['name']) ? $data : [];
        });
    }

    public static function curseForgeKey(): string
    {
        // Set in Pelican's environment; never exposed in cards or notifications.
        return (string) (config('services.curseforge.key') ?: getenv('SSC_CURSEFORGE_API_KEY') ?: env('SSC_CURSEFORGE_API_KEY', ''));
    }

    /** Three popular picks when empty; one mixed list when searching. Selected pack stays pinned. */
    public static function results(?string $query = '', ?string $selected = null): array
    {
        $query = mb_substr(trim((string) $query), 0, 60);
        // One or two letters are not a useful search; show the popular picks until a real term is typed.
        if (mb_strlen($query) < 3) $query = '';
        $results = Cache::remember('ssc.modpacks.search.v1.' . hash('sha256', mb_strtolower($query) . (self::curseForgeKey() !== '' ? ':cf' : '')), 600,
            function () use ($query): array {
                $mr = self::json('https://api.modrinth.com/v2/search', ['query' => $query, 'index' => $query === '' ? 'downloads' : 'relevance', 'limit' => 6, 'facets' => '[["project_type:modpack"]]']);
                $modrinth = [];
                foreach ($mr['hits'] ?? [] as $hit) {
                    if (!empty($hit['project_id'])) {
                        $modrinth[] = self::card('modrinth', (string) $hit['project_id'], $hit);
                    }
                }
                $ftb = [];
                $ftbSearch = $query === ''
                    ? self::json('https://api.feed-the-beast.com/v1/modpacks/public/modpack/popular/installs/3')
                    : self::json('https://api.feed-the-beast.com/v1/modpacks/public/modpack/search/8', ['term' => $query]);
                if (!$ftbSearch) {
                    $ftbSearch = $query === ''
                        ? self::json('https://api.modpacks.ch/public/modpack/popular/installs/3')
                        : self::json('https://api.modpacks.ch/public/modpack/search/8', ['term' => $query]);
                }
                foreach (array_slice($ftbSearch['packs'] ?? [], 0, $query === '' ? 1 : 6) as $id) {
                    if (!ctype_digit((string) $id)) {
                        continue;
                    }
                    $pack = self::ftbData((string) $id);
                    if ($pack) {
                        $ftb[] = self::card('ftb', (string) $id, $pack);
                    }
                }
                $technic = array_values(array_filter(self::technic(), fn ($pack) => $query === '' || str_contains(mb_strtolower($pack['title'].' '.'Technic'.' '.$pack['description']), mb_strtolower($query))));
                $cf = [];
                if (self::curseForgeKey() !== '' && $query !== '') {
                    $data = self::json('https://api.curseforge.com/v1/mods/search', ['gameId' => 432, 'classId' => 4471, 'searchFilter' => $query, 'pageSize' => 6], ['x-api-key' => self::curseForgeKey()]);
                    foreach ($data['data'] ?? [] as $pack) {
                        if (!empty($pack['id'])) {
                            $cf[] = self::card('curseforge', (string) $pack['id'], $pack);
                        }
                    }
                }
                if ($query === '') {
                    $results = array_filter([$modrinth[0] ?? null, $ftb[0] ?? null, $technic[0] ?? null]);
                    foreach ($modrinth as $pack) {
                        if (count($results) >= 3) {
                            break;
                        }
                        if (!in_array($pack, $results, true)) {
                            $results[] = $pack;
                        }
                    }
                } else {
                    $results = [];
                    // Interleave providers instead of hiding later sources below a long first list.
                    for ($index = 0; $index < 10; $index++) {
                        foreach ([$modrinth, $ftb, $technic, $cf] as $source) {
                            if (isset($source[$index])) {
                                $results[] = $source[$index];
                            }
                        }
                    }
                }
                return array_values($results);
            });
        if ($selected && !in_array($selected, array_column($results, 'key'), true) && ($pack = self::pack($selected))) {
            array_unshift($results, $pack);
        }
        return $results;
    }

    public static function pack(?string $key): ?array
    {
        $parts = self::split($key);
        if (!$parts) {
            return null;
        }
        [$provider, $id] = $parts;
        return Cache::remember('ssc.modpacks.pack.' . $key, 1800, function () use ($provider, $id): array {
            if ($provider === 'technic') {
                foreach (self::technic() as $pack) {
                    if ($pack['id'] === $id) {
                        return $pack;
                    }
                }
                return [];
            }
            $data = match ($provider) {
                'ftb' => self::ftbData($id),
                'modrinth' => self::json('https://api.modrinth.com/v2/project/' . rawurlencode($id)),
                'curseforge' => self::json('https://api.curseforge.com/v1/mods/' . $id, [], ['x-api-key' => self::curseForgeKey()])['data'] ?? [],
            };
            if (!$data || ($provider === 'modrinth' && ($data['project_type'] ?? '') !== 'modpack') || ($provider === 'curseforge' && (int) ($data['classId'] ?? 0) !== 4471)) {
                return [];
            }
            return self::card($provider, $id, $data);
        }) ?: null;
    }

    public static function versions(?string $key): array
    {
        if (!($parts = self::split($key))) {
            return [];
        }
        [$provider, $id] = $parts;
        return Cache::remember('ssc.modpacks.versions.' . $key, 600, function () use ($provider, $id, $key): array {
            if ($provider === 'technic') {
                $pack = self::pack($key);
                if (!$pack) {
                    return [];
                }
                // Only expose the egg's tested server-build default, not arbitrary client builds.
                return ['egg_default' => __('pelican-simplify::maker.modpack.egg_default')];
            }
            $items = match ($provider) {
                'modrinth' => self::json('https://api.modrinth.com/v2/project/' . $id . '/version'),
                'ftb' => self::ftbData($id)['versions'] ?? [],
                'curseforge' => self::json('https://api.curseforge.com/v1/mods/' . $id . '/files', ['pageSize' => 50], ['x-api-key' => self::curseForgeKey()])['data'] ?? [],
            };
            if ($provider !== 'modrinth') {
                usort($items, fn ($a, $b) => ($b['updated'] ?? strtotime($b['fileDate'] ?? '') ?: 0) <=> ($a['updated'] ?? strtotime($a['fileDate'] ?? '') ?: 0));
            }
            $versions = [];
            foreach (array_slice($items, 0, 60) as $item) {
                if (empty($item['id']) || !empty($item['private']) || ($item['type'] ?? '') === 'archived') {
                    continue;
                }
                if ($provider === 'curseforge' && empty($item['isServerPack']) && empty($item['serverPackFileId'])) {
                    continue;
                }
                $id = (string) $item['id'];
                $versions[$id] = (string) ($item['version_number'] ?? $item['displayName'] ?? $item['name'] ?? $id);
            }
            return $versions;
        });
    }

    public static function runtime(?string $key, ?string $version): array
    {
        if (!($parts = self::split($key)) || !$version) {
            return [];
        }
        [$provider, $id] = $parts;
        return Cache::remember('ssc.modpacks.runtime.' . $key . ':' . $version, 600, function () use ($provider, $id, $key, $version): array {
            if ($provider === 'technic') {
                $pack = self::pack($key);
                return $pack && $version === 'egg_default' ? ['minecraft' => $pack['minecraft'], 'java' => $pack['java']] : [];
            }
            if ($provider === 'ftb') {
                foreach (self::ftbData($id)['versions'] ?? [] as $item) {
                    if ((string) ($item['id'] ?? '') !== $version) {
                        continue;
                    }
                    $runtime = [];
                    foreach ($item['targets'] ?? [] as $target) {
                        if (($target['name'] ?? '') === 'minecraft') {
                            $runtime['minecraft'] = (string) $target['version'];
                        }
                        if (($target['name'] ?? '') === 'java' && preg_match('/^(?:1\.)?(\d+)/', (string) $target['version'], $match)) {
                            $runtime['java'] = (int) $match[1];
                        }
                    }
                    return $runtime;
                }
                return [];
            }
            $item = $provider === 'modrinth'
                ? self::json('https://api.modrinth.com/v2/version/' . rawurlencode($version))
                : (self::json('https://api.curseforge.com/v1/mods/' . $id . '/files/' . rawurlencode($version), [], ['x-api-key' => self::curseForgeKey()])['data'] ?? []);
            if ((string) ($item[$provider === 'modrinth' ? 'project_id' : 'modId'] ?? '') !== $id) {
                return [];
            }
            $games = array_values(array_filter($item[$provider === 'modrinth' ? 'game_versions' : 'gameVersions'] ?? [], fn ($v) => is_string($v) && preg_match('/^\d+\.\d+(?:\.\d+)?$/', $v)));
            usort($games, fn ($a, $b) => version_compare($b, $a));
            return $games ? ['minecraft' => $games[0]] : [];
        });
    }

    public static function egg(?string $key): ?Egg
    {
        $parts = self::split($key);
        if (!$parts) {
            return null;
        }
        $names = match ($parts[0]) {
            'modrinth' => ['modrinth generic', 'modrinth'],
            'ftb' => ['ftb server'],
            'curseforge' => ['curseforge generic', 'curse forge generic'],
            'technic' => [mb_strtolower((string) (self::pack($key)['egg_name'] ?? ''))],
        };
        foreach ($names as $name) {
            if ($name !== '' && ($egg = Egg::query()->whereRaw('LOWER(name) = ?', [$name])->first())) {
                return $egg;
            }
        }
        return null;
    }

    public static function applyVariables(Egg $egg, array $state, array $env): array
    {
        $key = (string) ($state['modpack_project'] ?? '');
        $version = (string) ($state['modpack_version'] ?? '');
        $parts = self::split($key);
        if (!$parts || !self::pack($key) || !array_key_exists($version, self::versions($key))) {
            throw ValidationException::withMessages(['data.modpack_project' => __('pelican-simplify::maker.modpack.choose_a_valid_modpack_and_server')]);
        }
        [$provider, $id] = $parts;
        $required = match ($provider) {
            'ftb' => ['FTB_MODPACK_ID', 'FTB_MODPACK_VERSION_ID'],
            'technic' => ['MODPACK_VERSION'],
            default => ['PROJECT_ID', 'VERSION_ID'],
        };
        foreach ($required as $variable) {
            if (!array_key_exists($variable, $env)) {
                throw ValidationException::withMessages(['data.egg_id' => __('pelican-simplify::maker.modpack.needs_egg', ['provider' => $provider])]);
            }
        }
        if ($provider === 'technic') {
            $expected = self::pack($key)['egg_name'];
            if (mb_strtolower($egg->name) !== mb_strtolower($expected)) {
                throw ValidationException::withMessages(['data.egg_id' => __('pelican-simplify::maker.modpack.select_egg', ['egg' => $expected])]);
            }
            // Preserve the installed egg's server-build default exactly (including v prefixes).
            return $env;
        }
        if ($provider === 'ftb') {
            $env['FTB_MODPACK_ID'] = $id;
            $env['FTB_MODPACK_VERSION_ID'] = $version;
        } else {
            $env['PROJECT_ID'] = $id;
            $env['VERSION_ID'] = $version;
        }
        if ($provider === 'curseforge') {
            if (self::curseForgeKey() === '' || !array_key_exists('API_KEY', $env)) {
                throw ValidationException::withMessages(['data.modpack_project' => __('pelican-simplify::maker.modpack.configure_the_curseforge_api_key_and')]);
            }
            $env['API_KEY'] = self::curseForgeKey();
        }
        return $env;
    }
}

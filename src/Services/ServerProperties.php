<?php

declare(strict_types=1);

namespace Simplify\Panel\Services;

use Illuminate\Validation\ValidationException;

final class ServerProperties
{
    public static function hasSettings(array $state): bool
    {
        return (bool) ($state['properties_enabled'] ?? false) || !empty($state['server_icon']);
    }

    public static function prepare(array $state, bool $requireAsync = true): array
    {
        if (!self::hasSettings($state)) {
            return [];
        }
        if ($requireAsync && in_array(config('queue.default'), ['sync', 'null'], true)) {
            throw ValidationException::withMessages(['data.properties_enabled' => __('pelican-simplify::maker.properties.configure_an_asynchronous_pelican_queue_worker')]);
        }
        if ($state['skip_scripts'] ?? false) {
            throw ValidationException::withMessages(['data.skip_scripts' => __('pelican-simplify::maker.properties.installation_scripts_must_run_to_apply')]);
        }
        $values = [];
        if ($state['properties_enabled'] ?? false) {
            foreach (['max_players' => [1, 1000], 'view_distance' => [2, 32], 'spawn_protection' => [0, 1000]] as $key => [$min, $max]) {
                $number = filter_var($state[$key] ?? null, FILTER_VALIDATE_INT);
                if ($number === false || $number < $min || $number > $max) {
                    throw ValidationException::withMessages(['data.' . $key => __('pelican-simplify::maker.properties.number_between', ['min' => $min, 'max' => $max])]);
                }
                $values[str_replace('_', '-', $key)] = (string) $number;
            }
            foreach (['difficulty' => ['peaceful', 'easy', 'normal', 'hard'], 'gamemode' => ['survival', 'creative', 'adventure', 'spectator']] as $key => $allowed) {
                if (!in_array($state[$key] ?? '', $allowed, true)) {
                    throw ValidationException::withMessages(['data.' . $key => __('pelican-simplify::maker.properties.choose_valid_'.$key)]);
                }
                $values[$key] = (string) $state[$key];
            }
            // Older Minecraft releases use numeric values for these enums.
            $mc = JavaRuntime::minecraftVersion($state);
            if ($mc && version_compare($mc, '1.13', '<')) {
                $values['difficulty'] = (string) array_search($values['difficulty'], ['peaceful', 'easy', 'normal', 'hard'], true);
                $values['gamemode'] = (string) array_search($values['gamemode'], ['survival', 'creative', 'adventure', 'spectator'], true);
            }
            foreach (['pvp', 'white_list', 'allow_flight'] as $key) {
                $values[str_replace('_', '-', $key)] = ($state[$key] ?? false) ? 'true' : 'false';
            }
            $motd = (string) ($state['motd'] ?? '');
            if (mb_strlen($motd) > 512) {
                throw ValidationException::withMessages(['data.motd' => __('pelican-simplify::maker.properties.motd_can_contain_at_most_512')]);
            }
            $values['motd'] = $motd;
        }
        $icon = null;
        $upload = $state['server_icon'] ?? null;
        if ($upload) {
            if (!$upload instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
                throw ValidationException::withMessages(['data.server_icon' => __('pelican-simplify::maker.properties.upload_the_icon_again')]);
            }
            $content = file_get_contents($upload->getRealPath());
            $size = $content !== false ? @getimagesizefromstring($content) : false;
            if (!$size || $size[0] !== 64 || $size[1] !== 64 || $size[2] !== IMAGETYPE_PNG || strlen($content) > 102400) {
                throw ValidationException::withMessages(['data.server_icon' => __('pelican-simplify::maker.properties.the_icon_must_be_a_64')]);
            }
            $icon = base64_encode($content);
        }
        return ['properties' => $values, 'icon' => $icon];
    }

    public static function escape(string $value): string
    {
        $value = str_replace(['\\', "\r", "\n", "\t"], ['\\\\', '\\r', '\\n', '\\t'], $value);
        $value = preg_replace_callback('/^ +/', fn (array $match) => str_repeat('\\ ', strlen($match[0])), $value);
        return preg_replace_callback('/[^\x20-\x7e]/u', function (array $match): string {
            $units = unpack('n*', mb_convert_encoding($match[0], 'UTF-16BE', 'UTF-8'));
            return implode('', array_map(fn ($unit) => sprintf('\\u%04x', $unit), $units));
        }, $value);
    }

    /** Replace only selected keys, preserving unrelated installer values and comments. */
    public static function merge(string $original, array $values): string
    {
        $lines = preg_split('/\r\n|\n|\r/', $original);
        $result = [];
        $seen = [];
        $continuation = false;
        $skip = false;
        foreach ($lines as $line) {
            if (!$continuation) {
                $skip = false;
                if (preg_match('/^\s*([^#!\s=:]+)\s*(?:[=:]|\s)\s*/', $line, $match) && array_key_exists($match[1], $values)) {
                    $key = $match[1];
                    $skip = true;
                    if (!isset($seen[$key])) {
                        $result[] = $key . '=' . self::escape((string) $values[$key]);
                        $seen[$key] = true;
                    }
                }
            }
            if (!$skip) {
                $result[] = $line;
            }
            $slashes = strlen($line) - strlen(rtrim($line, '\\'));
            $continuation = $slashes % 2 === 1 && !preg_match('/^\s*[#!]/', $line);
        }
        foreach ($values as $key => $value) {
            if (!isset($seen[$key])) {
                $result[] = $key . '=' . self::escape((string) $value);
            }
        }
        return rtrim(implode("\n", $result), "\n") . "\n";
    }
}

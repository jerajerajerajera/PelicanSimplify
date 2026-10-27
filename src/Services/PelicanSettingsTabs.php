<?php

declare(strict_types=1);

namespace Simplify\Panel\Services;

use Illuminate\Support\Str;

/**
 * Pelican's own settings page (General, Captcha, Mail, Backup, OAuth, Misc) shown inside Simplify settings: one tab
 * per Pelican tab, each opening Pelican's page in a frame on that tab. Pelican keeps its form, validation and save.
 */
final class PelicanSettingsTabs
{
    private const PAGE = 'App\\Filament\\Admin\\Pages\\Settings';
    /** Pelican's tabs today, used when the page cannot list its own. */
    private const FALLBACK = [
        ['General', 'tabler-home'], ['Captcha', 'tabler-shield'], ['Mail', 'tabler-mail'],
        ['Backup', 'tabler-box'], ['OAuth', 'tabler-brand-oauth'], ['Misc', 'tabler-tool'],
    ];

    public static function available(): bool
    {
        return class_exists(self::PAGE) && (bool) user()?->isRootAdmin();
    }

    /** @return list<array{key: string, label: string, icon: ?string, url: string}> */
    public static function all(): array
    {
        if (!self::available()) return [];
        $page = self::PAGE;
        try {
            $base = (string) $page::getUrl(panel: 'admin');
        } catch (\Throwable) {
            return [];
        }
        $tabs = [];
        try {
            $instance = app($page);
            foreach ((array) (fn () => $this->getTabs())->call($instance) as $tab) {
                if (!$tab instanceof \Filament\Schemas\Components\Tabs\Tab) continue;
                $label = trim((string) $tab->getLabel());
                if ($label === '') continue;
                $icon = $tab->getIcon();
                $tabs[] = ['label' => $label, 'icon' => $icon instanceof \BackedEnum ? (string) $icon->value : (is_string($icon) ? $icon : null)];
            }
        } catch (\Throwable) {
            $tabs = [];
        }
        if (!$tabs) $tabs = array_map(fn (array $tab) => ['label' => $tab[0], 'icon' => $tab[1]], self::FALLBACK);
        $seen = [];
        $out = [];
        foreach ($tabs as $tab) {
            $key = Str::slug($tab['label']) ?: 'tab';
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $out[] = ['key' => $key, 'label' => $tab['label'], 'icon' => $tab['icon'], 'url' => self::url($base, $tab['label'])];
        }
        return $out;
    }

    /** Pelican's page with the embed marker and the tab preselected (Filament keeps the tab key in the query string). */
    public static function url(string $base, string $label): string
    {
        $tab = Str::slug(Str::transliterate($label, strict: true)).'::data::tab';
        return $base.(str_contains($base, '?') ? '&' : '?').http_build_query(['simplify_embed' => 1, 'tab' => $tab]);
    }
}

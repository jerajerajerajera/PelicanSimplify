<?php
declare(strict_types=1);
namespace Simplify\Panel\Services;

use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentColor;

/**
 * Detects a theme from another plugin (Nord, Catppuccin, Pterodactyl, Theme Customizer, or the panel's theme switcher):
 * such a theme changes the panel's Filament colours, font, light/dark mode or stylesheet. Simplify then takes its
 * palette from Filament's colour variables (tokens.css .ps-panel-theme) so it matches the rest of the panel.
 */
final class PanelTheme
{
    /** Pelican's own colours and font (FilamentServiceProvider); anything else comes from a theme. */
    private const PELICAN = ['gray' => 'Zinc', 'primary' => 'Blue', 'success' => 'Green', 'warning' => 'Amber', 'danger' => 'Red', 'info' => 'Sky'];
    private const PELICAN_FONTS = ['Inter', 'Inter Variable'];

    /** @return array{active: bool, mode: ?string, own_css: bool} */
    public static function state(): array
    {
        $none = ['active' => false, 'mode' => null, 'own_css' => false];
        try {
            if (!FeatureSettings::enabled('follow_panel_theme')) return $none;
            $panel = filament()->getCurrentPanel();
            if (!$panel) return $none;
            $colors = FilamentColor::getColors();
            $changed = false;
            foreach (self::PELICAN as $name => $palette) {
                if (isset($colors[$name][500]) && $colors[$name][500] !== constant(Color::class.'::'.$palette)[500]) $changed = true;
            }
            $vite = $panel->getViteTheme();
            $ownCss = collect((array) $vite)->contains(fn ($path) => str_starts_with((string) $path, 'plugins/'));
            $mode = !$panel->hasDarkMode() ? 'light' : ($panel->hasDarkModeForced() ? 'dark' : null);
            $font = !in_array($panel->getFontFamily(), self::PELICAN_FONTS, true);
            return ['active' => $changed || $ownCss || $font || $mode !== null, 'mode' => $mode, 'own_css' => $ownCss];
        } catch (\Throwable) {
            return $none;
        }
    }
}

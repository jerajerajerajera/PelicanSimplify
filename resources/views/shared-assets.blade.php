@php
    $ownPage = request()->routeIs('filament.app.pages.simplify', 'filament.app.pages.simple-server-creator', 'filament.admin.pages.simple-server-creator', '*.pages.pelican-simplify-settings');
    $config = [
        ...\Simplify\Panel\Services\FeatureSettings::effective(),
        ...\Simplify\Panel\Services\AppearanceSettings::all(),
        'web_app' => ['worker' => route('simplify.worker').'?v='.\Simplify\Panel\Services\WebApp::version(), 'scope' => \Simplify\Panel\Services\WebApp::scope()],
        'panel_theme' => \Simplify\Panel\Services\PanelTheme::state(),
        'legacy_page' => !$ownPage || (request()->routeIs('*.pages.pelican-simplify-settings') && !request()->boolean('simplify_embed')),
    ];
@endphp
{{-- Installable web app (home screen): manifest, icons and status bar colours. Part of the full Simplify panel only. --}}
@unless(\Simplify\Panel\Services\FeatureSettings::addonsOnly())
<link rel="manifest" href="{{ route('simplify.manifest') }}">
<meta name="theme-color" media="(prefers-color-scheme: light)" content="{{ \Simplify\Panel\Services\WebApp::LIGHT }}">
<meta name="theme-color" media="(prefers-color-scheme: dark)" content="{{ \Simplify\Panel\Services\WebApp::DARK }}">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="{{ \Simplify\Panel\Services\WebApp::name() }}">
<link rel="apple-touch-icon" href="{{ \Simplify\Panel\Services\WebApp::icon(180) }}">
@endunless
<link id="simplify-core-css" rel="stylesheet" href="{{ \Simplify\Panel\Services\BuildInfo::asset('core.css') }}">
<script>window.simplifyThemeConfig=@js($config);</script>
<script src="{{ \Simplify\Panel\Services\BuildInfo::asset('core.js') }}"></script>

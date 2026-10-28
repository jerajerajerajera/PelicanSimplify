<?php
return [
    'health' => [
        'ok' => ': OK',
    ],
    'system' => [
        'let_s_get_simplify_ready' => 'Lad os gøre Simplify klar',
        'a_quick_check_of_your_panel' => 'Et hurtigt tjek af dit panel, før du vælger dine indstillinger.',
        'checking_your_panel' => 'Tjekker dit panel…',
        'view_checks' => 'Se tjek',
        'keep_your_queue_worker_and_scheduler' => 'Hold din queue worker og scheduler kørende. Deres aktuelle status kan ikke kontrolleres her.',
        'check_again' => 'Tjek igen',
        'go_to_first_setup' => 'Gå til første opsætning',
        'preparing_your_system_checks' => 'Forbereder dine systemtjek…',
        'close_system_checks' => 'Luk systemtjek',
        'first_setup_eyebrow' => 'Første opsætning · Systemtjek',
        'diagnostics' => 'Systemdiagnose',
        'panel_ready' => 'Dit panel er klar',
        'needs_attention_some' => 'Et par ting kræver opmærksomhed',
        'passed' => ':passed af :total tjek bestået',
        'ready' => 'Klar',
        'needs_attention' => 'Kræver opmærksomhed',
        'warning_optional' => 'Advarsel · valgfrit',
    ],
    'requirements' => [
        'php' => [
            'name' => 'PHP 8.3 eller nyere',
            'fix' => 'Opgrader PHP for både webserveren og queue worker til PHP 8.3 eller nyere.',
        ],
        'php_extension' => [
            'name' => 'PHP-udvidelse: :extension',
            'fix' => 'Aktivér udvidelsen :extension i den PHP-runtime, som panelet og queue worker bruger, og genstart derefter disse tjenester.',
        ],
        'pelican' => [
            'name' => 'Pelican-applikation og Composer-afhængigheder',
            'fix' => 'Gør Pelican-installationen færdig, og installer dens Composer-afhængigheder.',
        ],
        'composer' => [
            'name' => 'Composer: :class',
            'fix' => 'Installer de Composer-afhængigheder, der passer til din Pelican-version.',
        ],
        'tabler' => [
            'name' => 'Tabler-ikonkomponenter',
            'fix' => 'Gendan Composer-afhængigheden blade-tabler-icons, og ryd Blade-visningscachen.',
        ],
        'node' => [
            'name' => 'Node.js 22.12+ / 24+',
            'fix' => 'Installer Node.js 22.12 eller nyere (eller Node.js 24+). Gør node tilgængelig i PATH for webserveren og queue worker.',
        ],
        'yarn' => [
            'name' => 'Yarn',
            'fix' => 'Installer Yarn, eller aktivér det via Corepack. Gør yarn tilgængelig i PATH for webserveren og queue worker.',
        ],
        'writable' => [
            'name' => 'Skrivbar :directory',
            'fix' => 'Giv panelet og queue worker læse-/skriveadgang til :directory.',
        ],
        'plugin_asset' => [
            'name' => 'Plugin-fil: :file',
            'fix' => 'Importér hele PelicanSimplify-pakken igen; denne CSS-/JavaScript-fil mangler, er tom eller kan ikke læses.',
        ],
        'legacy_creator' => [
            'name' => 'Separat creator udfaset',
            'fix' => 'Deaktivér og slet den tidligere separate creator (simple-server-creator) under Admin → Plugins, før du aktiverer den samlede creator.',
        ],
        'built_assets' => [
            'name' => 'Bygget Pelican-CSS og -JavaScript',
            'fix' => 'Byg panelets filer igen med yarn install og yarn build i din Pelican-mappe. :errors',
        ],
        'tested_release' => [
            'name' => 'Testet Pelican-version',
            'fix' => 'Simplify er testet på Pelican :tested; dette panel melder :release. Installationen fortsætter, men rapportér problemer på GitHub.',
            'no_version' => 'ingen version',
        ],
        'database' => [
            'name' => 'Databaseforbindelse',
            'fix' => 'Reparér panelets databaseforbindelse, før du fortsætter.',
        ],
        'cache' => [
            'name' => 'Cache læse/skrive',
            'fix' => 'Reparér den konfigurerede cache-backend og dens rettigheder.',
        ],
        'storage' => [
            'name' => 'Lager til plugin-indstillinger',
            'fix' => 'Reparér den lokale Laravel-lagerdisk, og giv panelet skriveadgang.',
        ],
        'queue' => [
            'name' => 'Baggrundskø konfigureret',
            'fix' => 'Konfigurér en vedvarende Pelican-køforbindelse, og start dens queue worker.',
        ],
    ],
    'assets' => [
        'manifest' => 'Build-manifestet mangler eller er ugyldigt.',
        'missing_entry' => 'Manglende indgang: :source.',
        'invalid_entry' => 'Ugyldig build-indgang.',
        'invalid_css' => 'Ugyldig CSS-liste.',
        'invalid_path' => 'Ugyldig sti til build-fil.',
        'missing_asset' => 'Manglende eller tom fil: :file.',
        'invalid_imports' => 'Ugyldige build-imports.',
        'missing_import' => 'Manglende importeret build-indgang.',
        'no_css' => 'Ingen kompileret CSS fundet.',
        'no_js' => 'Ingen kompileret JavaScript fundet.',
    ],
    'uploads' => [
        'runtime_cli' => 'CLI-PHP (kontrollér PHP-FPM separat)',
        'runtime_web' => 'Web-PHP',
        'capacity' => 'Uploadkapacitet · :size til bannere',
        'capacity_detail' => ':runtime: upload_max_filesize=:upload; post_max_size=:post. Banneruploads begrænses til den laveste PHP-/Livewire-grænse (højst 5 MB).',
        'capacity_fix' => 'For at tillade bannere på 5 MB skal du sætte PHP-FPM upload_max_filesize = 10M og post_max_size = 12M, tjekke eventuelle Livewire-max-regler og derefter genindlæse PHP-FPM.',
        'proxy' => 'Uploadgrænse i NGINX / proxy · ikke kontrolleret',
        'proxy_detail' => 'En proxy kan afvise uploads, før PHP modtager dem (HTTP 413), selv når tjekkene ovenfor er bestået.',
        'proxy_fix' => 'Sæt client_max_body_size 12m i den NGINX-serverblok, der leverer dette panel; tjek for lavere location-overstyringer og upstream-proxygrænser. Kør sudo nginx -t og derefter sudo systemctl reload nginx. Kontrollér med en rigtig upload. Ingen serverkonfiguration ændres automatisk.',
    ],
];

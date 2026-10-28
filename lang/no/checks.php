<?php
return [
    'health' => [
        'ok' => ': OK',
    ],
    'system' => [
        'let_s_get_simplify_ready' => 'La oss gjøre Simplify klar',
        'a_quick_check_of_your_panel' => 'En rask sjekk av panelet ditt før du velger innstillingene dine.',
        'checking_your_panel' => 'Sjekker panelet ditt…',
        'view_checks' => 'Vis sjekker',
        'keep_your_queue_worker_and_scheduler' => 'Hold queue worker og scheduler i gang. Den aktuelle statusen deres kan ikke kontrolleres her.',
        'check_again' => 'Sjekk igjen',
        'go_to_first_setup' => 'Gå til første oppsett',
        'preparing_your_system_checks' => 'Forbereder systemsjekkene dine…',
        'close_system_checks' => 'Lukk systemsjekker',
        'first_setup_eyebrow' => 'Første oppsett · Systemsjekk',
        'diagnostics' => 'Systemdiagnostikk',
        'panel_ready' => 'Panelet ditt er klart',
        'needs_attention_some' => 'Noen ting trenger oppmerksomhet',
        'passed' => ':passed av :total sjekker bestått',
        'ready' => 'Klar',
        'needs_attention' => 'Trenger oppmerksomhet',
        'warning_optional' => 'Advarsel · valgfritt',
    ],
    'requirements' => [
        'php' => [
            'name' => 'PHP 8.3 eller nyere',
            'fix' => 'Oppgrader PHP for både webserveren og queue worker til PHP 8.3 eller nyere.',
        ],
        'php_extension' => [
            'name' => 'PHP-utvidelse: :extension',
            'fix' => 'Aktiver utvidelsen :extension i PHP-runtimen som panelet og queue worker bruker, og start deretter disse tjenestene på nytt.',
        ],
        'pelican' => [
            'name' => 'Pelican-applikasjon og Composer-avhengigheter',
            'fix' => 'Fullfør Pelican-installasjonen og installer Composer-avhengighetene.',
        ],
        'composer' => [
            'name' => 'Composer: :class',
            'fix' => 'Installer Composer-avhengighetene som passer til Pelican-versjonen din.',
        ],
        'tabler' => [
            'name' => 'Tabler-ikonkomponenter',
            'fix' => 'Gjenopprett Composer-avhengigheten blade-tabler-icons og tøm Blade-visningsbufferen.',
        ],
        'node' => [
            'name' => 'Node.js 22.12+ / 24+',
            'fix' => 'Installer Node.js 22.12 eller nyere (eller Node.js 24+). Gjør node tilgjengelig i PATH for webserveren og queue worker.',
        ],
        'yarn' => [
            'name' => 'Yarn',
            'fix' => 'Installer Yarn eller aktiver det via Corepack. Gjør yarn tilgjengelig i PATH for webserveren og queue worker.',
        ],
        'writable' => [
            'name' => 'Skrivbar :directory',
            'fix' => 'Gi panelet og queue worker lese- og skrivetilgang til :directory.',
        ],
        'plugin_asset' => [
            'name' => 'Plugin-fil: :file',
            'fix' => 'Importer hele PelicanSimplify-pakken på nytt; denne CSS-/JavaScript-filen mangler, er tom eller kan ikke leses.',
        ],
        'legacy_creator' => [
            'name' => 'Frittstående oppretter avviklet',
            'fix' => 'Deaktiver og slett den tidligere frittstående oppretteren (simple-server-creator) under Admin → Plugins før du aktiverer den samlede oppretteren.',
        ],
        'built_assets' => [
            'name' => 'Bygget CSS og JavaScript for Pelican',
            'fix' => 'Bygg panelets filer på nytt med yarn install og yarn build i Pelican-mappen din. :errors',
        ],
        'tested_release' => [
            'name' => 'Testet Pelican-versjon',
            'fix' => 'Simplify er testet på Pelican :tested; dette panelet rapporterer :release. Installasjonen fortsetter, men rapporter problemer på GitHub.',
            'no_version' => 'ingen versjon',
        ],
        'database' => [
            'name' => 'Databasetilkobling',
            'fix' => 'Reparer panelets databasetilkobling før du fortsetter.',
        ],
        'cache' => [
            'name' => 'Hurtigbuffer lese/skrive',
            'fix' => 'Reparer den konfigurerte hurtigbuffer-backenden og tillatelsene for den.',
        ],
        'storage' => [
            'name' => 'Lagring av plugin-innstillinger',
            'fix' => 'Reparer den lokale Laravel-lagringsdisken og gi panelet skrivetilgang.',
        ],
        'queue' => [
            'name' => 'Bakgrunnskø konfigurert',
            'fix' => 'Konfigurer en vedvarende Pelican-køtilkobling og start queue worker for den.',
        ],
    ],
    'assets' => [
        'manifest' => 'Byggemanifestet mangler eller er ugyldig.',
        'missing_entry' => 'Manglende oppføring: :source.',
        'invalid_entry' => 'Ugyldig byggeoppføring.',
        'invalid_css' => 'Ugyldig CSS-liste.',
        'invalid_path' => 'Ugyldig bane til byggefil.',
        'missing_asset' => 'Manglende eller tom fil: :file.',
        'invalid_imports' => 'Ugyldige byggeimporter.',
        'missing_import' => 'Manglende importert byggeoppføring.',
        'no_css' => 'Fant ingen kompilert CSS.',
        'no_js' => 'Fant ingen kompilert JavaScript.',
    ],
    'uploads' => [
        'runtime_cli' => 'CLI-PHP (kontroller PHP-FPM separat)',
        'runtime_web' => 'Web-PHP',
        'capacity' => 'Opplastingskapasitet · :size for bannere',
        'capacity_detail' => ':runtime: upload_max_filesize=:upload; post_max_size=:post. Banneropplastinger begrenses til den laveste PHP-/Livewire-grensen (maks 5 MB).',
        'capacity_fix' => 'For å tillate bannere på 5 MB, sett PHP-FPM upload_max_filesize = 10M og post_max_size = 12M, sjekk eventuelle Livewire-max-regler, og last deretter inn PHP-FPM på nytt.',
        'proxy' => 'Opplastingsgrense i NGINX / proxy · ikke kontrollert',
        'proxy_detail' => 'En proxy kan avvise opplastinger før PHP mottar dem (HTTP 413), selv når sjekkene ovenfor er bestått.',
        'proxy_fix' => 'Sett client_max_body_size 12m i NGINX-serverblokken som leverer dette panelet; se etter lavere location-overstyringer og upstream-proxygrenser. Kjør sudo nginx -t og deretter sudo systemctl reload nginx. Kontroller med en ekte opplasting. Ingen serverkonfigurasjon endres automatisk.',
    ],
];

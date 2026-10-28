<?php
return [
    'health' => [
        'ok' => ': OK',
    ],
    'system' => [
        'let_s_get_simplify_ready' => 'Nu gör vi Simplify redo',
        'a_quick_check_of_your_panel' => 'En snabb kontroll av din panel innan du väljer dina inställningar.',
        'checking_your_panel' => 'Kontrollerar din panel…',
        'view_checks' => 'Visa kontroller',
        'keep_your_queue_worker_and_scheduler' => 'Håll din queue worker och scheduler igång. Deras aktuella status kan inte kontrolleras här.',
        'check_again' => 'Kontrollera igen',
        'go_to_first_setup' => 'Gå till första konfigurationen',
        'preparing_your_system_checks' => 'Förbereder dina systemkontroller…',
        'close_system_checks' => 'Stäng systemkontroller',
        'first_setup_eyebrow' => 'Första konfigurationen · Systemkontroll',
        'diagnostics' => 'Systemdiagnostik',
        'panel_ready' => 'Din panel är redo',
        'needs_attention_some' => 'Några saker behöver åtgärdas',
        'passed' => ':passed av :total kontroller godkända',
        'ready' => 'Redo',
        'needs_attention' => 'Behöver åtgärdas',
        'warning_optional' => 'Varning · valfritt',
    ],
    'requirements' => [
        'php' => [
            'name' => 'PHP 8.3 eller senare',
            'fix' => 'Uppgradera PHP för både webbservern och queue worker till PHP 8.3 eller senare.',
        ],
        'php_extension' => [
            'name' => 'PHP-tillägg: :extension',
            'fix' => 'Aktivera tillägget :extension i den PHP-runtime som panelen och queue worker använder, och starta sedan om de tjänsterna.',
        ],
        'pelican' => [
            'name' => 'Pelican-applikation och Composer-beroenden',
            'fix' => 'Slutför Pelican-installationen och installera dess Composer-beroenden.',
        ],
        'composer' => [
            'name' => 'Composer: :class',
            'fix' => 'Installera de Composer-beroenden som hör till din Pelican-version.',
        ],
        'tabler' => [
            'name' => 'Tabler-ikonkomponenter',
            'fix' => 'Återställ Composer-beroendet blade-tabler-icons och rensa Blades vycache.',
        ],
        'node' => [
            'name' => 'Node.js 22.12+ / 24+',
            'fix' => 'Installera Node.js 22.12 eller senare (eller Node.js 24+). Gör node tillgängligt i PATH för webbservern och queue worker.',
        ],
        'yarn' => [
            'name' => 'Yarn',
            'fix' => 'Installera Yarn eller aktivera det via Corepack. Gör yarn tillgängligt i PATH för webbservern och queue worker.',
        ],
        'writable' => [
            'name' => 'Skrivbar :directory',
            'fix' => 'Ge panelen och queue worker läs- och skrivåtkomst till :directory.',
        ],
        'plugin_asset' => [
            'name' => 'Pluginfil: :file',
            'fix' => 'Importera hela PelicanSimplify-paketet igen; den här CSS-/JavaScript-filen saknas, är tom eller går inte att läsa.',
        ],
        'legacy_creator' => [
            'name' => 'Fristående skapare borttagen',
            'fix' => 'Inaktivera och ta bort den tidigare fristående skaparen (simple-server-creator) under Admin → Plugins innan du aktiverar den samlade skaparen.',
        ],
        'built_assets' => [
            'name' => 'Byggd CSS och JavaScript för Pelican',
            'fix' => 'Bygg om panelens filer med yarn install och yarn build i din Pelican-mapp. :errors',
        ],
        'tested_release' => [
            'name' => 'Testad Pelican-version',
            'fix' => 'Simplify är testat på Pelican :tested; den här panelen rapporterar :release. Installationen fortsätter, men rapportera problem på GitHub.',
            'no_version' => 'ingen version',
        ],
        'database' => [
            'name' => 'Databasanslutning',
            'fix' => 'Reparera panelens databasanslutning innan du fortsätter.',
        ],
        'cache' => [
            'name' => 'Cache läsa/skriva',
            'fix' => 'Reparera den konfigurerade cache-backenden och dess behörigheter.',
        ],
        'storage' => [
            'name' => 'Lagring av plugininställningar',
            'fix' => 'Reparera den lokala Laravel-lagringsdisken och ge panelen skrivåtkomst.',
        ],
        'queue' => [
            'name' => 'Bakgrundskö konfigurerad',
            'fix' => 'Konfigurera en beständig köanslutning för Pelican och starta dess queue worker.',
        ],
    ],
    'assets' => [
        'manifest' => 'Byggmanifestet saknas eller är ogiltigt.',
        'missing_entry' => 'Post saknas: :source.',
        'invalid_entry' => 'Ogiltig byggpost.',
        'invalid_css' => 'Ogiltig CSS-lista.',
        'invalid_path' => 'Ogiltig sökväg till byggfil.',
        'missing_asset' => 'Fil saknas eller är tom: :file.',
        'invalid_imports' => 'Ogiltiga byggimporter.',
        'missing_import' => 'Importerad byggpost saknas.',
        'no_css' => 'Ingen kompilerad CSS hittades.',
        'no_js' => 'Inget kompilerat JavaScript hittades.',
    ],
    'uploads' => [
        'runtime_cli' => 'CLI-PHP (kontrollera PHP-FPM separat)',
        'runtime_web' => 'Webb-PHP',
        'capacity' => 'Uppladdningskapacitet · :size för banners',
        'capacity_detail' => ':runtime: upload_max_filesize=:upload; post_max_size=:post. Banneruppladdningar begränsas till den lägsta PHP-/Livewire-gränsen (högst 5 MB).',
        'capacity_fix' => 'För att tillåta banners på 5 MB, ställ in PHP-FPM upload_max_filesize = 10M och post_max_size = 12M, kontrollera eventuella Livewire-max-regler och ladda sedan om PHP-FPM.',
        'proxy' => 'Uppladdningsgräns i NGINX / proxy · inte kontrollerad',
        'proxy_detail' => 'En proxy kan avvisa uppladdningar innan PHP tar emot dem (HTTP 413), även när kontrollerna ovan är godkända.',
        'proxy_fix' => 'Ställ in client_max_body_size 12m i NGINX-serverblocket som levererar den här panelen; leta efter lägre location-åsidosättningar och upstream-proxygränser. Kör sudo nginx -t och sedan sudo systemctl reload nginx. Kontrollera med en riktig uppladdning. Ingen serverkonfiguration ändras automatiskt.',
    ],
];

<?php
return [
    'health' => [
        'ok' => ': OK',
    ],
    'system' => [
        'let_s_get_simplify_ready' => 'Připravme Simplify',
        'a_quick_check_of_your_panel' => 'Rychlá kontrola panelu, než vyberete nastavení.',
        'checking_your_panel' => 'Kontrola panelu…',
        'view_checks' => 'Zobrazit kontroly',
        'keep_your_queue_worker_and_scheduler' => 'Nechte queue worker a scheduler běžet. Jejich aktuální stav zde nelze ověřit.',
        'check_again' => 'Zkontrolovat znovu',
        'go_to_first_setup' => 'Přejít k prvnímu nastavení',
        'preparing_your_system_checks' => 'Příprava kontrol systému…',
        'close_system_checks' => 'Zavřít kontroly systému',
        'first_setup_eyebrow' => 'První nastavení · Kontrola systému',
        'diagnostics' => 'Diagnostika systému',
        'panel_ready' => 'Váš panel je připraven',
        'needs_attention_some' => 'Několik věcí vyžaduje pozornost',
        'passed' => 'Prošlo :passed z :total kontrol',
        'ready' => 'Připraveno',
        'needs_attention' => 'Vyžaduje pozornost',
        'warning_optional' => 'Varování · volitelné',
    ],
    'requirements' => [
        'php' => [
            'name' => 'PHP 8.3 nebo novější',
            'fix' => 'Aktualizujte PHP pro webový server a queue worker na PHP 8.3 nebo novější.',
        ],
        'php_extension' => [
            'name' => 'Rozšíření PHP: :extension',
            'fix' => 'Zapněte rozšíření :extension v prostředí PHP, které používají panel a queue worker, a pak tyto služby restartujte.',
        ],
        'pelican' => [
            'name' => 'Aplikace Pelican a závislosti Composer',
            'fix' => 'Dokončete instalaci Pelican a nainstalujte jeho závislosti Composer.',
        ],
        'composer' => [
            'name' => 'Composer: :class',
            'fix' => 'Nainstalujte závislosti Composer odpovídající vašemu vydání Pelican.',
        ],
        'tabler' => [
            'name' => 'Komponenty ikon Tabler',
            'fix' => 'Obnovte závislost Composer blade-tabler-icons a vymažte mezipaměť pohledů Blade.',
        ],
        'node' => [
            'name' => 'Node.js 22.12+ / 24+',
            'fix' => 'Nainstalujte Node.js 22.12 nebo novější (nebo Node.js 24+). Zpřístupněte node v PATH webového serveru a queue worker.',
        ],
        'yarn' => [
            'name' => 'Yarn',
            'fix' => 'Nainstalujte Yarn nebo ho zapněte přes Corepack. Zpřístupněte yarn v PATH webového serveru a queue worker.',
        ],
        'writable' => [
            'name' => 'Zapisovatelné: :directory',
            'fix' => 'Dejte panelu a queue worker oprávnění ke čtení a zápisu do :directory.',
        ],
        'plugin_asset' => [
            'name' => 'Soubor pluginu: :file',
            'fix' => 'Znovu importujte celý balíček PelicanSimplify; tento soubor CSS/JavaScript chybí, je prázdný nebo nečitelný.',
        ],
        'legacy_creator' => [
            'name' => 'Samostatný průvodce vyřazen',
            'fix' => 'Vypněte a odstraňte starý samostatný průvodce (simple-server-creator) v Admin → Plugins, než zapnete jednotný průvodce.',
        ],
        'built_assets' => [
            'name' => 'Sestavené CSS a JavaScript Pelican',
            'fix' => 'Znovu sestavte soubory panelu příkazy yarn install a yarn build ve složce Pelican. :errors',
        ],
        'tested_release' => [
            'name' => 'Otestované vydání Pelican',
            'fix' => 'Simplify je otestován na Pelican :tested; tento panel hlásí :release. Instalace bude pokračovat, ale problémy hlaste na GitHubu.',
            'no_version' => 'bez verze',
        ],
        'database' => [
            'name' => 'Připojení k databázi',
            'fix' => 'Než budete pokračovat, opravte připojení panelu k databázi.',
        ],
        'cache' => [
            'name' => 'Čtení/zápis mezipaměti',
            'fix' => 'Opravte nastavený backend mezipaměti a jeho oprávnění.',
        ],
        'storage' => [
            'name' => 'Úložiště nastavení pluginu',
            'fix' => 'Opravte místní disk storage Laravel a dejte panelu oprávnění k zápisu.',
        ],
        'queue' => [
            'name' => 'Fronta na pozadí nastavena',
            'fix' => 'Nastavte trvalé připojení fronty Pelican a spusťte její queue worker.',
        ],
    ],
    'assets' => [
        'manifest' => 'Manifest sestavení chybí nebo je neplatný.',
        'missing_entry' => 'Chybí položka: :source.',
        'invalid_entry' => 'Neplatná položka sestavení.',
        'invalid_css' => 'Neplatný seznam CSS.',
        'invalid_path' => 'Neplatná cesta k souboru sestavení.',
        'missing_asset' => 'Soubor chybí nebo je prázdný: :file.',
        'invalid_imports' => 'Neplatné importy sestavení.',
        'missing_import' => 'Chybí importovaná položka sestavení.',
        'no_css' => 'Zkompilované CSS nebylo nalezeno.',
        'no_js' => 'Zkompilovaný JavaScript nebyl nalezen.',
    ],
    'uploads' => [
        'runtime_cli' => 'PHP CLI (PHP-FPM ověřte zvlášť)',
        'runtime_web' => 'Webové PHP',
        'capacity' => 'Limit nahrávání · :size pro bannery',
        'capacity_detail' => ':runtime: upload_max_filesize=:upload; post_max_size=:post. Nahrávání bannerů omezuje nižší limit PHP/Livewire (maximálně 5 MB).',
        'capacity_fix' => 'Pro povolení bannerů o 5 MB nastavte v PHP-FPM upload_max_filesize = 10M a post_max_size = 12M, zkontrolujte pravidlo max v Livewire a pak PHP-FPM znovu načtěte.',
        'proxy' => 'Limit nahrávání NGINX / proxy · neověřeno',
        'proxy_detail' => 'Proxy může nahrávání odmítnout dřív, než ho PHP dostane (HTTP 413), i když kontroly výše prošly.',
        'proxy_fix' => 'V bloku server NGINX, který obsluhuje tento panel, nastavte client_max_body_size 12m; zkontrolujte nižší hodnoty v location a limity nadřazené proxy. Spusťte sudo nginx -t a pak sudo systemctl reload nginx. Ověřte skutečným nahráním. Konfigurace serveru se automaticky nemění.',
    ],
];

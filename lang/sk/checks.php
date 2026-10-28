<?php
return [
    'health' => [
        'ok' => ': OK',
    ],
    'system' => [
        'let_s_get_simplify_ready' => 'Pripravme Simplify',
        'a_quick_check_of_your_panel' => 'Rýchla kontrola panela, než vyberiete nastavenia.',
        'checking_your_panel' => 'Kontrola panela…',
        'view_checks' => 'Zobraziť kontroly',
        'keep_your_queue_worker_and_scheduler' => 'Nechajte queue worker a scheduler bežať. Ich aktuálny stav tu nemožno overiť.',
        'check_again' => 'Skontrolovať znova',
        'go_to_first_setup' => 'Prejsť na prvé nastavenie',
        'preparing_your_system_checks' => 'Príprava kontrol systému…',
        'close_system_checks' => 'Zavrieť kontroly systému',
        'first_setup_eyebrow' => 'Prvé nastavenie · Kontrola systému',
        'diagnostics' => 'Diagnostika systému',
        'panel_ready' => 'Váš panel je pripravený',
        'needs_attention_some' => 'Niekoľko vecí si vyžaduje pozornosť',
        'passed' => 'Prešlo :passed z :total kontrol',
        'ready' => 'Pripravené',
        'needs_attention' => 'Vyžaduje pozornosť',
        'warning_optional' => 'Varovanie · voliteľné',
    ],
    'requirements' => [
        'php' => [
            'name' => 'PHP 8.3 alebo novšie',
            'fix' => 'Aktualizujte PHP pre webový server a queue worker na PHP 8.3 alebo novšie.',
        ],
        'php_extension' => [
            'name' => 'Rozšírenie PHP: :extension',
            'fix' => 'Zapnite rozšírenie :extension v prostredí PHP, ktoré používajú panel a queue worker, a potom tieto služby reštartujte.',
        ],
        'pelican' => [
            'name' => 'Aplikácia Pelican a závislosti Composer',
            'fix' => 'Dokončite inštaláciu Pelican a nainštalujte jeho závislosti Composer.',
        ],
        'composer' => [
            'name' => 'Composer: :class',
            'fix' => 'Nainštalujte závislosti Composer zodpovedajúce vášmu vydaniu Pelican.',
        ],
        'tabler' => [
            'name' => 'Komponenty ikon Tabler',
            'fix' => 'Obnovte závislosť Composer blade-tabler-icons a vymažte vyrovnávaciu pamäť zobrazení Blade.',
        ],
        'node' => [
            'name' => 'Node.js 22.12+ / 24+',
            'fix' => 'Nainštalujte Node.js 22.12 alebo novší (alebo Node.js 24+). Sprístupnite node v PATH webového servera a queue worker.',
        ],
        'yarn' => [
            'name' => 'Yarn',
            'fix' => 'Nainštalujte Yarn alebo ho zapnite cez Corepack. Sprístupnite yarn v PATH webového servera a queue worker.',
        ],
        'writable' => [
            'name' => 'Zapisovateľné: :directory',
            'fix' => 'Dajte panelu a queue worker oprávnenie na čítanie a zápis do :directory.',
        ],
        'plugin_asset' => [
            'name' => 'Súbor pluginu: :file',
            'fix' => 'Znova importujte celý balík PelicanSimplify; tento súbor CSS/JavaScript chýba, je prázdny alebo nečitateľný.',
        ],
        'legacy_creator' => [
            'name' => 'Samostatný sprievodca vyradený',
            'fix' => 'Vypnite a odstráňte starý samostatný sprievodca (simple-server-creator) v Admin → Plugins, než zapnete jednotný sprievodca.',
        ],
        'built_assets' => [
            'name' => 'Zostavené CSS a JavaScript Pelican',
            'fix' => 'Znova zostavte súbory panela príkazmi yarn install a yarn build v priečinku Pelican. :errors',
        ],
        'tested_release' => [
            'name' => 'Otestované vydanie Pelican',
            'fix' => 'Simplify je otestovaný na Pelican :tested; tento panel hlási :release. Inštalácia bude pokračovať, ale problémy hláste na GitHube.',
            'no_version' => 'bez verzie',
        ],
        'database' => [
            'name' => 'Pripojenie k databáze',
            'fix' => 'Než budete pokračovať, opravte pripojenie panela k databáze.',
        ],
        'cache' => [
            'name' => 'Čítanie/zápis vyrovnávacej pamäte',
            'fix' => 'Opravte nastavený backend vyrovnávacej pamäte a jeho oprávnenia.',
        ],
        'storage' => [
            'name' => 'Úložisko nastavení pluginu',
            'fix' => 'Opravte lokálny disk storage Laravel a dajte panelu oprávnenie na zápis.',
        ],
        'queue' => [
            'name' => 'Front na pozadí nastavený',
            'fix' => 'Nastavte trvalé pripojenie frontu Pelican a spustite jeho queue worker.',
        ],
    ],
    'assets' => [
        'manifest' => 'Manifest zostavenia chýba alebo je neplatný.',
        'missing_entry' => 'Chýba položka: :source.',
        'invalid_entry' => 'Neplatná položka zostavenia.',
        'invalid_css' => 'Neplatný zoznam CSS.',
        'invalid_path' => 'Neplatná cesta k súboru zostavenia.',
        'missing_asset' => 'Súbor chýba alebo je prázdny: :file.',
        'invalid_imports' => 'Neplatné importy zostavenia.',
        'missing_import' => 'Chýba importovaná položka zostavenia.',
        'no_css' => 'Skompilované CSS sa nenašlo.',
        'no_js' => 'Skompilovaný JavaScript sa nenašiel.',
    ],
    'uploads' => [
        'runtime_cli' => 'PHP CLI (PHP-FPM overte zvlášť)',
        'runtime_web' => 'Webové PHP',
        'capacity' => 'Limit nahrávania · :size pre bannery',
        'capacity_detail' => ':runtime: upload_max_filesize=:upload; post_max_size=:post. Nahrávanie bannerov obmedzuje nižší limit PHP/Livewire (najviac 5 MB).',
        'capacity_fix' => 'Na povolenie bannerov s veľkosťou 5 MB nastavte v PHP-FPM upload_max_filesize = 10M a post_max_size = 12M, skontrolujte pravidlo max v Livewire a potom PHP-FPM znova načítajte.',
        'proxy' => 'Limit nahrávania NGINX / proxy · neoverené',
        'proxy_detail' => 'Proxy môže nahrávanie odmietnuť skôr, než ho PHP dostane (HTTP 413), aj keď kontroly vyššie prešli.',
        'proxy_fix' => 'V bloku server NGINX, ktorý obsluhuje tento panel, nastavte client_max_body_size 12m; skontrolujte nižšie hodnoty v location a limity nadradenej proxy. Spustite sudo nginx -t a potom sudo systemctl reload nginx. Overte skutočným nahraním. Konfigurácia servera sa automaticky nemení.',
    ],
];

<?php
return [
    'health' => [
        'ok' => ': OK',
    ],
    'system' => [
        'let_s_get_simplify_ready' => 'Készítsük elő a Simplifyt',
        'a_quick_check_of_your_panel' => 'A panel gyors ellenőrzése, mielőtt kiválasztod a beállításaidat.',
        'checking_your_panel' => 'A panel ellenőrzése…',
        'view_checks' => 'Ellenőrzések megtekintése',
        'keep_your_queue_worker_and_scheduler' => 'Tartsd futásban a queue workert és az ütemezőt. Az élő állapotuk itt nem ellenőrizhető.',
        'check_again' => 'Ellenőrzés újra',
        'go_to_first_setup' => 'Tovább az első beállításhoz',
        'preparing_your_system_checks' => 'A rendszerellenőrzések előkészítése…',
        'close_system_checks' => 'Rendszerellenőrzések bezárása',
        'first_setup_eyebrow' => 'Első beállítás · Rendszerellenőrzés',
        'diagnostics' => 'Rendszerdiagnosztika',
        'panel_ready' => 'A paneled készen áll',
        'needs_attention_some' => 'Néhány dolog figyelmet igényel',
        'passed' => ':total ellenőrzésből :passed sikeres',
        'ready' => 'Kész',
        'needs_attention' => 'Figyelmet igényel',
        'warning_optional' => 'Figyelmeztetés · opcionális',
    ],
    'requirements' => [
        'php' => [
            'name' => 'PHP 8.3 vagy újabb',
            'fix' => 'Frissítsd a PHP-t a webszerverhez és a queue workerhez is PHP 8.3-ra vagy újabbra.',
        ],
        'php_extension' => [
            'name' => 'PHP-bővítmény: :extension',
            'fix' => 'Engedélyezd a(z) :extension bővítményt a panel és a queue worker által használt PHP-ban, majd indítsd újra ezeket a szolgáltatásokat.',
        ],
        'pelican' => [
            'name' => 'Pelican alkalmazás és Composer-függőségek',
            'fix' => 'Fejezd be a Pelican telepítését, és telepítsd a Composer-függőségeit.',
        ],
        'composer' => [
            'name' => 'Composer: :class',
            'fix' => 'Telepítsd a Pelican-kiadásodnak megfelelő Composer-függőségeket.',
        ],
        'tabler' => [
            'name' => 'Tabler ikonkomponensek',
            'fix' => 'Állítsd vissza a blade-tabler-icons Composer-függőséget, és ürítsd a Blade nézet-gyorsítótárat.',
        ],
        'node' => [
            'name' => 'Node.js 22.12+ / 24+',
            'fix' => 'Telepítsd a Node.js 22.12-es vagy újabb verzióját (vagy a Node.js 24+-t). Tedd elérhetővé a node-ot a webszerver és a queue worker PATH-jában.',
        ],
        'yarn' => [
            'name' => 'Yarn',
            'fix' => 'Telepítsd a Yarnt, vagy engedélyezd Corepackkel. Tedd elérhetővé a yarnt a webszerver és a queue worker PATH-jában.',
        ],
        'writable' => [
            'name' => 'Írható mappa: :directory',
            'fix' => 'Adj olvasási/írási jogot a panelnek és a queue workernek ehhez: :directory.',
        ],
        'plugin_asset' => [
            'name' => 'Bővítményfájl: :file',
            'fix' => 'Importáld újra a teljes PelicanSimplify csomagot; ez a CSS/JavaScript fájl hiányzik, üres vagy nem olvasható.',
        ],
        'legacy_creator' => [
            'name' => 'Az önálló létrehozó megszűnt',
            'fix' => 'Az egységes létrehozó bekapcsolása előtt kapcsold ki és töröld a korábbi önálló létrehozót (simple-server-creator) itt: Admin → Plugins.',
        ],
        'built_assets' => [
            'name' => 'Lefordított Pelican CSS és JavaScript',
            'fix' => 'Fordítsd újra a panel fájljait a yarn install és a yarn build paranccsal a Pelican mappájában. :errors',
        ],
        'tested_release' => [
            'name' => 'Tesztelt Pelican-kiadás',
            'fix' => 'A Simplify a Pelican :tested verzión van tesztelve; ez a panel ezt jelenti: :release. A telepítés folytatódik, de a hibákat jelentsd a GitHubon.',
            'no_version' => 'nincs verzió',
        ],
        'database' => [
            'name' => 'Adatbázis-kapcsolat',
            'fix' => 'Folytatás előtt javítsd ki a panel adatbázis-kapcsolatát.',
        ],
        'cache' => [
            'name' => 'Gyorsítótár olvasása/írása',
            'fix' => 'Javítsd ki a beállított gyorsítótár-háttért és a jogosultságait.',
        ],
        'storage' => [
            'name' => 'Bővítménybeállítások tárolása',
            'fix' => 'Javítsd ki a helyi Laravel-tárolólemezt, és adj írási jogot a panelnek.',
        ],
        'queue' => [
            'name' => 'Háttérsor beállítva',
            'fix' => 'Állíts be egy tartós Pelican-sorkapcsolatot, és indítsd el a queue workerét.',
        ],
    ],
    'assets' => [
        'manifest' => 'A build manifest hiányzik vagy érvénytelen.',
        'missing_entry' => 'Hiányzó bejegyzés: :source.',
        'invalid_entry' => 'Érvénytelen build-bejegyzés.',
        'invalid_css' => 'Érvénytelen CSS-lista.',
        'invalid_path' => 'Érvénytelen build-fájlútvonal.',
        'missing_asset' => 'Hiányzó vagy üres fájl: :file.',
        'invalid_imports' => 'Érvénytelen build-importok.',
        'missing_import' => 'Hiányzó importált build-bejegyzés.',
        'no_css' => 'Nem található lefordított CSS.',
        'no_js' => 'Nem található lefordított JavaScript.',
    ],
    'uploads' => [
        'runtime_cli' => 'Parancssori PHP (a PHP-FPM-et külön ellenőrizd)',
        'runtime_web' => 'Webes PHP',
        'capacity' => 'Feltöltési kapacitás · :size bannerekhez',
        'capacity_detail' => ':runtime: upload_max_filesize=:upload; post_max_size=:post. A bannerfeltöltések az alacsonyabb PHP/Livewire korlátra vannak korlátozva (legfeljebb 5 MB).',
        'capacity_fix' => 'Az 5 MB-os bannerek engedélyezéséhez állítsd a PHP-FPM-ben az upload_max_filesize = 10M és a post_max_size = 12M értéket, ellenőrizd a Livewire max szabályát, majd töltsd újra a PHP-FPM-et.',
        'proxy' => 'NGINX / proxy feltöltési korlát · nincs ellenőrizve',
        'proxy_detail' => 'Egy proxy elutasíthatja a feltöltéseket, mielőtt a PHP megkapná őket (HTTP 413), még ha a fenti ellenőrzések sikeresek is.',
        'proxy_fix' => 'A panelt kiszolgáló NGINX server blokkban állítsd be: client_max_body_size 12m; ellenőrizd az alacsonyabb location felülírásokat és az upstream proxy korlátait. Futtasd: sudo nginx -t, majd sudo systemctl reload nginx. Ellenőrizd egy valódi feltöltéssel. A szerver beállításai nem változnak automatikusan.',
    ],
];

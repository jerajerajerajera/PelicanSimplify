<?php
return [
    'health' => [
        'ok' => ': OK',
    ],
    'system' => [
        'let_s_get_simplify_ready' => 'Să pregătim Simplify',
        'a_quick_check_of_your_panel' => 'O verificare rapidă a panoului înainte să alegeți setările.',
        'checking_your_panel' => 'Se verifică panoul…',
        'view_checks' => 'Vedeți verificările',
        'keep_your_queue_worker_and_scheduler' => 'Mențineți pornite queue worker-ul și scheduler-ul. Starea lor în timp real nu poate fi verificată aici.',
        'check_again' => 'Verificați din nou',
        'go_to_first_setup' => 'Mergeți la prima configurare',
        'preparing_your_system_checks' => 'Se pregătesc verificările de sistem…',
        'close_system_checks' => 'Închideți verificările de sistem',
        'first_setup_eyebrow' => 'Prima configurare · Verificare sistem',
        'diagnostics' => 'Diagnosticare sistem',
        'panel_ready' => 'Panoul este pregătit',
        'needs_attention_some' => 'Câteva lucruri necesită atenție',
        'passed' => ':passed din :total verificări reușite',
        'ready' => 'Pregătit',
        'needs_attention' => 'Necesită atenție',
        'warning_optional' => 'Avertisment · opțional',
    ],
    'requirements' => [
        'php' => [
            'name' => 'PHP 8.3 sau mai nou',
            'fix' => 'Actualizați PHP la 8.3 sau mai nou atât pentru serverul web, cât și pentru queue worker.',
        ],
        'php_extension' => [
            'name' => 'Extensie PHP: :extension',
            'fix' => 'Activați extensia :extension în mediul PHP folosit de panou și de queue worker, apoi reporniți aceste servicii.',
        ],
        'pelican' => [
            'name' => 'Aplicația Pelican și dependențele Composer',
            'fix' => 'Finalizați instalarea Pelican și instalați dependențele Composer.',
        ],
        'composer' => [
            'name' => 'Composer: :class',
            'fix' => 'Instalați dependențele Composer potrivite versiunii dumneavoastră de Pelican.',
        ],
        'tabler' => [
            'name' => 'Componente de pictograme Tabler',
            'fix' => 'Restaurați dependența Composer blade-tabler-icons și goliți cache-ul de vizualizări Blade.',
        ],
        'node' => [
            'name' => 'Node.js 22.12+ / 24+',
            'fix' => 'Instalați Node.js 22.12 sau mai nou (sau Node.js 24+). Faceți node disponibil în PATH pentru serverul web și queue worker.',
        ],
        'yarn' => [
            'name' => 'Yarn',
            'fix' => 'Instalați Yarn sau activați-l prin Corepack. Faceți yarn disponibil în PATH pentru serverul web și queue worker.',
        ],
        'writable' => [
            'name' => ':directory poate fi scris',
            'fix' => 'Acordați panoului și queue worker-ului acces de citire/scriere la :directory.',
        ],
        'plugin_asset' => [
            'name' => 'Fișier al pluginului: :file',
            'fix' => 'Importați din nou pachetul PelicanSimplify complet; acest fișier CSS/JavaScript lipsește, este gol sau nu poate fi citit.',
        ],
        'legacy_creator' => [
            'name' => 'Creatorul separat a fost retras',
            'fix' => 'Dezactivați și ștergeți fostul creator separat (simple-server-creator) la Admin → Plugins înainte de a activa creatorul unificat.',
        ],
        'built_assets' => [
            'name' => 'CSS și JavaScript Pelican compilate',
            'fix' => 'Recompilați resursele panoului cu yarn install și yarn build în folderul Pelican. :errors',
        ],
        'tested_release' => [
            'name' => 'Versiune Pelican testată',
            'fix' => 'Simplify este testat pe Pelican :tested; acest panou raportează :release. Instalarea continuă, dar raportați problemele pe GitHub.',
            'no_version' => 'nicio versiune',
        ],
        'database' => [
            'name' => 'Conexiunea la baza de date',
            'fix' => 'Reparați conexiunea panoului la baza de date înainte de a continua.',
        ],
        'cache' => [
            'name' => 'Citire/scriere cache',
            'fix' => 'Reparați backendul de cache configurat și permisiunile acestuia.',
        ],
        'storage' => [
            'name' => 'Stocarea setărilor pluginului',
            'fix' => 'Reparați discul local de stocare Laravel și acordați panoului acces de scriere.',
        ],
        'queue' => [
            'name' => 'Coadă de fundal configurată',
            'fix' => 'Configurați o conexiune de coadă Pelican persistentă și porniți queue worker-ul.',
        ],
    ],
    'assets' => [
        'manifest' => 'Manifestul de build lipsește sau este invalid.',
        'missing_entry' => 'Intrare lipsă: :source.',
        'invalid_entry' => 'Intrare de build invalidă.',
        'invalid_css' => 'Listă CSS invalidă.',
        'invalid_path' => 'Cale invalidă pentru o resursă de build.',
        'missing_asset' => 'Resursă lipsă sau goală: :file.',
        'invalid_imports' => 'Importuri de build invalide.',
        'missing_import' => 'Lipsește o intrare de build importată.',
        'no_css' => 'Nu s-a găsit CSS compilat.',
        'no_js' => 'Nu s-a găsit JavaScript compilat.',
    ],
    'uploads' => [
        'runtime_cli' => 'PHP CLI (verificați PHP-FPM separat)',
        'runtime_web' => 'PHP web',
        'capacity' => 'Capacitate de încărcare · :size pentru bannere',
        'capacity_detail' => ':runtime: upload_max_filesize=:upload; post_max_size=:post. Încărcările de bannere sunt limitate la limita mai mică dintre PHP și Livewire (maximum 5 MB).',
        'capacity_fix' => 'Pentru a permite bannere de 5 MB, setați în PHP-FPM upload_max_filesize = 10M și post_max_size = 12M, verificați orice regulă max din Livewire, apoi reîncărcați PHP-FPM.',
        'proxy' => 'Limită de încărcare NGINX / proxy · neverificată',
        'proxy_detail' => 'Un proxy poate respinge încărcările înainte ca PHP să le primească (HTTP 413), chiar dacă verificările de mai sus trec.',
        'proxy_fix' => 'În blocul server NGINX care servește acest panou, setați client_max_body_size 12m; verificați dacă există suprascrieri location mai mici și limite ale proxy-urilor din amonte. Rulați sudo nginx -t, apoi sudo systemctl reload nginx. Verificați cu o încărcare reală. Nicio configurare a serverului nu este modificată automat.',
    ],
];

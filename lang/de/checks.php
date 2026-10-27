<?php
return [
    'health' => [
        'ok' => ': OK',
    ],
    'system' => [
        'let_s_get_simplify_ready' => 'Simplify startklar machen',
        'a_quick_check_of_your_panel' => 'Eine kurze Prüfung deines Panels, bevor du deine Einstellungen wählst.',
        'checking_your_panel' => 'Dein Panel wird geprüft…',
        'view_checks' => 'Prüfungen ansehen',
        'keep_your_queue_worker_and_scheduler' => 'Lass Queue Worker und Scheduler laufen. Ob sie gerade laufen, lässt sich hier nicht prüfen.',
        'check_again' => 'Erneut prüfen',
        'go_to_first_setup' => 'Zur Ersteinrichtung',
        'preparing_your_system_checks' => 'Systemprüfungen werden vorbereitet…',
        'close_system_checks' => 'Systemprüfungen schließen',
        'first_setup_eyebrow' => 'Ersteinrichtung · Systemprüfung',
        'diagnostics' => 'Systemdiagnose',
        'panel_ready' => 'Dein Panel ist bereit',
        'needs_attention_some' => 'Ein paar Dinge brauchen Aufmerksamkeit',
        'passed' => ':passed von :total Prüfungen bestanden',
        'ready' => 'Bereit',
        'needs_attention' => 'Braucht Aufmerksamkeit',
        'warning_optional' => 'Warnung · optional',
    ],
    'requirements' => [
        'php' => [
            'name' => 'PHP 8.3 oder neuer',
            'fix' => 'Aktualisiere PHP für Webserver und Queue Worker auf PHP 8.3 oder neuer.',
        ],
        'php_extension' => [
            'name' => 'PHP-Erweiterung: :extension',
            'fix' => 'Aktiviere die Erweiterung :extension in der PHP-Laufzeit von Panel und Queue Worker und starte diese Dienste neu.',
        ],
        'pelican' => [
            'name' => 'Pelican-Anwendung und Composer-Abhängigkeiten',
            'fix' => 'Schließe die Pelican-Installation ab und installiere die Composer-Abhängigkeiten.',
        ],
        'composer' => [
            'name' => 'Composer: :class',
            'fix' => 'Installiere die Composer-Abhängigkeiten passend zu deiner Pelican-Version.',
        ],
        'tabler' => [
            'name' => 'Tabler-Icons',
            'fix' => 'Stelle die Composer-Abhängigkeit blade-tabler-icons wieder her und leere den Blade-View-Cache.',
        ],
        'node' => [
            'name' => 'Node.js 22.12+ / 24+',
            'fix' => 'Installiere Node.js 22.12 oder neuer (oder Node.js 24+). Mach node im PATH von Webserver und Queue Worker verfügbar.',
        ],
        'yarn' => [
            'name' => 'Yarn',
            'fix' => 'Installiere Yarn oder aktiviere es über Corepack. Mach yarn im PATH von Webserver und Queue Worker verfügbar.',
        ],
        'writable' => [
            'name' => 'Beschreibbar: :directory',
            'fix' => 'Gib Panel und Queue Worker Lese- und Schreibzugriff auf :directory.',
        ],
        'plugin_asset' => [
            'name' => 'Plugin-Datei: :file',
            'fix' => 'Importiere das vollständige PelicanSimplify-Paket erneut; diese CSS-/JavaScript-Datei fehlt, ist leer oder nicht lesbar.',
        ],
        'legacy_creator' => [
            'name' => 'Eigenständiger Creator entfernt',
            'fix' => 'Deaktiviere und lösche den früheren eigenständigen Creator (simple-server-creator) unter Admin → Plugins, bevor du den neuen Creator aktivierst.',
        ],
        'built_assets' => [
            'name' => 'Gebautes CSS und JavaScript von Pelican',
            'fix' => 'Baue die Panel-Dateien mit yarn install und yarn build in deinem Pelican-Ordner neu. :errors',
        ],
        'tested_release' => [
            'name' => 'Getestete Pelican-Version',
            'fix' => 'Simplify ist auf Pelican :tested getestet; dieses Panel meldet :release. Die Installation läuft weiter, aber melde Probleme auf GitHub.',
            'no_version' => 'keine Version',
        ],
        'database' => [
            'name' => 'Datenbankverbindung',
            'fix' => 'Repariere die Datenbankverbindung des Panels, bevor du weitermachst.',
        ],
        'cache' => [
            'name' => 'Cache lesen/schreiben',
            'fix' => 'Repariere den eingestellten Cache und seine Rechte.',
        ],
        'storage' => [
            'name' => 'Speicher für Plugin-Einstellungen',
            'fix' => 'Repariere den lokalen Laravel-Speicher und gib dem Panel Schreibzugriff.',
        ],
        'queue' => [
            'name' => 'Hintergrund-Queue eingerichtet',
            'fix' => 'Richte eine dauerhafte Queue-Verbindung für Pelican ein und starte den Queue Worker.',
        ],
    ],
    'assets' => [
        'manifest' => 'Das Build-Manifest fehlt oder ist ungültig.',
        'missing_entry' => 'Fehlender Eintrag: :source.',
        'invalid_entry' => 'Ungültiger Build-Eintrag.',
        'invalid_css' => 'Ungültige CSS-Liste.',
        'invalid_path' => 'Ungültiger Pfad zu einer Build-Datei.',
        'missing_asset' => 'Fehlende oder leere Datei: :file.',
        'invalid_imports' => 'Ungültige Build-Importe.',
        'missing_import' => 'Fehlender importierter Build-Eintrag.',
        'no_css' => 'Kein gebautes CSS gefunden.',
        'no_js' => 'Kein gebautes JavaScript gefunden.',
    ],
    'uploads' => [
        'runtime_cli' => 'CLI-PHP (PHP-FPM separat prüfen)',
        'runtime_web' => 'Web-PHP',
        'capacity' => 'Upload-Kapazität · :size für Banner',
        'capacity_detail' => ':runtime: upload_max_filesize=:upload; post_max_size=:post. Banner-Uploads sind auf das niedrigere PHP-/Livewire-Limit begrenzt (höchstens 5 MB).',
        'capacity_fix' => 'Für Banner bis 5 MB: setze in PHP-FPM upload_max_filesize = 10M und post_max_size = 12M, prüfe eine eventuelle Livewire-max-Regel und lade PHP-FPM neu.',
        'proxy' => 'Upload-Limit von NGINX / Proxy · nicht geprüft',
        'proxy_detail' => 'Ein Proxy kann Uploads ablehnen, bevor PHP sie erhält (HTTP 413), auch wenn die Prüfungen oben bestehen.',
        'proxy_fix' => 'Setze im NGINX-Serverblock dieses Panels client_max_body_size 12m; achte auf niedrigere location-Einstellungen und Limits vorgeschalteter Proxys. Führe sudo nginx -t und danach sudo systemctl reload nginx aus. Prüfe mit einem echten Upload. An der Serverkonfiguration wird nichts automatisch geändert.',
    ],
];

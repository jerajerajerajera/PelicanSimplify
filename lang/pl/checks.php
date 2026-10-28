<?php
return [
    'health' => [
        'ok' => ': OK',
    ],
    'system' => [
        'let_s_get_simplify_ready' => 'Przygotujmy Simplify',
        'a_quick_check_of_your_panel' => 'Szybkie sprawdzenie panelu, zanim wybierzesz ustawienia.',
        'checking_your_panel' => 'Sprawdzanie panelu…',
        'view_checks' => 'Pokaż testy',
        'keep_your_queue_worker_and_scheduler' => 'Utrzymuj działający queue worker i scheduler. Ich bieżącego stanu nie da się tutaj sprawdzić.',
        'check_again' => 'Sprawdź ponownie',
        'go_to_first_setup' => 'Przejdź do pierwszej konfiguracji',
        'preparing_your_system_checks' => 'Przygotowywanie testów systemu…',
        'close_system_checks' => 'Zamknij testy systemu',
        'first_setup_eyebrow' => 'Pierwsza konfiguracja · Test systemu',
        'diagnostics' => 'Diagnostyka systemu',
        'panel_ready' => 'Twój panel jest gotowy',
        'needs_attention_some' => 'Kilka rzeczy wymaga uwagi',
        'passed' => 'Zaliczono :passed z :total testów',
        'ready' => 'Gotowe',
        'needs_attention' => 'Wymaga uwagi',
        'warning_optional' => 'Ostrzeżenie · opcjonalne',
    ],
    'requirements' => [
        'php' => [
            'name' => 'PHP 8.3 lub nowszy',
            'fix' => 'Zaktualizuj PHP dla serwera WWW i queue workera do PHP 8.3 lub nowszego.',
        ],
        'php_extension' => [
            'name' => 'Rozszerzenie PHP: :extension',
            'fix' => 'Włącz rozszerzenie :extension w środowisku PHP używanym przez panel i queue worker, a potem uruchom te usługi ponownie.',
        ],
        'pelican' => [
            'name' => 'Aplikacja Pelican i zależności Composer',
            'fix' => 'Dokończ instalację Pelican i zainstaluj jego zależności Composer.',
        ],
        'composer' => [
            'name' => 'Composer: :class',
            'fix' => 'Zainstaluj zależności Composer zgodne z Twoim wydaniem Pelican.',
        ],
        'tabler' => [
            'name' => 'Komponenty ikon Tabler',
            'fix' => 'Przywróć zależność Composer blade-tabler-icons i wyczyść cache widoków Blade.',
        ],
        'node' => [
            'name' => 'Node.js 22.12+ / 24+',
            'fix' => 'Zainstaluj Node.js 22.12 lub nowszy (albo Node.js 24+). Udostępnij node w PATH serwera WWW i queue workera.',
        ],
        'yarn' => [
            'name' => 'Yarn',
            'fix' => 'Zainstaluj Yarn lub włącz go przez Corepack. Udostępnij yarn w PATH serwera WWW i queue workera.',
        ],
        'writable' => [
            'name' => 'Zapisywalny :directory',
            'fix' => 'Daj panelowi i queue workerowi prawo odczytu i zapisu do :directory.',
        ],
        'plugin_asset' => [
            'name' => 'Plik wtyczki: :file',
            'fix' => 'Zaimportuj ponownie pełny pakiet PelicanSimplify; ten plik CSS/JavaScript brakuje, jest pusty lub nieczytelny.',
        ],
        'legacy_creator' => [
            'name' => 'Samodzielny kreator wycofany',
            'fix' => 'Wyłącz i usuń dawny samodzielny kreator (simple-server-creator) w Admin → Plugins, zanim włączysz zunifikowany kreator.',
        ],
        'built_assets' => [
            'name' => 'Zbudowane CSS i JavaScript Pelican',
            'fix' => 'Przebuduj pliki panelu poleceniami yarn install i yarn build w folderze Pelican. :errors',
        ],
        'tested_release' => [
            'name' => 'Przetestowane wydanie Pelican',
            'fix' => 'Simplify jest testowany na Pelican :tested; ten panel zgłasza :release. Instalacja trwa dalej, ale zgłaszaj problemy na GitHubie.',
            'no_version' => 'brak wersji',
        ],
        'database' => [
            'name' => 'Połączenie z bazą danych',
            'fix' => 'Napraw połączenie panelu z bazą danych, zanim przejdziesz dalej.',
        ],
        'cache' => [
            'name' => 'Odczyt/zapis cache',
            'fix' => 'Napraw skonfigurowany backend cache i jego uprawnienia.',
        ],
        'storage' => [
            'name' => 'Magazyn ustawień wtyczki',
            'fix' => 'Napraw lokalny dysk storage Laravela i daj panelowi prawo zapisu.',
        ],
        'queue' => [
            'name' => 'Skonfigurowana kolejka w tle',
            'fix' => 'Skonfiguruj trwałe połączenie kolejki Pelican i uruchom jej queue worker.',
        ],
    ],
    'assets' => [
        'manifest' => 'Brak manifestu builda lub jest nieprawidłowy.',
        'missing_entry' => 'Brak wpisu: :source.',
        'invalid_entry' => 'Nieprawidłowy wpis builda.',
        'invalid_css' => 'Nieprawidłowa lista CSS.',
        'invalid_path' => 'Nieprawidłowa ścieżka pliku builda.',
        'missing_asset' => 'Brakujący lub pusty plik: :file.',
        'invalid_imports' => 'Nieprawidłowe importy builda.',
        'missing_import' => 'Brak importowanego wpisu builda.',
        'no_css' => 'Nie znaleziono skompilowanego CSS.',
        'no_js' => 'Nie znaleziono skompilowanego JavaScriptu.',
    ],
    'uploads' => [
        'runtime_cli' => 'PHP CLI (PHP-FPM sprawdź osobno)',
        'runtime_web' => 'PHP WWW',
        'capacity' => 'Limit przesyłania · :size dla banerów',
        'capacity_detail' => ':runtime: upload_max_filesize=:upload; post_max_size=:post. Przesyłanie banerów jest ograniczone niższym limitem PHP/Livewire (maksymalnie 5 MB).',
        'capacity_fix' => 'Aby pozwolić na banery 5 MB, ustaw w PHP-FPM upload_max_filesize = 10M i post_max_size = 12M, sprawdź regułę max w Livewire, a potem przeładuj PHP-FPM.',
        'proxy' => 'Limit przesyłania NGINX / proxy · niesprawdzony',
        'proxy_detail' => 'Proxy może odrzucić przesyłanie, zanim PHP je otrzyma (HTTP 413), nawet gdy powyższe testy przechodzą.',
        'proxy_fix' => 'W bloku server NGINX obsługującym ten panel ustaw client_max_body_size 12m; sprawdź niższe ustawienia w location i limity proxy wyżej. Uruchom sudo nginx -t, a potem sudo systemctl reload nginx. Sprawdź prawdziwym przesłaniem. Żadna konfiguracja serwera nie jest zmieniana automatycznie.',
    ],
];

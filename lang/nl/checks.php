<?php
return [
    'health' => [
        'ok' => ': OK',
    ],
    'system' => [
        'let_s_get_simplify_ready' => 'Simplify klaarzetten',
        'a_quick_check_of_your_panel' => 'Een snelle controle van je paneel voordat je je instellingen kiest.',
        'checking_your_panel' => 'Je paneel wordt gecontroleerd…',
        'view_checks' => 'Controles bekijken',
        'keep_your_queue_worker_and_scheduler' => 'Laat je queue worker en scheduler draaien. Of ze nu draaien kan hier niet worden gecontroleerd.',
        'check_again' => 'Opnieuw controleren',
        'go_to_first_setup' => 'Naar eerste installatie',
        'preparing_your_system_checks' => 'Systeemcontroles worden voorbereid…',
        'close_system_checks' => 'Systeemcontroles sluiten',
        'first_setup_eyebrow' => 'Eerste installatie · Systeemcontrole',
        'diagnostics' => 'Systeemdiagnose',
        'panel_ready' => 'Je paneel is klaar',
        'needs_attention_some' => 'Een paar dingen hebben aandacht nodig',
        'passed' => ':passed van :total controles geslaagd',
        'ready' => 'Klaar',
        'needs_attention' => 'Aandacht nodig',
        'warning_optional' => 'Waarschuwing · optioneel',
    ],
    'requirements' => [
        'php' => [
            'name' => 'PHP 8.3 of nieuwer',
            'fix' => 'Werk PHP voor zowel de webserver als de queue worker bij naar PHP 8.3 of nieuwer.',
        ],
        'php_extension' => [
            'name' => 'PHP-extensie: :extension',
            'fix' => 'Zet de extensie :extension aan in de PHP die het paneel en de queue worker gebruiken, en herstart die diensten.',
        ],
        'pelican' => [
            'name' => 'Pelican-applicatie en Composer-pakketten',
            'fix' => 'Maak de Pelican-installatie af en installeer de Composer-pakketten.',
        ],
        'composer' => [
            'name' => 'Composer: :class',
            'fix' => 'Installeer de Composer-pakketten die bij je Pelican-versie horen.',
        ],
        'tabler' => [
            'name' => 'Tabler-iconen',
            'fix' => 'Zet het Composer-pakket blade-tabler-icons terug en leeg de Blade-viewcache.',
        ],
        'node' => [
            'name' => 'Node.js 22.12+ / 24+',
            'fix' => 'Installeer Node.js 22.12 of nieuwer (of Node.js 24+). Zorg dat node in het PATH van de webserver en de queue worker staat.',
        ],
        'yarn' => [
            'name' => 'Yarn',
            'fix' => 'Installeer Yarn of zet het aan via Corepack. Zorg dat yarn in het PATH van de webserver en de queue worker staat.',
        ],
        'writable' => [
            'name' => 'Schrijfbaar :directory',
            'fix' => 'Geef het paneel en de queue worker lees- en schrijfrechten op :directory.',
        ],
        'plugin_asset' => [
            'name' => 'Pluginbestand: :file',
            'fix' => 'Importeer het complete PelicanSimplify-pakket opnieuw; dit CSS-/JavaScript-bestand ontbreekt, is leeg of onleesbaar.',
        ],
        'legacy_creator' => [
            'name' => 'Losse servermaker vervallen',
            'fix' => 'Schakel de vroegere losse servermaker (simple-server-creator) uit en verwijder hem onder Admin → Plugins voordat je de nieuwe servermaker aanzet.',
        ],
        'built_assets' => [
            'name' => 'Gebouwde CSS en JavaScript van Pelican',
            'fix' => 'Bouw de paneelbestanden opnieuw met yarn install en yarn build in je Pelican-map. :errors',
        ],
        'tested_release' => [
            'name' => 'Geteste Pelican-versie',
            'fix' => 'Simplify is getest op Pelican :tested; dit paneel meldt :release. De installatie gaat door, maar meld problemen op GitHub.',
            'no_version' => 'geen versie',
        ],
        'database' => [
            'name' => 'Databaseverbinding',
            'fix' => 'Herstel de databaseverbinding van het paneel voordat je verdergaat.',
        ],
        'cache' => [
            'name' => 'Cache lezen/schrijven',
            'fix' => 'Herstel de ingestelde cache en de rechten daarop.',
        ],
        'storage' => [
            'name' => 'Opslag van plugininstellingen',
            'fix' => 'Herstel de lokale Laravel-opslag en geef het paneel schrijfrechten.',
        ],
        'queue' => [
            'name' => 'Achtergrondwachtrij ingesteld',
            'fix' => 'Stel een blijvende wachtrijverbinding voor Pelican in en start de queue worker.',
        ],
    ],
    'assets' => [
        'manifest' => 'Het build-manifest ontbreekt of is ongeldig.',
        'missing_entry' => 'Ontbrekend onderdeel: :source.',
        'invalid_entry' => 'Ongeldig build-onderdeel.',
        'invalid_css' => 'Ongeldige CSS-lijst.',
        'invalid_path' => 'Ongeldig pad naar build-bestand.',
        'missing_asset' => 'Ontbrekend of leeg bestand: :file.',
        'invalid_imports' => 'Ongeldige build-imports.',
        'missing_import' => 'Ontbrekend geïmporteerd build-onderdeel.',
        'no_css' => 'Geen gebouwde CSS gevonden.',
        'no_js' => 'Geen gebouwde JavaScript gevonden.',
    ],
    'uploads' => [
        'runtime_cli' => 'CLI-PHP (controleer PHP-FPM apart)',
        'runtime_web' => 'Web-PHP',
        'capacity' => 'Uploadruimte · :size voor banners',
        'capacity_detail' => ':runtime: upload_max_filesize=:upload; post_max_size=:post. Banneruploads zijn begrensd op de laagste PHP-/Livewire-limiet (maximaal 5 MB).',
        'capacity_fix' => 'Voor banners van 5 MB: zet in PHP-FPM upload_max_filesize = 10M en post_max_size = 12M, controleer een eventuele Livewire max-regel en herlaad PHP-FPM.',
        'proxy' => 'Uploadlimiet NGINX / proxy · niet gecontroleerd',
        'proxy_detail' => 'Een proxy kan uploads weigeren voordat PHP ze krijgt (HTTP 413), ook als de controles hierboven slagen.',
        'proxy_fix' => 'Zet in het NGINX-serverblok van dit paneel client_max_body_size 12m; kijk of er lagere location-instellingen of proxylimieten verderop zijn. Draai sudo nginx -t en daarna sudo systemctl reload nginx. Controleer met een echte upload. Er wordt niets automatisch aan de serverinstellingen veranderd.',
    ],
];

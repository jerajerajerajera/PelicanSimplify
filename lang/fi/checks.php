<?php
return [
    'health' => [
        'ok' => ': OK',
    ],
    'system' => [
        'let_s_get_simplify_ready' => 'Valmistellaan Simplify',
        'a_quick_check_of_your_panel' => 'Nopea tarkistus paneelillesi ennen kuin valitset asetukset.',
        'checking_your_panel' => 'Tarkistetaan paneelia…',
        'view_checks' => 'Näytä tarkistukset',
        'keep_your_queue_worker_and_scheduler' => 'Pidä queue worker ja scheduler käynnissä. Niiden nykyistä tilaa ei voi tarkistaa täällä.',
        'check_again' => 'Tarkista uudelleen',
        'go_to_first_setup' => 'Siirry ensimmäiseen määritykseen',
        'preparing_your_system_checks' => 'Valmistellaan järjestelmätarkistuksia…',
        'close_system_checks' => 'Sulje järjestelmätarkistukset',
        'first_setup_eyebrow' => 'Ensimmäinen määritys · Järjestelmätarkistus',
        'diagnostics' => 'Järjestelmädiagnostiikka',
        'panel_ready' => 'Paneelisi on valmis',
        'needs_attention_some' => 'Muutama asia vaatii huomiota',
        'passed' => ':passed / :total tarkistusta läpäisty',
        'ready' => 'Valmis',
        'needs_attention' => 'Vaatii huomiota',
        'warning_optional' => 'Varoitus · valinnainen',
    ],
    'requirements' => [
        'php' => [
            'name' => 'PHP 8.3 tai uudempi',
            'fix' => 'Päivitä sekä verkkopalvelimen että queue workerin PHP versioon 8.3 tai uudempaan.',
        ],
        'php_extension' => [
            'name' => 'PHP-laajennus: :extension',
            'fix' => 'Ota laajennus :extension käyttöön PHP-ympäristössä, jota paneeli ja queue worker käyttävät, ja käynnistä sitten nämä palvelut uudelleen.',
        ],
        'pelican' => [
            'name' => 'Pelican-sovellus ja Composer-riippuvuudet',
            'fix' => 'Viimeistele Pelican-asennus ja asenna sen Composer-riippuvuudet.',
        ],
        'composer' => [
            'name' => 'Composer: :class',
            'fix' => 'Asenna Pelican-versioosi sopivat Composer-riippuvuudet.',
        ],
        'tabler' => [
            'name' => 'Tabler-kuvakekomponentit',
            'fix' => 'Palauta Composer-riippuvuus blade-tabler-icons ja tyhjennä Bladen näkymävälimuisti.',
        ],
        'node' => [
            'name' => 'Node.js 22.12+ / 24+',
            'fix' => 'Asenna Node.js 22.12 tai uudempi (tai Node.js 24+). Lisää node verkkopalvelimen ja queue workerin PATH-polkuun.',
        ],
        'yarn' => [
            'name' => 'Yarn',
            'fix' => 'Asenna Yarn tai ota se käyttöön Corepackin kautta. Lisää yarn verkkopalvelimen ja queue workerin PATH-polkuun.',
        ],
        'writable' => [
            'name' => 'Kirjoitettava :directory',
            'fix' => 'Anna paneelille ja queue workerille luku- ja kirjoitusoikeus kohteeseen :directory.',
        ],
        'plugin_asset' => [
            'name' => 'Lisäosan tiedosto: :file',
            'fix' => 'Tuo koko PelicanSimplify-paketti uudelleen; tämä CSS-/JavaScript-tiedosto puuttuu, on tyhjä tai sitä ei voi lukea.',
        ],
        'legacy_creator' => [
            'name' => 'Erillinen luontityökalu poistettu käytöstä',
            'fix' => 'Poista käytöstä ja poista aiempi erillinen luontityökalu (simple-server-creator) kohdassa Admin → Plugins ennen kuin otat yhdistetyn luontityökalun käyttöön.',
        ],
        'built_assets' => [
            'name' => 'Pelicanin käännetyt CSS ja JavaScript',
            'fix' => 'Käännä paneelin tiedostot uudelleen komennoilla yarn install ja yarn build Pelican-kansiossasi. :errors',
        ],
        'tested_release' => [
            'name' => 'Testattu Pelican-versio',
            'fix' => 'Simplify on testattu Pelican-versiolla :tested; tämä paneeli ilmoittaa version :release. Asennus jatkuu, mutta ilmoita ongelmista GitHubissa.',
            'no_version' => 'ei versiota',
        ],
        'database' => [
            'name' => 'Tietokantayhteys',
            'fix' => 'Korjaa paneelin tietokantayhteys ennen jatkamista.',
        ],
        'cache' => [
            'name' => 'Välimuistin luku/kirjoitus',
            'fix' => 'Korjaa määritetty välimuistitaustaosa ja sen oikeudet.',
        ],
        'storage' => [
            'name' => 'Lisäosan asetusten tallennus',
            'fix' => 'Korjaa paikallinen Laravel-tallennuslevy ja anna paneelille kirjoitusoikeus.',
        ],
        'queue' => [
            'name' => 'Taustajono määritetty',
            'fix' => 'Määritä pysyvä Pelican-jonoyhteys ja käynnistä sen queue worker.',
        ],
    ],
    'assets' => [
        'manifest' => 'Käännösmanifesti puuttuu tai on virheellinen.',
        'missing_entry' => 'Puuttuva merkintä: :source.',
        'invalid_entry' => 'Virheellinen käännösmerkintä.',
        'invalid_css' => 'Virheellinen CSS-luettelo.',
        'invalid_path' => 'Virheellinen käännöstiedoston polku.',
        'missing_asset' => 'Puuttuva tai tyhjä tiedosto: :file.',
        'invalid_imports' => 'Virheelliset käännöstuonnit.',
        'missing_import' => 'Tuotu käännösmerkintä puuttuu.',
        'no_css' => 'Käännettyä CSS-koodia ei löytynyt.',
        'no_js' => 'Käännettyä JavaScript-koodia ei löytynyt.',
    ],
    'uploads' => [
        'runtime_cli' => 'CLI-PHP (tarkista PHP-FPM erikseen)',
        'runtime_web' => 'Verkko-PHP',
        'capacity' => 'Latauskapasiteetti · :size bannereille',
        'capacity_detail' => ':runtime: upload_max_filesize=:upload; post_max_size=:post. Bannerien lataukset rajoitetaan alempaan PHP-/Livewire-rajaan (enintään 5 Mt).',
        'capacity_fix' => 'Jos haluat sallia 5 megatavun bannerit, aseta PHP-FPM-asetuksiin upload_max_filesize = 10M ja post_max_size = 12M, tarkista mahdolliset Livewiren max-säännöt ja lataa sitten PHP-FPM uudelleen.',
        'proxy' => 'NGINX / välityspalvelimen latausraja · ei tarkistettu',
        'proxy_detail' => 'Välityspalvelin voi hylätä lataukset ennen kuin PHP vastaanottaa ne (HTTP 413), vaikka yllä olevat tarkistukset läpäistäisiin.',
        'proxy_fix' => 'Aseta client_max_body_size 12m siihen NGINX-palvelinlohkoon, joka palvelee tätä paneelia; tarkista alemmat location-ohitukset ja upstream-välityspalvelinten rajat. Suorita sudo nginx -t ja sitten sudo systemctl reload nginx. Tarkista oikealla latauksella. Mitään palvelinmäärityksiä ei muuteta automaattisesti.',
    ],
];

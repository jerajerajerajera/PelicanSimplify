<?php
return [
    'health' => [
        'ok' => ': OK',
    ],
    'system' => [
        'let_s_get_simplify_ready' => 'Paruoškime Simplify',
        'a_quick_check_of_your_panel' => 'Greitas skydelio patikrinimas prieš pasirenkant nustatymus.',
        'checking_your_panel' => 'Tikrinamas skydelis…',
        'view_checks' => 'Peržiūrėti patikras',
        'keep_your_queue_worker_and_scheduler' => 'Palikite queue worker ir scheduler veikti. Jų dabartinės būsenos čia patikrinti negalima.',
        'check_again' => 'Tikrinti dar kartą',
        'go_to_first_setup' => 'Eiti į pirmąją sąranką',
        'preparing_your_system_checks' => 'Ruošiamos sistemos patikros…',
        'close_system_checks' => 'Uždaryti sistemos patikras',
        'first_setup_eyebrow' => 'Pirmoji sąranka · Sistemos patikra',
        'diagnostics' => 'Sistemos diagnostika',
        'panel_ready' => 'Jūsų skydelis paruoštas',
        'needs_attention_some' => 'Keletui dalykų reikia dėmesio',
        'passed' => 'Patikrų išlaikyta: :passed iš :total',
        'ready' => 'Paruošta',
        'needs_attention' => 'Reikia dėmesio',
        'warning_optional' => 'Įspėjimas · neprivaloma',
    ],
    'requirements' => [
        'php' => [
            'name' => 'PHP 8.3 arba naujesnė',
            'fix' => 'Atnaujinkite PHP ir žiniatinklio serveriui, ir queue worker iki PHP 8.3 arba naujesnės.',
        ],
        'php_extension' => [
            'name' => 'PHP plėtinys: :extension',
            'fix' => 'Įjunkite plėtinį :extension PHP aplinkoje, kurią naudoja skydelis ir queue worker, tada iš naujo paleiskite šias paslaugas.',
        ],
        'pelican' => [
            'name' => 'Pelican programa ir Composer priklausomybės',
            'fix' => 'Užbaikite Pelican diegimą ir įdiekite jo Composer priklausomybes.',
        ],
        'composer' => [
            'name' => 'Composer: :class',
            'fix' => 'Įdiekite Composer priklausomybes, atitinkančias jūsų Pelican leidimą.',
        ],
        'tabler' => [
            'name' => 'Tabler piktogramų komponentai',
            'fix' => 'Atkurkite Composer priklausomybę blade-tabler-icons ir išvalykite Blade rodinių podėlį.',
        ],
        'node' => [
            'name' => 'Node.js 22.12+ / 24+',
            'fix' => 'Įdiekite Node.js 22.12 arba naujesnę (arba Node.js 24+). Padarykite node pasiekiamą žiniatinklio serverio ir queue worker PATH.',
        ],
        'yarn' => [
            'name' => 'Yarn',
            'fix' => 'Įdiekite Yarn arba įjunkite jį per Corepack. Padarykite yarn pasiekiamą žiniatinklio serverio ir queue worker PATH.',
        ],
        'writable' => [
            'name' => 'Rašomas: :directory',
            'fix' => 'Suteikite skydeliui ir queue worker skaitymo ir rašymo prieigą prie :directory.',
        ],
        'plugin_asset' => [
            'name' => 'Įskiepio failas: :file',
            'fix' => 'Iš naujo importuokite visą PelicanSimplify paketą; šio CSS/JavaScript failo trūksta, jis tuščias arba neperskaitomas.',
        ],
        'legacy_creator' => [
            'name' => 'Atskiras vedlys nebenaudojamas',
            'fix' => 'Prieš įjungdami bendrą vedlį, išjunkite ir ištrinkite seną atskirą vedlį (simple-server-creator) per Admin → Plugins.',
        ],
        'built_assets' => [
            'name' => 'Sukompiliuoti Pelican CSS ir JavaScript',
            'fix' => 'Iš naujo sukompiliuokite skydelio failus komandomis yarn install ir yarn build Pelican aplanke. :errors',
        ],
        'tested_release' => [
            'name' => 'Išbandytas Pelican leidimas',
            'fix' => 'Simplify išbandytas su Pelican :tested; šis skydelis praneša :release. Diegimas tęsiamas, bet apie problemas praneškite GitHub.',
            'no_version' => 'be versijos',
        ],
        'database' => [
            'name' => 'Duomenų bazės ryšys',
            'fix' => 'Prieš tęsdami sutaisykite skydelio ryšį su duomenų baze.',
        ],
        'cache' => [
            'name' => 'Podėlio skaitymas/rašymas',
            'fix' => 'Sutaisykite nustatytą podėlio sistemą ir jos leidimus.',
        ],
        'storage' => [
            'name' => 'Įskiepio nustatymų saugykla',
            'fix' => 'Sutaisykite vietinį Laravel storage diską ir suteikite skydeliui rašymo prieigą.',
        ],
        'queue' => [
            'name' => 'Foninė eilė nustatyta',
            'fix' => 'Nustatykite nuolatinį Pelican eilės ryšį ir paleiskite jo queue worker.',
        ],
    ],
    'assets' => [
        'manifest' => 'Kompiliavimo manifesto trūksta arba jis netinkamas.',
        'missing_entry' => 'Trūksta įrašo: :source.',
        'invalid_entry' => 'Netinkamas kompiliavimo įrašas.',
        'invalid_css' => 'Netinkamas CSS sąrašas.',
        'invalid_path' => 'Netinkamas kompiliavimo failo kelias.',
        'missing_asset' => 'Failo trūksta arba jis tuščias: :file.',
        'invalid_imports' => 'Netinkami kompiliavimo importai.',
        'missing_import' => 'Trūksta importuoto kompiliavimo įrašo.',
        'no_css' => 'Sukompiliuoto CSS nerasta.',
        'no_js' => 'Sukompiliuoto JavaScript nerasta.',
    ],
    'uploads' => [
        'runtime_cli' => 'PHP CLI (PHP-FPM patikrinkite atskirai)',
        'runtime_web' => 'Žiniatinklio PHP',
        'capacity' => 'Įkėlimo riba · :size reklamjuostėms',
        'capacity_detail' => ':runtime: upload_max_filesize=:upload; post_max_size=:post. Reklamjuosčių įkėlimą riboja žemesnė PHP/Livewire riba (daugiausia 5 MB).',
        'capacity_fix' => 'Norėdami leisti 5 MB reklamjuostes, PHP-FPM nustatykite upload_max_filesize = 10M ir post_max_size = 12M, patikrinkite Livewire max taisyklę, tada iš naujo įkelkite PHP-FPM.',
        'proxy' => 'NGINX / proxy įkėlimo riba · nepatikrinta',
        'proxy_detail' => 'Proxy gali atmesti įkėlimą dar prieš jam pasiekiant PHP (HTTP 413), net jei aukščiau esančios patikros išlaikytos.',
        'proxy_fix' => 'NGINX server bloke, aptarnaujančiame šį skydelį, nustatykite client_max_body_size 12m; patikrinkite žemesnes location reikšmes ir aukštesnio proxy ribas. Paleiskite sudo nginx -t, tada sudo systemctl reload nginx. Patikrinkite tikru įkėlimu. Serverio konfigūracija automatiškai nekeičiama.',
    ],
];

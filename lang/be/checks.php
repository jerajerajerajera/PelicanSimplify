<?php
return [
    'health' => [
        'ok' => ': OK',
    ],
    'system' => [
        'let_s_get_simplify_ready' => 'Падрыхтуем Simplify',
        'a_quick_check_of_your_panel' => 'Хуткая праверка панэлі перад выбарам налад.',
        'checking_your_panel' => 'Праверка панэлі…',
        'view_checks' => 'Паказаць праверкі',
        'keep_your_queue_worker_and_scheduler' => 'Трымайце queue worker і scheduler запушчанымі. Іх бягучы стан тут праверыць нельга.',
        'check_again' => 'Праверыць зноў',
        'go_to_first_setup' => 'Перайсці да першай налады',
        'preparing_your_system_checks' => 'Падрыхтоўка праверак сістэмы…',
        'close_system_checks' => 'Закрыць праверкі сістэмы',
        'first_setup_eyebrow' => 'Першая налада · Праверка сістэмы',
        'diagnostics' => 'Дыягностыка сістэмы',
        'panel_ready' => 'Ваша панэль гатовая',
        'needs_attention_some' => 'Некалькі рэчаў патрабуюць увагі',
        'passed' => 'Пройдзена :passed з :total праверак',
        'ready' => 'Гатова',
        'needs_attention' => 'Патрабуе ўвагі',
        'warning_optional' => 'Папярэджанне · неабавязкова',
    ],
    'requirements' => [
        'php' => [
            'name' => 'PHP 8.3 або навейшы',
            'fix' => 'Абнавіце PHP для вэб-сервера і queue worker да PHP 8.3 або навейшага.',
        ],
        'php_extension' => [
            'name' => 'Пашырэнне PHP: :extension',
            'fix' => 'Уключыце пашырэнне :extension у асяроддзі PHP, якое выкарыстоўваюць панэль і queue worker, потым перазапусціце гэтыя службы.',
        ],
        'pelican' => [
            'name' => 'Праграма Pelican і залежнасці Composer',
            'fix' => 'Завяршыце ўсталяванне Pelican і ўсталюйце яго залежнасці Composer.',
        ],
        'composer' => [
            'name' => 'Composer: :class',
            'fix' => 'Усталюйце залежнасці Composer, якія адпавядаюць вашаму выпуску Pelican.',
        ],
        'tabler' => [
            'name' => 'Кампаненты значкоў Tabler',
            'fix' => 'Аднавіце залежнасць Composer blade-tabler-icons і ачысціце кэш прадстаўленняў Blade.',
        ],
        'node' => [
            'name' => 'Node.js 22.12+ / 24+',
            'fix' => 'Усталюйце Node.js 22.12 або навейшы (або Node.js 24+). Зрабіце node даступным у PATH вэб-сервера і queue worker.',
        ],
        'yarn' => [
            'name' => 'Yarn',
            'fix' => 'Усталюйце Yarn або ўключыце яго праз Corepack. Зрабіце yarn даступным у PATH вэб-сервера і queue worker.',
        ],
        'writable' => [
            'name' => 'Даступна для запісу: :directory',
            'fix' => 'Дайце панэлі і queue worker правы на чытанне і запіс у :directory.',
        ],
        'plugin_asset' => [
            'name' => 'Файл плагіна: :file',
            'fix' => 'Зноў імпартуйце поўны пакет PelicanSimplify; гэты файл CSS/JavaScript адсутнічае, пусты або не чытаецца.',
        ],
        'legacy_creator' => [
            'name' => 'Асобны майстар выведзены з ужытку',
            'fix' => 'Выключыце і выдаліце ранейшы асобны майстар (simple-server-creator) у Admin → Plugins, перш чым уключаць адзіны майстар.',
        ],
        'built_assets' => [
            'name' => 'Сабраныя CSS і JavaScript Pelican',
            'fix' => 'Перасабярыце файлы панэлі камандамі yarn install і yarn build у папцы Pelican. :errors',
        ],
        'tested_release' => [
            'name' => 'Правераны выпуск Pelican',
            'fix' => 'Simplify правераны на Pelican :tested; гэтая панэль паведамляе :release. Усталяванне працягнецца, але паведамляйце пра праблемы на GitHub.',
            'no_version' => 'няма версіі',
        ],
        'database' => [
            'name' => 'Падключэнне да базы даных',
            'fix' => 'Выпраўце падключэнне панэлі да базы даных, перш чым працягнуць.',
        ],
        'cache' => [
            'name' => 'Чытанне/запіс кэша',
            'fix' => 'Выпраўце наладжаны бэкенд кэша і яго правы.',
        ],
        'storage' => [
            'name' => 'Сховішча налад плагіна',
            'fix' => 'Выпраўце лакальны дыск storage Laravel і дайце панэлі правы на запіс.',
        ],
        'queue' => [
            'name' => 'Фонавая чарга наладжана',
            'fix' => 'Наладзьце пастаяннае падключэнне чаргі Pelican і запусціце яе queue worker.',
        ],
    ],
    'assets' => [
        'manifest' => 'Маніфест зборкі адсутнічае або несапраўдны.',
        'missing_entry' => 'Адсутнічае запіс: :source.',
        'invalid_entry' => 'Несапраўдны запіс зборкі.',
        'invalid_css' => 'Несапраўдны спіс CSS.',
        'invalid_path' => 'Несапраўдны шлях да файла зборкі.',
        'missing_asset' => 'Файл адсутнічае або пусты: :file.',
        'invalid_imports' => 'Несапраўдныя імпарты зборкі.',
        'missing_import' => 'Адсутнічае імпартаваны запіс зборкі.',
        'no_css' => 'Скампіляваны CSS не знойдзены.',
        'no_js' => 'Скампіляваны JavaScript не знойдзены.',
    ],
    'uploads' => [
        'runtime_cli' => 'PHP CLI (PHP-FPM праверце асобна)',
        'runtime_web' => 'Вэб-PHP',
        'capacity' => 'Ліміт загрузкі · :size для банераў',
        'capacity_detail' => ':runtime: upload_max_filesize=:upload; post_max_size=:post. Загрузка банераў абмежавана меншым лімітам PHP/Livewire (максімум 5 МБ).',
        'capacity_fix' => 'Каб дазволіць банеры па 5 МБ, задайце ў PHP-FPM upload_max_filesize = 10M і post_max_size = 12M, праверце правіла max у Livewire, потым перазагрузіце PHP-FPM.',
        'proxy' => 'Ліміт загрузкі NGINX / проксі · не правераны',
        'proxy_detail' => 'Проксі можа адхіліць загрузку раней, чым яе атрымае PHP (HTTP 413), нават калі праверкі вышэй пройдзены.',
        'proxy_fix' => 'У блоку server NGINX, які абслугоўвае гэтую панэль, задайце client_max_body_size 12m; праверце ніжэйшыя значэнні ў location і ліміты вышэйшага проксі. Выканайце sudo nginx -t, потым sudo systemctl reload nginx. Праверце сапраўднай загрузкай. Канфігурацыя сервера аўтаматычна не змяняецца.',
    ],
];

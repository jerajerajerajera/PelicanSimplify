<?php
return [
    'health' => [
        'ok' => ': OK',
    ],
    'system' => [
        'let_s_get_simplify_ready' => 'Хајде да припремимо Simplify',
        'a_quick_check_of_your_panel' => 'Брза провера вашег панела пре него што изаберете подешавања.',
        'checking_your_panel' => 'Проверава се панел…',
        'view_checks' => 'Прикажи провере',
        'keep_your_queue_worker_and_scheduler' => 'Нека queue worker и scheduler стално раде. Њихов тренутни статус овде не може да се провери.',
        'check_again' => 'Провери поново',
        'go_to_first_setup' => 'Иди на прво подешавање',
        'preparing_your_system_checks' => 'Припремају се системске провере…',
        'close_system_checks' => 'Затвори системске провере',
        'first_setup_eyebrow' => 'Прво подешавање · Провера система',
        'diagnostics' => 'Дијагностика система',
        'panel_ready' => 'Ваш панел је спреман',
        'needs_attention_some' => 'Неколико ствари захтева пажњу',
        'passed' => 'Успешно :passed од :total провера',
        'ready' => 'Спремно',
        'needs_attention' => 'Захтева пажњу',
        'warning_optional' => 'Упозорење · опционо',
    ],
    'requirements' => [
        'php' => [
            'name' => 'PHP 8.3 или новији',
            'fix' => 'Надоградите PHP на 8.3 или новији и за веб сервер и за queue worker.',
        ],
        'php_extension' => [
            'name' => 'PHP екстензија: :extension',
            'fix' => 'Укључите екстензију :extension у PHP окружењу које користе панел и queue worker, па поново покрените те услуге.',
        ],
        'pelican' => [
            'name' => 'Pelican апликација и Composer зависности',
            'fix' => 'Довршите инсталацију Pelican-а и инсталирајте његове Composer зависности.',
        ],
        'composer' => [
            'name' => 'Composer: :class',
            'fix' => 'Инсталирајте Composer зависности које одговарају вашем издању Pelican-а.',
        ],
        'tabler' => [
            'name' => 'Tabler компоненте икона',
            'fix' => 'Вратите Composer зависност blade-tabler-icons и очистите Blade кеш приказа.',
        ],
        'node' => [
            'name' => 'Node.js 22.12+ / 24+',
            'fix' => 'Инсталирајте Node.js 22.12 или новији (или Node.js 24+). Учините node доступним у PATH-у веб сервера и queue worker-а.',
        ],
        'yarn' => [
            'name' => 'Yarn',
            'fix' => 'Инсталирајте Yarn или га укључите преко Corepack-а. Учините yarn доступним у PATH-у веб сервера и queue worker-а.',
        ],
        'writable' => [
            'name' => 'Упис у :directory',
            'fix' => 'Дајте панелу и queue worker-у право читања/уписа у :directory.',
        ],
        'plugin_asset' => [
            'name' => 'Датотека додатка: :file',
            'fix' => 'Поново увезите комплетан PelicanSimplify пакет; ова CSS/JavaScript датотека недостаје, празна је или се не може прочитати.',
        ],
        'legacy_creator' => [
            'name' => 'Засебни креатор је повучен',
            'fix' => 'Искључите и обришите некадашњи засебни креатор (simple-server-creator) под Admin → Plugins пре укључивања обједињеног креатора.',
        ],
        'built_assets' => [
            'name' => 'Изграђени Pelican CSS и JavaScript',
            'fix' => 'Поново изградите ресурсе панела помоћу yarn install и yarn build у фасцикли Pelican-а. :errors',
        ],
        'tested_release' => [
            'name' => 'Тестирано издање Pelican-а',
            'fix' => 'Simplify је тестиран на Pelican :tested; овај панел пријављује :release. Инсталација се наставља, али пријавите проблеме на GitHub-у.',
            'no_version' => 'нема верзије',
        ],
        'database' => [
            'name' => 'Веза са базом података',
            'fix' => 'Поправите везу панела са базом података пре наставка.',
        ],
        'cache' => [
            'name' => 'Читање/упис кеша',
            'fix' => 'Поправите подешени кеш бекенд и његове дозволе.',
        ],
        'storage' => [
            'name' => 'Складиште подешавања додатка',
            'fix' => 'Поправите локални Laravel диск за складиштење и дајте панелу право уписа.',
        ],
        'queue' => [
            'name' => 'Позадински ред је подешен',
            'fix' => 'Подесите трајну Pelican везу реда и покрените њен queue worker.',
        ],
    ],
    'assets' => [
        'manifest' => 'Build манифест недостаје или није исправан.',
        'missing_entry' => 'Недостаје унос: :source.',
        'invalid_entry' => 'Неисправан build унос.',
        'invalid_css' => 'Неисправна CSS листа.',
        'invalid_path' => 'Неисправна путања build ресурса.',
        'missing_asset' => 'Ресурс недостаје или је празан: :file.',
        'invalid_imports' => 'Неисправни build увози.',
        'missing_import' => 'Недостаје увезени build унос.',
        'no_css' => 'Није пронађен компајлирани CSS.',
        'no_js' => 'Није пронађен компајлирани JavaScript.',
    ],
    'uploads' => [
        'runtime_cli' => 'CLI PHP (PHP-FPM проверите засебно)',
        'runtime_web' => 'Веб PHP',
        'capacity' => 'Капацитет отпремања · :size за банере',
        'capacity_detail' => ':runtime: upload_max_filesize=:upload; post_max_size=:post. Отпремање банера је ограничено нижим PHP/Livewire ограничењем (највише 5 MB).',
        'capacity_fix' => 'Да бисте дозволили банере од 5 MB, у PHP-FPM поставите upload_max_filesize = 10M и post_max_size = 12M, проверите свако Livewire max правило, па поново учитајте PHP-FPM.',
        'proxy' => 'Ограничење отпремања NGINX / proxy · није проверено',
        'proxy_detail' => 'Proxy може одбити отпремање пре него што га PHP прими (HTTP 413), чак и кад провере изнад прођу.',
        'proxy_fix' => 'У NGINX server блоку који служи овај панел поставите client_max_body_size 12m; проверите нижа location преклапања и ограничења upstream proxy-ја. Покрените sudo nginx -t, па sudo systemctl reload nginx. Проверите правим отпремањем. Ниједно подешавање сервера се не мења аутоматски.',
    ],
];

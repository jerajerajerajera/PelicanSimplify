<?php
return [
    'health' => [
        'ok' => ': OK',
    ],
    'system' => [
        'let_s_get_simplify_ready' => 'Підготуймо Simplify',
        'a_quick_check_of_your_panel' => 'Швидка перевірка панелі перед вибором налаштувань.',
        'checking_your_panel' => 'Перевірка панелі…',
        'view_checks' => 'Показати перевірки',
        'keep_your_queue_worker_and_scheduler' => 'Тримайте queue worker і scheduler запущеними. Їхній поточний стан тут перевірити не можна.',
        'check_again' => 'Перевірити знову',
        'go_to_first_setup' => 'Перейти до першого налаштування',
        'preparing_your_system_checks' => 'Підготовка перевірок системи…',
        'close_system_checks' => 'Закрити перевірки системи',
        'first_setup_eyebrow' => 'Перше налаштування · Перевірка системи',
        'diagnostics' => 'Діагностика системи',
        'panel_ready' => 'Ваша панель готова',
        'needs_attention_some' => 'Кілька речей потребують уваги',
        'passed' => 'Пройдено :passed із :total перевірок',
        'ready' => 'Готово',
        'needs_attention' => 'Потребує уваги',
        'warning_optional' => 'Попередження · необов\'язково',
    ],
    'requirements' => [
        'php' => [
            'name' => 'PHP 8.3 або новіший',
            'fix' => 'Оновіть PHP для вебсервера й queue worker до PHP 8.3 або новішого.',
        ],
        'php_extension' => [
            'name' => 'Розширення PHP: :extension',
            'fix' => 'Увімкніть розширення :extension у середовищі PHP, яке використовують панель і queue worker, потім перезапустіть ці служби.',
        ],
        'pelican' => [
            'name' => 'Застосунок Pelican і залежності Composer',
            'fix' => 'Завершіть встановлення Pelican і встановіть його залежності Composer.',
        ],
        'composer' => [
            'name' => 'Composer: :class',
            'fix' => 'Встановіть залежності Composer, що відповідають вашому випуску Pelican.',
        ],
        'tabler' => [
            'name' => 'Компоненти іконок Tabler',
            'fix' => 'Відновіть залежність Composer blade-tabler-icons і очистьте кеш представлень Blade.',
        ],
        'node' => [
            'name' => 'Node.js 22.12+ / 24+',
            'fix' => 'Встановіть Node.js 22.12 або новіший (або Node.js 24+). Зробіть node доступним у PATH вебсервера й queue worker.',
        ],
        'yarn' => [
            'name' => 'Yarn',
            'fix' => 'Встановіть Yarn або увімкніть його через Corepack. Зробіть yarn доступним у PATH вебсервера й queue worker.',
        ],
        'writable' => [
            'name' => 'Доступна для запису: :directory',
            'fix' => 'Дайте панелі й queue worker права на читання і запис у :directory.',
        ],
        'plugin_asset' => [
            'name' => 'Файл плагіна: :file',
            'fix' => 'Заново імпортуйте повний пакет PelicanSimplify; цей файл CSS/JavaScript відсутній, порожній або не читається.',
        ],
        'legacy_creator' => [
            'name' => 'Окремий майстер виведено з ужитку',
            'fix' => 'Вимкніть і видаліть попередній окремий майстер (simple-server-creator) в Admin → Plugins, перш ніж вмикати єдиний майстер.',
        ],
        'built_assets' => [
            'name' => 'Зібрані CSS і JavaScript Pelican',
            'fix' => 'Перезберіть файли панелі командами yarn install і yarn build у теці Pelican. :errors',
        ],
        'tested_release' => [
            'name' => 'Перевірений випуск Pelican',
            'fix' => 'Simplify перевірено на Pelican :tested; ця панель повідомляє :release. Встановлення продовжиться, але повідомляйте про проблеми на GitHub.',
            'no_version' => 'немає версії',
        ],
        'database' => [
            'name' => 'Підключення до бази даних',
            'fix' => 'Виправте підключення панелі до бази даних, перш ніж продовжити.',
        ],
        'cache' => [
            'name' => 'Читання/запис кешу',
            'fix' => 'Виправте налаштований бекенд кешу та його права.',
        ],
        'storage' => [
            'name' => 'Сховище налаштувань плагіна',
            'fix' => 'Виправте локальний диск storage Laravel і дайте панелі права на запис.',
        ],
        'queue' => [
            'name' => 'Фонову чергу налаштовано',
            'fix' => 'Налаштуйте постійне підключення черги Pelican і запустіть її queue worker.',
        ],
    ],
    'assets' => [
        'manifest' => 'Маніфест збірки відсутній або недійсний.',
        'missing_entry' => 'Відсутній запис: :source.',
        'invalid_entry' => 'Недійсний запис збірки.',
        'invalid_css' => 'Недійсний список CSS.',
        'invalid_path' => 'Недійсний шлях до файлу збірки.',
        'missing_asset' => 'Файл відсутній або порожній: :file.',
        'invalid_imports' => 'Недійсні імпорти збірки.',
        'missing_import' => 'Відсутній імпортований запис збірки.',
        'no_css' => 'Скомпільований CSS не знайдено.',
        'no_js' => 'Скомпільований JavaScript не знайдено.',
    ],
    'uploads' => [
        'runtime_cli' => 'PHP CLI (PHP-FPM перевірте окремо)',
        'runtime_web' => 'Веб-PHP',
        'capacity' => 'Ліміт завантаження · :size для банерів',
        'capacity_detail' => ':runtime: upload_max_filesize=:upload; post_max_size=:post. Завантаження банерів обмежене меншим лімітом PHP/Livewire (максимум 5 МБ).',
        'capacity_fix' => 'Щоб дозволити банери по 5 МБ, задайте в PHP-FPM upload_max_filesize = 10M і post_max_size = 12M, перевірте правило max у Livewire, потім перезавантажте PHP-FPM.',
        'proxy' => 'Ліміт завантаження NGINX / проксі · не перевірено',
        'proxy_detail' => 'Проксі може відхилити завантаження до того, як його отримає PHP (HTTP 413), навіть якщо перевірки вище пройдено.',
        'proxy_fix' => 'У блоці server NGINX, який обслуговує цю панель, задайте client_max_body_size 12m; перевірте нижчі значення в location і ліміти вищого проксі. Виконайте sudo nginx -t, потім sudo systemctl reload nginx. Перевірте справжнім завантаженням. Конфігурація сервера автоматично не змінюється.',
    ],
];

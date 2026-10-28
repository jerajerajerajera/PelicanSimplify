<?php
return [
    'health' => [
        'ok' => ': OK',
    ],
    'system' => [
        'let_s_get_simplify_ready' => 'Подготовим Simplify',
        'a_quick_check_of_your_panel' => 'Быстрая проверка панели перед выбором настроек.',
        'checking_your_panel' => 'Проверка панели…',
        'view_checks' => 'Показать проверки',
        'keep_your_queue_worker_and_scheduler' => 'Держите queue worker и scheduler запущенными. Их текущее состояние здесь проверить нельзя.',
        'check_again' => 'Проверить снова',
        'go_to_first_setup' => 'Перейти к первой настройке',
        'preparing_your_system_checks' => 'Подготовка проверок системы…',
        'close_system_checks' => 'Закрыть проверки системы',
        'first_setup_eyebrow' => 'Первая настройка · Проверка системы',
        'diagnostics' => 'Диагностика системы',
        'panel_ready' => 'Ваша панель готова',
        'needs_attention_some' => 'Несколько вещей требуют внимания',
        'passed' => 'Пройдено :passed из :total проверок',
        'ready' => 'Готово',
        'needs_attention' => 'Требует внимания',
        'warning_optional' => 'Предупреждение · необязательно',
    ],
    'requirements' => [
        'php' => [
            'name' => 'PHP 8.3 или новее',
            'fix' => 'Обновите PHP для веб-сервера и queue worker до PHP 8.3 или новее.',
        ],
        'php_extension' => [
            'name' => 'Расширение PHP: :extension',
            'fix' => 'Включите расширение :extension в среде PHP, которую используют панель и queue worker, затем перезапустите эти службы.',
        ],
        'pelican' => [
            'name' => 'Приложение Pelican и зависимости Composer',
            'fix' => 'Завершите установку Pelican и установите его зависимости Composer.',
        ],
        'composer' => [
            'name' => 'Composer: :class',
            'fix' => 'Установите зависимости Composer, соответствующие вашему выпуску Pelican.',
        ],
        'tabler' => [
            'name' => 'Компоненты иконок Tabler',
            'fix' => 'Восстановите зависимость Composer blade-tabler-icons и очистите кэш представлений Blade.',
        ],
        'node' => [
            'name' => 'Node.js 22.12+ / 24+',
            'fix' => 'Установите Node.js 22.12 или новее (или Node.js 24+). Сделайте node доступным в PATH веб-сервера и queue worker.',
        ],
        'yarn' => [
            'name' => 'Yarn',
            'fix' => 'Установите Yarn или включите его через Corepack. Сделайте yarn доступным в PATH веб-сервера и queue worker.',
        ],
        'writable' => [
            'name' => 'Доступен для записи: :directory',
            'fix' => 'Дайте панели и queue worker права на чтение и запись в :directory.',
        ],
        'plugin_asset' => [
            'name' => 'Файл плагина: :file',
            'fix' => 'Заново импортируйте полный пакет PelicanSimplify; этот файл CSS/JavaScript отсутствует, пуст или не читается.',
        ],
        'legacy_creator' => [
            'name' => 'Отдельный мастер выведен из употребления',
            'fix' => 'Отключите и удалите прежний отдельный мастер (simple-server-creator) в Admin → Plugins, прежде чем включать единый мастер.',
        ],
        'built_assets' => [
            'name' => 'Собранные CSS и JavaScript Pelican',
            'fix' => 'Пересоберите файлы панели командами yarn install и yarn build в папке Pelican. :errors',
        ],
        'tested_release' => [
            'name' => 'Проверенный выпуск Pelican',
            'fix' => 'Simplify проверен на Pelican :tested; эта панель сообщает :release. Установка продолжится, но сообщайте о проблемах на GitHub.',
            'no_version' => 'нет версии',
        ],
        'database' => [
            'name' => 'Подключение к базе данных',
            'fix' => 'Исправьте подключение панели к базе данных, прежде чем продолжить.',
        ],
        'cache' => [
            'name' => 'Чтение/запись кэша',
            'fix' => 'Исправьте настроенный бэкенд кэша и его права.',
        ],
        'storage' => [
            'name' => 'Хранилище настроек плагина',
            'fix' => 'Исправьте локальный диск storage Laravel и дайте панели права на запись.',
        ],
        'queue' => [
            'name' => 'Фоновая очередь настроена',
            'fix' => 'Настройте постоянное подключение очереди Pelican и запустите её queue worker.',
        ],
    ],
    'assets' => [
        'manifest' => 'Манифест сборки отсутствует или неверен.',
        'missing_entry' => 'Отсутствует запись: :source.',
        'invalid_entry' => 'Неверная запись сборки.',
        'invalid_css' => 'Неверный список CSS.',
        'invalid_path' => 'Неверный путь к файлу сборки.',
        'missing_asset' => 'Файл отсутствует или пуст: :file.',
        'invalid_imports' => 'Неверные импорты сборки.',
        'missing_import' => 'Отсутствует импортируемая запись сборки.',
        'no_css' => 'Скомпилированный CSS не найден.',
        'no_js' => 'Скомпилированный JavaScript не найден.',
    ],
    'uploads' => [
        'runtime_cli' => 'PHP CLI (PHP-FPM проверьте отдельно)',
        'runtime_web' => 'Веб-PHP',
        'capacity' => 'Лимит загрузки · :size для баннеров',
        'capacity_detail' => ':runtime: upload_max_filesize=:upload; post_max_size=:post. Загрузка баннеров ограничена меньшим лимитом PHP/Livewire (максимум 5 МБ).',
        'capacity_fix' => 'Чтобы разрешить баннеры по 5 МБ, задайте в PHP-FPM upload_max_filesize = 10M и post_max_size = 12M, проверьте правило max в Livewire, затем перезагрузите PHP-FPM.',
        'proxy' => 'Лимит загрузки NGINX / прокси · не проверен',
        'proxy_detail' => 'Прокси может отклонить загрузку до того, как её получит PHP (HTTP 413), даже если проверки выше пройдены.',
        'proxy_fix' => 'В блоке server NGINX, который обслуживает эту панель, задайте client_max_body_size 12m; проверьте более низкие значения в location и лимиты вышестоящего прокси. Выполните sudo nginx -t, затем sudo systemctl reload nginx. Проверьте настоящей загрузкой. Конфигурация сервера автоматически не меняется.',
    ],
];

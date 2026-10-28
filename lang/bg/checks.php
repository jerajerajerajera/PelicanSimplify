<?php
return [
    'health' => [
        'ok' => ': OK',
    ],
    'system' => [
        'let_s_get_simplify_ready' => 'Да подготвим Simplify',
        'a_quick_check_of_your_panel' => 'Бърза проверка на панела, преди да изберете настройките си.',
        'checking_your_panel' => 'Проверка на панела…',
        'view_checks' => 'Виж проверките',
        'keep_your_queue_worker_and_scheduler' => 'Поддържайте queue worker и scheduler работещи. Текущото им състояние не може да се провери оттук.',
        'check_again' => 'Провери отново',
        'go_to_first_setup' => 'Към първоначалната настройка',
        'preparing_your_system_checks' => 'Подготовка на системните проверки…',
        'close_system_checks' => 'Затвори системните проверки',
        'first_setup_eyebrow' => 'Първоначална настройка · Системна проверка',
        'diagnostics' => 'Системна диагностика',
        'panel_ready' => 'Панелът ви е готов',
        'needs_attention_some' => 'Няколко неща изискват внимание',
        'passed' => 'Успешни :passed от :total проверки',
        'ready' => 'Готово',
        'needs_attention' => 'Изисква внимание',
        'warning_optional' => 'Предупреждение · по избор',
    ],
    'requirements' => [
        'php' => [
            'name' => 'PHP 8.3 или по-нов',
            'fix' => 'Обновете PHP до 8.3 или по-нов както за уеб сървъра, така и за queue worker.',
        ],
        'php_extension' => [
            'name' => 'PHP разширение: :extension',
            'fix' => 'Включете разширението :extension в PHP средата, използвана от панела и queue worker, след което рестартирайте тези услуги.',
        ],
        'pelican' => [
            'name' => 'Приложение Pelican и Composer зависимости',
            'fix' => 'Завършете инсталацията на Pelican и инсталирайте неговите Composer зависимости.',
        ],
        'composer' => [
            'name' => 'Composer: :class',
            'fix' => 'Инсталирайте Composer зависимостите, съответстващи на вашата версия на Pelican.',
        ],
        'tabler' => [
            'name' => 'Компоненти за икони Tabler',
            'fix' => 'Възстановете Composer зависимостта blade-tabler-icons и изчистете кеша на изгледите на Blade.',
        ],
        'node' => [
            'name' => 'Node.js 22.12+ / 24+',
            'fix' => 'Инсталирайте Node.js 22.12 или по-нов (или Node.js 24+). Направете node достъпен в PATH на уеб сървъра и queue worker.',
        ],
        'yarn' => [
            'name' => 'Yarn',
            'fix' => 'Инсталирайте Yarn или го включете чрез Corepack. Направете yarn достъпен в PATH на уеб сървъра и queue worker.',
        ],
        'writable' => [
            'name' => 'Запис в :directory',
            'fix' => 'Дайте на панела и queue worker права за четене/запис в :directory.',
        ],
        'plugin_asset' => [
            'name' => 'Файл на плъгина: :file',
            'fix' => 'Импортирайте отново пълния пакет PelicanSimplify; този CSS/JavaScript файл липсва, празен е или не може да бъде прочетен.',
        ],
        'legacy_creator' => [
            'name' => 'Самостоятелният създател е спрян',
            'fix' => 'Изключете и изтрийте предишния самостоятелен създател (simple-server-creator) от Admin → Plugins, преди да включите обединения създател.',
        ],
        'built_assets' => [
            'name' => 'Компилирани CSS и JavaScript на Pelican',
            'fix' => 'Компилирайте отново ресурсите на панела с yarn install и yarn build в папката на Pelican. :errors',
        ],
        'tested_release' => [
            'name' => 'Тествана версия на Pelican',
            'fix' => 'Simplify е тестван на Pelican :tested; този панел отчита :release. Инсталацията продължава, но съобщавайте за проблеми в GitHub.',
            'no_version' => 'няма версия',
        ],
        'database' => [
            'name' => 'Връзка с базата данни',
            'fix' => 'Поправете връзката на панела с базата данни, преди да продължите.',
        ],
        'cache' => [
            'name' => 'Четене/запис в кеша',
            'fix' => 'Поправете настроения кеш бекенд и правата му.',
        ],
        'storage' => [
            'name' => 'Хранилище за настройките на плъгина',
            'fix' => 'Поправете локалния Laravel диск за съхранение и дайте на панела права за запис.',
        ],
        'queue' => [
            'name' => 'Фоновата опашка е настроена',
            'fix' => 'Настройте постоянна връзка за опашка в Pelican и стартирайте нейния queue worker.',
        ],
    ],
    'assets' => [
        'manifest' => 'Build манифестът липсва или е невалиден.',
        'missing_entry' => 'Липсващ запис: :source.',
        'invalid_entry' => 'Невалиден build запис.',
        'invalid_css' => 'Невалиден CSS списък.',
        'invalid_path' => 'Невалиден път до build ресурс.',
        'missing_asset' => 'Липсващ или празен ресурс: :file.',
        'invalid_imports' => 'Невалидни build импорти.',
        'missing_import' => 'Липсващ импортиран build запис.',
        'no_css' => 'Не е намерен компилиран CSS.',
        'no_js' => 'Не е намерен компилиран JavaScript.',
    ],
    'uploads' => [
        'runtime_cli' => 'CLI PHP (проверете PHP-FPM отделно)',
        'runtime_web' => 'Уеб PHP',
        'capacity' => 'Капацитет за качване · :size за банери',
        'capacity_detail' => ':runtime: upload_max_filesize=:upload; post_max_size=:post. Качването на банери е ограничено до по-ниския лимит на PHP/Livewire (максимум 5 MB).',
        'capacity_fix' => 'За да позволите банери от 5 MB, задайте в PHP-FPM upload_max_filesize = 10M и post_max_size = 12M, проверете всяко Livewire max правило и презаредете PHP-FPM.',
        'proxy' => 'Лимит за качване на NGINX / proxy · не е проверен',
        'proxy_detail' => 'Proxy може да отхвърли качванията, преди PHP да ги получи (HTTP 413), дори когато проверките по-горе минават.',
        'proxy_fix' => 'В server блока на NGINX, който обслужва този панел, задайте client_max_body_size 12m; проверете за по-ниски location стойности и лимити на upstream proxy. Изпълнете sudo nginx -t, след това sudo systemctl reload nginx. Проверете с истинско качване. Никоя сървърна конфигурация не се променя автоматично.',
    ],
];

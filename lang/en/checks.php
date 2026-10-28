<?php
return [
    'health' => [
        'ok' => ': OK',
    ],
    'system' => [
        'let_s_get_simplify_ready' => 'Let’s get Simplify ready',
        'a_quick_check_of_your_panel' => 'A quick check of your panel before you choose your settings.',
        'checking_your_panel' => 'Checking your panel…',
        'view_checks' => 'View checks',
        'keep_your_queue_worker_and_scheduler' => 'Keep your queue worker and scheduler running. Their live status cannot be verified here.',
        'check_again' => 'Check again',
        'go_to_first_setup' => 'Go to first setup',
        'preparing_your_system_checks' => 'Preparing your system checks…',
        'close_system_checks' => 'Close system checks',
        'first_setup_eyebrow' => 'First setup · System check',
        'diagnostics' => 'System diagnostics',
        'panel_ready' => 'Your panel is ready',
        'needs_attention_some' => 'A few things need attention',
        'passed' => ':passed of :total checks passed',
        'ready' => 'Ready',
        'needs_attention' => 'Needs attention',
        'warning_optional' => 'Warning · optional',
    ],
    'requirements' => [
        'php' => [
            'name' => 'PHP 8.3 or newer',
            'fix' => 'Upgrade PHP for both the web server and queue worker to PHP 8.3 or newer.',
        ],
        'php_extension' => [
            'name' => 'PHP extension: :extension',
            'fix' => 'Enable the :extension extension in the PHP runtime used by the panel and queue worker, then restart those services.',
        ],
        'pelican' => [
            'name' => 'Pelican application and Composer dependencies',
            'fix' => 'Complete the Pelican installation and install its Composer dependencies.',
        ],
        'composer' => [
            'name' => 'Composer: :class',
            'fix' => 'Install the Composer dependencies matching your Pelican release.',
        ],
        'tabler' => [
            'name' => 'Tabler icon components',
            'fix' => 'Restore the blade-tabler-icons Composer dependency and clear the Blade view cache.',
        ],
        'node' => [
            'name' => 'Node.js 22.12+ / 24+',
            'fix' => 'Install Node.js 22.12 or newer (or Node.js 24+). Make node available on the web server and queue worker PATH.',
        ],
        'yarn' => [
            'name' => 'Yarn',
            'fix' => 'Install Yarn or enable it through Corepack. Make yarn available on the web server and queue worker PATH.',
        ],
        'writable' => [
            'name' => 'Writable :directory',
            'fix' => 'Grant the panel and queue worker read/write access to :directory.',
        ],
        'plugin_asset' => [
            'name' => 'Plugin asset: :file',
            'fix' => 'Reimport the complete PelicanSimplify package; this CSS/JavaScript file is missing, empty or unreadable.',
        ],
        'legacy_creator' => [
            'name' => 'Standalone creator retired',
            'fix' => 'Disable and delete the former standalone creator (simple-server-creator) under Admin → Plugins before enabling the unified creator.',
        ],
        'built_assets' => [
            'name' => 'Built Pelican CSS and JavaScript',
            'fix' => 'Rebuild the panel assets with yarn install and yarn build in your Pelican folder. :errors',
        ],
        'tested_release' => [
            'name' => 'Tested Pelican release',
            'fix' => 'Simplify is tested on Pelican :tested; this panel reports :release. Installing continues, but report problems on GitHub.',
            'no_version' => 'no version',
        ],
        'database' => [
            'name' => 'Database connection',
            'fix' => 'Repair the panel database connection before continuing.',
        ],
        'cache' => [
            'name' => 'Cache read/write',
            'fix' => 'Repair the configured cache backend and its permissions.',
        ],
        'storage' => [
            'name' => 'Plugin settings storage',
            'fix' => 'Repair the local Laravel storage disk and grant the panel write access.',
        ],
        'queue' => [
            'name' => 'Background queue configured',
            'fix' => 'Configure a persistent Pelican queue connection and start its queue worker.',
        ],
    ],
    'assets' => [
        'manifest' => 'Build manifest is missing or invalid.',
        'missing_entry' => 'Missing entry: :source.',
        'invalid_entry' => 'Invalid build entry.',
        'invalid_css' => 'Invalid CSS list.',
        'invalid_path' => 'Invalid build asset path.',
        'missing_asset' => 'Missing or empty asset: :file.',
        'invalid_imports' => 'Invalid build imports.',
        'missing_import' => 'Missing imported build entry.',
        'no_css' => 'No compiled CSS found.',
        'no_js' => 'No compiled JavaScript found.',
    ],
    'uploads' => [
        'runtime_cli' => 'CLI PHP (verify PHP-FPM separately)',
        'runtime_web' => 'Web PHP',
        'capacity' => 'Upload capacity · :size for banners',
        'capacity_detail' => ':runtime: upload_max_filesize=:upload; post_max_size=:post. Banner uploads are capped at the lower PHP/Livewire limit (maximum 5 MB).',
        'capacity_fix' => 'To allow 5 MB banners, set PHP-FPM upload_max_filesize = 10M and post_max_size = 12M, check any Livewire max rule, then reload PHP-FPM.',
        'proxy' => 'NGINX / proxy upload limit · not verified',
        'proxy_detail' => 'A proxy may reject uploads before PHP receives them (HTTP 413), even when the checks above pass.',
        'proxy_fix' => 'In the NGINX server block serving this panel, set client_max_body_size 12m; check for lower location overrides and upstream proxy limits. Run sudo nginx -t, then sudo systemctl reload nginx. Verify with a real upload. No server configuration is changed automatically.',
    ],
];

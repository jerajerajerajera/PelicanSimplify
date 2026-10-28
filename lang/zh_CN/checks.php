<?php
return [
    'health' => [
        'ok' => ': OK',
    ],
    'system' => [
        'let_s_get_simplify_ready' => '来准备 Simplify 吧',
        'a_quick_check_of_your_panel' => '在选择设置前，先快速检查你的面板。',
        'checking_your_panel' => '正在检查面板…',
        'view_checks' => '查看检查结果',
        'keep_your_queue_worker_and_scheduler' => '请保持队列工作进程和调度器运行。这里无法验证它们的实时状态。',
        'check_again' => '重新检查',
        'go_to_first_setup' => '前往初始设置',
        'preparing_your_system_checks' => '正在准备系统检查…',
        'close_system_checks' => '关闭系统检查',
        'first_setup_eyebrow' => '初始设置 · 系统检查',
        'diagnostics' => '系统诊断',
        'panel_ready' => '面板已就绪',
        'needs_attention_some' => '有几项需要处理',
        'passed' => '已通过 :passed / :total 项检查',
        'ready' => '就绪',
        'needs_attention' => '需要处理',
        'warning_optional' => '警告 · 可选',
    ],
    'requirements' => [
        'php' => [
            'name' => 'PHP 8.3 或更高版本',
            'fix' => '将网页服务器和队列工作进程的 PHP 都升级到 8.3 或更高版本。',
        ],
        'php_extension' => [
            'name' => 'PHP 扩展：:extension',
            'fix' => '在面板和队列工作进程使用的 PHP 中启用 :extension 扩展，然后重启这些服务。',
        ],
        'pelican' => [
            'name' => 'Pelican 应用和 Composer 依赖',
            'fix' => '完成 Pelican 安装并安装其 Composer 依赖。',
        ],
        'composer' => [
            'name' => 'Composer：:class',
            'fix' => '安装与你的 Pelican 版本匹配的 Composer 依赖。',
        ],
        'tabler' => [
            'name' => 'Tabler 图标组件',
            'fix' => '恢复 Composer 依赖 blade-tabler-icons，并清除 Blade 视图缓存。',
        ],
        'node' => [
            'name' => 'Node.js 22.12+ / 24+',
            'fix' => '安装 Node.js 22.12 或更高版本（或 Node.js 24+）。确保网页服务器和队列工作进程的 PATH 中能找到 node。',
        ],
        'yarn' => [
            'name' => 'Yarn',
            'fix' => '安装 Yarn 或通过 Corepack 启用。确保网页服务器和队列工作进程的 PATH 中能找到 yarn。',
        ],
        'writable' => [
            'name' => '可写：:directory',
            'fix' => '授予面板和队列工作进程对 :directory 的读写权限。',
        ],
        'plugin_asset' => [
            'name' => '插件资源：:file',
            'fix' => '重新导入完整的 PelicanSimplify 包；此 CSS/JavaScript 文件缺失、为空或无法读取。',
        ],
        'legacy_creator' => [
            'name' => '独立创建器已停用',
            'fix' => '在启用统一创建器之前，先在 Admin → Plugins 中停用并删除旧的独立创建器（simple-server-creator）。',
        ],
        'built_assets' => [
            'name' => '已构建的 Pelican CSS 和 JavaScript',
            'fix' => '在 Pelican 目录中运行 yarn install 和 yarn build 重新构建面板资源。:errors',
        ],
        'tested_release' => [
            'name' => '经过测试的 Pelican 版本',
            'fix' => 'Simplify 在 Pelican :tested 上测试过；此面板报告的是 :release。安装会继续，但如有问题请在 GitHub 上反馈。',
            'no_version' => '无版本',
        ],
        'database' => [
            'name' => '数据库连接',
            'fix' => '继续之前请修复面板的数据库连接。',
        ],
        'cache' => [
            'name' => '缓存读写',
            'fix' => '修复已配置的缓存后端及其权限。',
        ],
        'storage' => [
            'name' => '插件设置存储',
            'fix' => '修复 Laravel 本地存储磁盘，并授予面板写入权限。',
        ],
        'queue' => [
            'name' => '已配置后台队列',
            'fix' => '配置持久的 Pelican 队列连接并启动其队列工作进程。',
        ],
    ],
    'assets' => [
        'manifest' => '构建清单缺失或无效。',
        'missing_entry' => '缺少入口：:source。',
        'invalid_entry' => '构建入口无效。',
        'invalid_css' => 'CSS 列表无效。',
        'invalid_path' => '构建资源路径无效。',
        'missing_asset' => '资源缺失或为空：:file。',
        'invalid_imports' => '构建导入无效。',
        'missing_import' => '缺少导入的构建入口。',
        'no_css' => '找不到已编译的 CSS。',
        'no_js' => '找不到已编译的 JavaScript。',
    ],
    'uploads' => [
        'runtime_cli' => 'CLI PHP（请另行检查 PHP-FPM）',
        'runtime_web' => 'Web PHP',
        'capacity' => '上传容量 · 横幅最大 :size',
        'capacity_detail' => ':runtime：upload_max_filesize=:upload；post_max_size=:post。横幅上传受 PHP/Livewire 中较低的限制约束（最大 5 MB）。',
        'capacity_fix' => '要允许 5 MB 的横幅，请将 PHP-FPM 的 upload_max_filesize 设为 10M、post_max_size 设为 12M，检查 Livewire 的 max 规则，然后重新加载 PHP-FPM。',
        'proxy' => 'NGINX / 代理上传限制 · 未验证',
        'proxy_detail' => '即使上面的检查都通过，代理也可能在 PHP 收到之前拒绝上传（HTTP 413）。',
        'proxy_fix' => '在为此面板服务的 NGINX server 块中设置 client_max_body_size 12m;，并检查是否有更低的 location 覆盖或上游代理限制。运行 sudo nginx -t，然后运行 sudo systemctl reload nginx。用实际上传来验证。不会自动更改任何服务器配置。',
    ],
];

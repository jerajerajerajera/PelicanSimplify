<?php
return [
    'health' => [
        'ok' => ': OK',
    ],
    'system' => [
        'let_s_get_simplify_ready' => '來準備 Simplify 吧',
        'a_quick_check_of_your_panel' => '在選擇設定前，先快速檢查你的面板。',
        'checking_your_panel' => '正在檢查面板…',
        'view_checks' => '查看檢查結果',
        'keep_your_queue_worker_and_scheduler' => '請保持佇列工作進程和排程器執行。這裡無法驗證它們的即時狀態。',
        'check_again' => '重新檢查',
        'go_to_first_setup' => '前往初始設定',
        'preparing_your_system_checks' => '正在準備系統檢查…',
        'close_system_checks' => '關閉系統檢查',
        'first_setup_eyebrow' => '初始設定 · 系統檢查',
        'diagnostics' => '系統診斷',
        'panel_ready' => '面板已就緒',
        'needs_attention_some' => '有幾項需要處理',
        'passed' => '已透過 :passed / :total 項檢查',
        'ready' => '就緒',
        'needs_attention' => '需要處理',
        'warning_optional' => '警告 · 可選',
    ],
    'requirements' => [
        'php' => [
            'name' => 'PHP 8.3 或更高版本',
            'fix' => '將網頁伺服器和佇列工作進程的 PHP 都升級到 8.3 或更高版本。',
        ],
        'php_extension' => [
            'name' => 'PHP 擴展：:extension',
            'fix' => '在面板和佇列工作進程使用的 PHP 中啟用 :extension 擴展，然後重新啟動這些服務。',
        ],
        'pelican' => [
            'name' => 'Pelican 應用程式和 Composer 相依套件',
            'fix' => '完成 Pelican 安裝並安裝其 Composer 相依套件。',
        ],
        'composer' => [
            'name' => 'Composer：:class',
            'fix' => '安裝與你的 Pelican 版本符合的 Composer 相依套件。',
        ],
        'tabler' => [
            'name' => 'Tabler 圖示元件',
            'fix' => '還原 Composer 相依套件 blade-tabler-icons，並清除 Blade 檢視快取。',
        ],
        'node' => [
            'name' => 'Node.js 22.12+ / 24+',
            'fix' => '安裝 Node.js 22.12 或更高版本（或 Node.js 24+）。確保網頁伺服器和佇列工作進程的 PATH 中能找到 node。',
        ],
        'yarn' => [
            'name' => 'Yarn',
            'fix' => '安裝 Yarn 或透過 Corepack 啟用。確保網頁伺服器和佇列工作進程的 PATH 中能找到 yarn。',
        ],
        'writable' => [
            'name' => '可寫：:directory',
            'fix' => '授予面板和佇列工作進程對 :directory 的讀寫權限。',
        ],
        'plugin_asset' => [
            'name' => '外掛資源：:file',
            'fix' => '重新匯入完整的 PelicanSimplify 包；此 CSS/JavaScript 檔案缺失、為空或無法讀取。',
        ],
        'legacy_creator' => [
            'name' => '獨立建立工具已停用',
            'fix' => '在啟用統一建立工具之前，先在 Admin → Plugins 中停用並刪除舊的獨立建立工具（simple-server-creator）。',
        ],
        'built_assets' => [
            'name' => '已建置的 Pelican CSS 和 JavaScript',
            'fix' => '在 Pelican 目錄中執行 yarn install 和 yarn build 重新建置面板資源。:errors',
        ],
        'tested_release' => [
            'name' => '經過測試的 Pelican 版本',
            'fix' => 'Simplify 在 Pelican :tested 上測試過；此面板報告的是 :release。安裝會繼續，但如有問題請在 GitHub 上反饋。',
            'no_version' => '無版本',
        ],
        'database' => [
            'name' => '資料庫連接',
            'fix' => '繼續之前請修復面板的資料庫連接。',
        ],
        'cache' => [
            'name' => '快取讀寫',
            'fix' => '修復已設定的快取後端及其權限。',
        ],
        'storage' => [
            'name' => '外掛設定儲存',
            'fix' => '修復 Laravel 本地儲存磁碟，並授予面板寫入權限。',
        ],
        'queue' => [
            'name' => '已設定後台佇列',
            'fix' => '設定持久的 Pelican 佇列連接並啟動其佇列工作進程。',
        ],
    ],
    'assets' => [
        'manifest' => '建置清單缺失或無效。',
        'missing_entry' => '缺少入口：:source。',
        'invalid_entry' => '建置入口無效。',
        'invalid_css' => 'CSS 列表無效。',
        'invalid_path' => '建置資源路徑無效。',
        'missing_asset' => '資源缺失或為空：:file。',
        'invalid_imports' => '建置匯入無效。',
        'missing_import' => '缺少匯入的建置入口。',
        'no_css' => '找不到已編譯的 CSS。',
        'no_js' => '找不到已編譯的 JavaScript。',
    ],
    'uploads' => [
        'runtime_cli' => 'CLI PHP（請另行檢查 PHP-FPM）',
        'runtime_web' => 'Web PHP',
        'capacity' => '上傳容量 · 橫幅最大 :size',
        'capacity_detail' => ':runtime：upload_max_filesize=:upload；post_max_size=:post。橫幅上傳受 PHP/Livewire 中較低的限制約束（最大 5 MB）。',
        'capacity_fix' => '要允許 5 MB 的橫幅，請將 PHP-FPM 的 upload_max_filesize 設為 10M、post_max_size 設為 12M，檢查 Livewire 的 max 規則，然後重新載入 PHP-FPM。',
        'proxy' => 'NGINX / 代理上傳限制 · 未驗證',
        'proxy_detail' => '即使上面的檢查都透過，代理也可能在 PHP 收到之前拒絕上傳（HTTP 413）。',
        'proxy_fix' => '在為此面板服務的 NGINX server 塊中設定 client_max_body_size 12m;，並檢查是否有更低的 location 覆蓋或上遊代理限制。執行 sudo nginx -t，然後執行 sudo systemctl reload nginx。用實際上傳來驗證。不會自動變更任何伺服器設定。',
    ],
];

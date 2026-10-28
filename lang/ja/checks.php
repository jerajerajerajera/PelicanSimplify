<?php
return [
    'health' => [
        'ok' => ': OK',
    ],
    'system' => [
        'let_s_get_simplify_ready' => 'Simplify の準備をしましょう',
        'a_quick_check_of_your_panel' => '設定を選ぶ前に、パネルを簡単にチェックします。',
        'checking_your_panel' => 'パネルをチェックしています…',
        'view_checks' => 'チェックを表示',
        'keep_your_queue_worker_and_scheduler' => 'キューワーカーとスケジューラーを動かし続けてください。ここでは稼働状況を確認できません。',
        'check_again' => 'もう一度チェック',
        'go_to_first_setup' => '初期設定へ進む',
        'preparing_your_system_checks' => 'システムチェックを準備しています…',
        'close_system_checks' => 'システムチェックを閉じる',
        'first_setup_eyebrow' => '初期設定 · システムチェック',
        'diagnostics' => 'システム診断',
        'panel_ready' => 'パネルの準備ができました',
        'needs_attention_some' => 'いくつか対応が必要な項目があります',
        'passed' => ':total 件中 :passed 件のチェックに合格',
        'ready' => '準備完了',
        'needs_attention' => '対応が必要',
        'warning_optional' => '警告 · 任意',
    ],
    'requirements' => [
        'php' => [
            'name' => 'PHP 8.3 以降',
            'fix' => 'ウェブサーバーとキューワーカーの両方の PHP を 8.3 以降にアップグレードしてください。',
        ],
        'php_extension' => [
            'name' => 'PHP 拡張機能: :extension',
            'fix' => 'パネルとキューワーカーが使う PHP で :extension 拡張機能を有効にし、それらのサービスを再起動してください。',
        ],
        'pelican' => [
            'name' => 'Pelican アプリケーションと Composer 依存関係',
            'fix' => 'Pelican のインストールを完了し、Composer 依存関係をインストールしてください。',
        ],
        'composer' => [
            'name' => 'Composer: :class',
            'fix' => 'お使いの Pelican リリースに合った Composer 依存関係をインストールしてください。',
        ],
        'tabler' => [
            'name' => 'Tabler アイコンコンポーネント',
            'fix' => 'Composer 依存関係 blade-tabler-icons を復元し、Blade ビューキャッシュをクリアしてください。',
        ],
        'node' => [
            'name' => 'Node.js 22.12+ / 24+',
            'fix' => 'Node.js 22.12 以降(または Node.js 24+)をインストールしてください。ウェブサーバーとキューワーカーの PATH で node を使えるようにしてください。',
        ],
        'yarn' => [
            'name' => 'Yarn',
            'fix' => 'Yarn をインストールするか、Corepack で有効にしてください。ウェブサーバーとキューワーカーの PATH で yarn を使えるようにしてください。',
        ],
        'writable' => [
            'name' => '書き込み可能: :directory',
            'fix' => 'パネルとキューワーカーに :directory の読み書き権限を与えてください。',
        ],
        'plugin_asset' => [
            'name' => 'プラグインのアセット: :file',
            'fix' => 'PelicanSimplify のパッケージ全体を再インポートしてください。この CSS/JavaScript ファイルが見つからないか、空か、読み取れません。',
        ],
        'legacy_creator' => [
            'name' => '単体の作成ツールは廃止',
            'fix' => '統合された作成ツールを有効にする前に、Admin → Plugins で以前の単体作成ツール(simple-server-creator)を無効にして削除してください。',
        ],
        'built_assets' => [
            'name' => 'ビルド済みの Pelican CSS と JavaScript',
            'fix' => 'Pelican フォルダーで yarn install と yarn build を実行して、パネルのアセットを再ビルドしてください。 :errors',
        ],
        'tested_release' => [
            'name' => 'テスト済みの Pelican リリース',
            'fix' => 'Simplify は Pelican :tested でテストされています。このパネルは :release です。インストールは続行されますが、問題があれば GitHub で報告してください。',
            'no_version' => 'バージョンなし',
        ],
        'database' => [
            'name' => 'データベース接続',
            'fix' => '続行する前に、パネルのデータベース接続を修復してください。',
        ],
        'cache' => [
            'name' => 'キャッシュの読み書き',
            'fix' => '設定されたキャッシュバックエンドとその権限を修復してください。',
        ],
        'storage' => [
            'name' => 'プラグイン設定の保存先',
            'fix' => 'Laravel のローカルストレージディスクを修復し、パネルに書き込み権限を与えてください。',
        ],
        'queue' => [
            'name' => 'バックグラウンドキューの設定',
            'fix' => '永続的な Pelican キュー接続を設定し、キューワーカーを起動してください。',
        ],
    ],
    'assets' => [
        'manifest' => 'ビルドマニフェストが見つからないか、無効です。',
        'missing_entry' => 'エントリーがありません: :source。',
        'invalid_entry' => '無効なビルドエントリーです。',
        'invalid_css' => '無効な CSS リストです。',
        'invalid_path' => '無効なビルドアセットのパスです。',
        'missing_asset' => 'アセットが見つからないか空です: :file。',
        'invalid_imports' => '無効なビルドインポートです。',
        'missing_import' => 'インポートされたビルドエントリーがありません。',
        'no_css' => 'コンパイル済みの CSS が見つかりません。',
        'no_js' => 'コンパイル済みの JavaScript が見つかりません。',
    ],
    'uploads' => [
        'runtime_cli' => 'CLI PHP(PHP-FPM は別途確認してください)',
        'runtime_web' => 'Web PHP',
        'capacity' => 'アップロード容量 · バナーは :size まで',
        'capacity_detail' => ':runtime: upload_max_filesize=:upload; post_max_size=:post。バナーのアップロードは PHP/Livewire の低い方の上限(最大 5 MB)に制限されます。',
        'capacity_fix' => '5 MB のバナーを許可するには、PHP-FPM で upload_max_filesize = 10M、post_max_size = 12M に設定し、Livewire の max ルールを確認してから PHP-FPM を再読み込みしてください。',
        'proxy' => 'NGINX / プロキシのアップロード上限 · 未確認',
        'proxy_detail' => '上のチェックに合格していても、PHP に届く前にプロキシがアップロードを拒否することがあります(HTTP 413)。',
        'proxy_fix' => 'このパネルを配信している NGINX の server ブロックで client_max_body_size 12m; を設定し、より低い location の上書きや上流プロキシの上限がないか確認してください。sudo nginx -t を実行してから sudo systemctl reload nginx を実行し、実際にアップロードして確認してください。サーバー設定が自動で変更されることはありません。',
    ],
];

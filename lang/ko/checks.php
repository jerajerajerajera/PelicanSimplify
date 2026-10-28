<?php
return [
    'health' => [
        'ok' => ': OK',
    ],
    'system' => [
        'let_s_get_simplify_ready' => 'Simplify를 준비해 봅시다',
        'a_quick_check_of_your_panel' => '설정을 고르기 전에 패널을 간단히 점검합니다.',
        'checking_your_panel' => '패널을 점검하는 중…',
        'view_checks' => '점검 결과 보기',
        'keep_your_queue_worker_and_scheduler' => '큐 워커와 스케줄러를 계속 실행해 두세요. 실시간 상태는 여기서 확인할 수 없습니다.',
        'check_again' => '다시 점검',
        'go_to_first_setup' => '초기 설정으로 이동',
        'preparing_your_system_checks' => '시스템 점검을 준비하는 중…',
        'close_system_checks' => '시스템 점검 닫기',
        'first_setup_eyebrow' => '초기 설정 · 시스템 점검',
        'diagnostics' => '시스템 진단',
        'panel_ready' => '패널이 준비되었습니다',
        'needs_attention_some' => '몇 가지 확인이 필요합니다',
        'passed' => ':total개 중 :passed개 점검 통과',
        'ready' => '준비됨',
        'needs_attention' => '확인 필요',
        'warning_optional' => '경고 · 선택 사항',
    ],
    'requirements' => [
        'php' => [
            'name' => 'PHP 8.3 이상',
            'fix' => '웹 서버와 큐 워커 모두 PHP를 8.3 이상으로 업그레이드하세요.',
        ],
        'php_extension' => [
            'name' => 'PHP 확장: :extension',
            'fix' => '패널과 큐 워커가 쓰는 PHP에서 :extension 확장을 켜고 해당 서비스를 다시 시작하세요.',
        ],
        'pelican' => [
            'name' => 'Pelican 애플리케이션 및 Composer 의존성',
            'fix' => 'Pelican 설치를 마치고 Composer 의존성을 설치하세요.',
        ],
        'composer' => [
            'name' => 'Composer: :class',
            'fix' => '사용 중인 Pelican 릴리스에 맞는 Composer 의존성을 설치하세요.',
        ],
        'tabler' => [
            'name' => 'Tabler 아이콘 컴포넌트',
            'fix' => 'Composer 의존성 blade-tabler-icons를 복원하고 Blade 뷰 캐시를 지우세요.',
        ],
        'node' => [
            'name' => 'Node.js 22.12+ / 24+',
            'fix' => 'Node.js 22.12 이상(또는 Node.js 24+)을 설치하세요. 웹 서버와 큐 워커의 PATH에서 node를 쓸 수 있게 하세요.',
        ],
        'yarn' => [
            'name' => 'Yarn',
            'fix' => 'Yarn을 설치하거나 Corepack으로 켜세요. 웹 서버와 큐 워커의 PATH에서 yarn을 쓸 수 있게 하세요.',
        ],
        'writable' => [
            'name' => '쓰기 가능: :directory',
            'fix' => '패널과 큐 워커에 :directory 읽기/쓰기 권한을 주세요.',
        ],
        'plugin_asset' => [
            'name' => '플러그인 에셋: :file',
            'fix' => 'PelicanSimplify 패키지 전체를 다시 가져오세요. 이 CSS/JavaScript 파일이 없거나, 비어 있거나, 읽을 수 없습니다.',
        ],
        'legacy_creator' => [
            'name' => '독립형 생성 도구 폐기됨',
            'fix' => '통합 생성 도구를 켜기 전에 Admin → Plugins에서 이전 독립형 생성 도구(simple-server-creator)를 끄고 삭제하세요.',
        ],
        'built_assets' => [
            'name' => '빌드된 Pelican CSS 및 JavaScript',
            'fix' => 'Pelican 폴더에서 yarn install과 yarn build로 패널 에셋을 다시 빌드하세요. :errors',
        ],
        'tested_release' => [
            'name' => '테스트된 Pelican 릴리스',
            'fix' => 'Simplify는 Pelican :tested 에서 테스트되었고, 이 패널은 :release 입니다. 설치는 계속되지만 문제가 있으면 GitHub에 알려 주세요.',
            'no_version' => '버전 없음',
        ],
        'database' => [
            'name' => '데이터베이스 연결',
            'fix' => '계속하기 전에 패널 데이터베이스 연결을 고치세요.',
        ],
        'cache' => [
            'name' => '캐시 읽기/쓰기',
            'fix' => '설정된 캐시 백엔드와 그 권한을 고치세요.',
        ],
        'storage' => [
            'name' => '플러그인 설정 저장소',
            'fix' => 'Laravel 로컬 저장소 디스크를 고치고 패널에 쓰기 권한을 주세요.',
        ],
        'queue' => [
            'name' => '백그라운드 큐 설정됨',
            'fix' => '지속형 Pelican 큐 연결을 설정하고 큐 워커를 시작하세요.',
        ],
    ],
    'assets' => [
        'manifest' => '빌드 매니페스트가 없거나 올바르지 않습니다.',
        'missing_entry' => '항목 없음: :source.',
        'invalid_entry' => '빌드 항목이 올바르지 않습니다.',
        'invalid_css' => 'CSS 목록이 올바르지 않습니다.',
        'invalid_path' => '빌드 에셋 경로가 올바르지 않습니다.',
        'missing_asset' => '에셋이 없거나 비어 있음: :file.',
        'invalid_imports' => '빌드 import가 올바르지 않습니다.',
        'missing_import' => '가져온 빌드 항목이 없습니다.',
        'no_css' => '컴파일된 CSS가 없습니다.',
        'no_js' => '컴파일된 JavaScript가 없습니다.',
    ],
    'uploads' => [
        'runtime_cli' => 'CLI PHP(PHP-FPM은 따로 확인하세요)',
        'runtime_web' => '웹 PHP',
        'capacity' => '업로드 용량 · 배너는 :size 까지',
        'capacity_detail' => ':runtime: upload_max_filesize=:upload; post_max_size=:post. 배너 업로드는 PHP/Livewire 중 낮은 한도(최대 5 MB)로 제한됩니다.',
        'capacity_fix' => '5 MB 배너를 허용하려면 PHP-FPM에서 upload_max_filesize = 10M, post_max_size = 12M으로 설정하고, Livewire max 규칙을 확인한 뒤 PHP-FPM을 다시 불러오세요.',
        'proxy' => 'NGINX / 프록시 업로드 한도 · 확인되지 않음',
        'proxy_detail' => '위 점검을 통과해도 PHP가 받기 전에 프록시가 업로드를 거부할 수 있습니다(HTTP 413).',
        'proxy_fix' => '이 패널을 서비스하는 NGINX server 블록에서 client_max_body_size 12m; 을 설정하고, 더 낮은 location 재정의나 상위 프록시 한도가 없는지 확인하세요. sudo nginx -t 를 실행한 뒤 sudo systemctl reload nginx 를 실행하고, 실제 업로드로 확인하세요. 서버 설정은 자동으로 바뀌지 않습니다.',
    ],
];

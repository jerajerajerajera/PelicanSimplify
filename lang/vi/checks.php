<?php
return [
    'health' => [
        'ok' => ': OK',
    ],
    'system' => [
        'let_s_get_simplify_ready' => 'Hãy chuẩn bị Simplify',
        'a_quick_check_of_your_panel' => 'Kiểm tra nhanh panel trước khi bạn chọn cài đặt.',
        'checking_your_panel' => 'Đang kiểm tra panel…',
        'view_checks' => 'Xem các kiểm tra',
        'keep_your_queue_worker_and_scheduler' => 'Hãy giữ queue worker và scheduler luôn chạy. Không thể xác minh trạng thái trực tiếp của chúng ở đây.',
        'check_again' => 'Kiểm tra lại',
        'go_to_first_setup' => 'Đến thiết lập ban đầu',
        'preparing_your_system_checks' => 'Đang chuẩn bị kiểm tra hệ thống…',
        'close_system_checks' => 'Đóng kiểm tra hệ thống',
        'first_setup_eyebrow' => 'Thiết lập ban đầu · Kiểm tra hệ thống',
        'diagnostics' => 'Chẩn đoán hệ thống',
        'panel_ready' => 'Panel của bạn đã sẵn sàng',
        'needs_attention_some' => 'Có vài điều cần chú ý',
        'passed' => 'Đạt :passed / :total kiểm tra',
        'ready' => 'Sẵn sàng',
        'needs_attention' => 'Cần chú ý',
        'warning_optional' => 'Cảnh báo · tùy chọn',
    ],
    'requirements' => [
        'php' => [
            'name' => 'PHP 8.3 trở lên',
            'fix' => 'Nâng cấp PHP cho cả web server và queue worker lên PHP 8.3 trở lên.',
        ],
        'php_extension' => [
            'name' => 'Phần mở rộng PHP: :extension',
            'fix' => 'Bật phần mở rộng :extension trong môi trường PHP mà panel và queue worker dùng, rồi khởi động lại các dịch vụ đó.',
        ],
        'pelican' => [
            'name' => 'Ứng dụng Pelican và phụ thuộc Composer',
            'fix' => 'Hoàn tất cài đặt Pelican và cài các phụ thuộc Composer.',
        ],
        'composer' => [
            'name' => 'Composer: :class',
            'fix' => 'Cài các phụ thuộc Composer khớp với phiên bản Pelican của bạn.',
        ],
        'tabler' => [
            'name' => 'Thành phần biểu tượng Tabler',
            'fix' => 'Khôi phục phụ thuộc Composer blade-tabler-icons và xóa bộ nhớ đệm view Blade.',
        ],
        'node' => [
            'name' => 'Node.js 22.12+ / 24+',
            'fix' => 'Cài Node.js 22.12 trở lên (hoặc Node.js 24+). Đảm bảo node có trong PATH của web server và queue worker.',
        ],
        'yarn' => [
            'name' => 'Yarn',
            'fix' => 'Cài Yarn hoặc bật qua Corepack. Đảm bảo yarn có trong PATH của web server và queue worker.',
        ],
        'writable' => [
            'name' => 'Ghi được: :directory',
            'fix' => 'Cấp cho panel và queue worker quyền đọc/ghi vào :directory.',
        ],
        'plugin_asset' => [
            'name' => 'Tệp plugin: :file',
            'fix' => 'Nhập lại toàn bộ gói PelicanSimplify; tệp CSS/JavaScript này bị thiếu, trống hoặc không đọc được.',
        ],
        'legacy_creator' => [
            'name' => 'Trình tạo độc lập đã ngừng',
            'fix' => 'Tắt và xóa trình tạo độc lập cũ (simple-server-creator) trong Admin → Plugins trước khi bật trình tạo hợp nhất.',
        ],
        'built_assets' => [
            'name' => 'CSS và JavaScript Pelican đã build',
            'fix' => 'Build lại tài nguyên panel bằng yarn install và yarn build trong thư mục Pelican. :errors',
        ],
        'tested_release' => [
            'name' => 'Phiên bản Pelican đã kiểm thử',
            'fix' => 'Simplify được kiểm thử trên Pelican :tested; panel này báo :release. Cài đặt vẫn tiếp tục, nhưng hãy báo lỗi trên GitHub.',
            'no_version' => 'không có phiên bản',
        ],
        'database' => [
            'name' => 'Kết nối cơ sở dữ liệu',
            'fix' => 'Sửa kết nối cơ sở dữ liệu của panel trước khi tiếp tục.',
        ],
        'cache' => [
            'name' => 'Đọc/ghi bộ nhớ đệm',
            'fix' => 'Sửa backend bộ nhớ đệm đã cấu hình và quyền của nó.',
        ],
        'storage' => [
            'name' => 'Lưu trữ cài đặt plugin',
            'fix' => 'Sửa ổ lưu trữ Laravel cục bộ và cấp quyền ghi cho panel.',
        ],
        'queue' => [
            'name' => 'Đã cấu hình hàng đợi nền',
            'fix' => 'Cấu hình kết nối hàng đợi Pelican bền vững và khởi động queue worker.',
        ],
    ],
    'assets' => [
        'manifest' => 'Manifest build bị thiếu hoặc không hợp lệ.',
        'missing_entry' => 'Thiếu mục: :source.',
        'invalid_entry' => 'Mục build không hợp lệ.',
        'invalid_css' => 'Danh sách CSS không hợp lệ.',
        'invalid_path' => 'Đường dẫn tài nguyên build không hợp lệ.',
        'missing_asset' => 'Tài nguyên bị thiếu hoặc trống: :file.',
        'invalid_imports' => 'Import build không hợp lệ.',
        'missing_import' => 'Thiếu mục build được import.',
        'no_css' => 'Không tìm thấy CSS đã biên dịch.',
        'no_js' => 'Không tìm thấy JavaScript đã biên dịch.',
    ],
    'uploads' => [
        'runtime_cli' => 'PHP CLI (kiểm tra PHP-FPM riêng)',
        'runtime_web' => 'PHP web',
        'capacity' => 'Dung lượng tải lên · :size cho banner',
        'capacity_detail' => ':runtime: upload_max_filesize=:upload; post_max_size=:post. Banner tải lên bị giới hạn bởi mức PHP/Livewire thấp hơn (tối đa 5 MB).',
        'capacity_fix' => 'Để cho phép banner 5 MB, đặt upload_max_filesize = 10M và post_max_size = 12M trong PHP-FPM, kiểm tra quy tắc max của Livewire, rồi tải lại PHP-FPM.',
        'proxy' => 'Giới hạn tải lên NGINX / proxy · chưa xác minh',
        'proxy_detail' => 'Proxy có thể từ chối tải lên trước khi PHP nhận được (HTTP 413), ngay cả khi các kiểm tra trên đều đạt.',
        'proxy_fix' => 'Trong khối server NGINX phục vụ panel này, đặt client_max_body_size 12m; kiểm tra các ghi đè location thấp hơn và giới hạn proxy upstream. Chạy sudo nginx -t, rồi sudo systemctl reload nginx. Xác minh bằng một lần tải lên thật. Không có cấu hình máy chủ nào được thay đổi tự động.',
    ],
];

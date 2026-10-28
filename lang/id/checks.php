<?php
return [
    'health' => [
        'ok' => ': OK',
    ],
    'system' => [
        'let_s_get_simplify_ready' => 'Mari siapkan Simplify',
        'a_quick_check_of_your_panel' => 'Pemeriksaan cepat panel Anda sebelum memilih pengaturan.',
        'checking_your_panel' => 'Memeriksa panel Anda…',
        'view_checks' => 'Lihat pemeriksaan',
        'keep_your_queue_worker_and_scheduler' => 'Biarkan queue worker dan scheduler tetap berjalan. Status langsungnya tidak dapat diverifikasi di sini.',
        'check_again' => 'Periksa lagi',
        'go_to_first_setup' => 'Ke penyiapan pertama',
        'preparing_your_system_checks' => 'Menyiapkan pemeriksaan sistem…',
        'close_system_checks' => 'Tutup pemeriksaan sistem',
        'first_setup_eyebrow' => 'Penyiapan pertama · Pemeriksaan sistem',
        'diagnostics' => 'Diagnostik sistem',
        'panel_ready' => 'Panel Anda siap',
        'needs_attention_some' => 'Beberapa hal perlu diperhatikan',
        'passed' => ':passed dari :total pemeriksaan lulus',
        'ready' => 'Siap',
        'needs_attention' => 'Perlu perhatian',
        'warning_optional' => 'Peringatan · opsional',
    ],
    'requirements' => [
        'php' => [
            'name' => 'PHP 8.3 atau lebih baru',
            'fix' => 'Tingkatkan PHP untuk server web dan queue worker ke PHP 8.3 atau lebih baru.',
        ],
        'php_extension' => [
            'name' => 'Ekstensi PHP: :extension',
            'fix' => 'Aktifkan ekstensi :extension di runtime PHP yang dipakai panel dan queue worker, lalu mulai ulang layanan tersebut.',
        ],
        'pelican' => [
            'name' => 'Aplikasi Pelican dan dependensi Composer',
            'fix' => 'Selesaikan instalasi Pelican dan pasang dependensi Composer-nya.',
        ],
        'composer' => [
            'name' => 'Composer: :class',
            'fix' => 'Pasang dependensi Composer yang sesuai dengan rilis Pelican Anda.',
        ],
        'tabler' => [
            'name' => 'Komponen ikon Tabler',
            'fix' => 'Pulihkan dependensi Composer blade-tabler-icons dan bersihkan cache view Blade.',
        ],
        'node' => [
            'name' => 'Node.js 22.12+ / 24+',
            'fix' => 'Pasang Node.js 22.12 atau lebih baru (atau Node.js 24+). Pastikan node tersedia di PATH server web dan queue worker.',
        ],
        'yarn' => [
            'name' => 'Yarn',
            'fix' => 'Pasang Yarn atau aktifkan melalui Corepack. Pastikan yarn tersedia di PATH server web dan queue worker.',
        ],
        'writable' => [
            'name' => 'Dapat ditulis: :directory',
            'fix' => 'Beri panel dan queue worker akses baca/tulis ke :directory.',
        ],
        'plugin_asset' => [
            'name' => 'Aset plugin: :file',
            'fix' => 'Impor ulang paket PelicanSimplify lengkap; file CSS/JavaScript ini hilang, kosong, atau tidak dapat dibaca.',
        ],
        'legacy_creator' => [
            'name' => 'Pembuat mandiri dihentikan',
            'fix' => 'Nonaktifkan dan hapus pembuat mandiri lama (simple-server-creator) di Admin → Plugins sebelum mengaktifkan pembuat terpadu.',
        ],
        'built_assets' => [
            'name' => 'CSS dan JavaScript Pelican yang sudah di-build',
            'fix' => 'Build ulang aset panel dengan yarn install dan yarn build di folder Pelican Anda. :errors',
        ],
        'tested_release' => [
            'name' => 'Rilis Pelican yang diuji',
            'fix' => 'Simplify diuji pada Pelican :tested; panel ini melaporkan :release. Instalasi tetap berjalan, tetapi laporkan masalah di GitHub.',
            'no_version' => 'tanpa versi',
        ],
        'database' => [
            'name' => 'Koneksi database',
            'fix' => 'Perbaiki koneksi database panel sebelum melanjutkan.',
        ],
        'cache' => [
            'name' => 'Baca/tulis cache',
            'fix' => 'Perbaiki backend cache yang dikonfigurasi beserta izinnya.',
        ],
        'storage' => [
            'name' => 'Penyimpanan pengaturan plugin',
            'fix' => 'Perbaiki disk penyimpanan Laravel lokal dan beri panel akses tulis.',
        ],
        'queue' => [
            'name' => 'Antrean latar belakang dikonfigurasi',
            'fix' => 'Konfigurasikan koneksi antrean Pelican yang persisten dan jalankan queue worker-nya.',
        ],
    ],
    'assets' => [
        'manifest' => 'Manifest build hilang atau tidak valid.',
        'missing_entry' => 'Entri hilang: :source.',
        'invalid_entry' => 'Entri build tidak valid.',
        'invalid_css' => 'Daftar CSS tidak valid.',
        'invalid_path' => 'Jalur aset build tidak valid.',
        'missing_asset' => 'Aset hilang atau kosong: :file.',
        'invalid_imports' => 'Impor build tidak valid.',
        'missing_import' => 'Entri build yang diimpor hilang.',
        'no_css' => 'Tidak ditemukan CSS hasil kompilasi.',
        'no_js' => 'Tidak ditemukan JavaScript hasil kompilasi.',
    ],
    'uploads' => [
        'runtime_cli' => 'PHP CLI (verifikasi PHP-FPM secara terpisah)',
        'runtime_web' => 'PHP web',
        'capacity' => 'Kapasitas unggah · :size untuk banner',
        'capacity_detail' => ':runtime: upload_max_filesize=:upload; post_max_size=:post. Unggahan banner dibatasi oleh batas PHP/Livewire yang lebih rendah (maksimum 5 MB).',
        'capacity_fix' => 'Untuk mengizinkan banner 5 MB, setel upload_max_filesize = 10M dan post_max_size = 12M di PHP-FPM, periksa aturan max Livewire, lalu muat ulang PHP-FPM.',
        'proxy' => 'Batas unggah NGINX / proxy · tidak diverifikasi',
        'proxy_detail' => 'Proxy dapat menolak unggahan sebelum PHP menerimanya (HTTP 413), bahkan jika pemeriksaan di atas lulus.',
        'proxy_fix' => 'Di blok server NGINX yang melayani panel ini, setel client_max_body_size 12m; periksa override location yang lebih rendah dan batas proxy upstream. Jalankan sudo nginx -t, lalu sudo systemctl reload nginx. Verifikasi dengan unggahan nyata. Tidak ada konfigurasi server yang diubah otomatis.',
    ],
];

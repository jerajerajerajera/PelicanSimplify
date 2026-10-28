<?php
return [
    'health' => [
        'ok' => ': OK',
    ],
    'system' => [
        'let_s_get_simplify_ready' => 'Simplify\'ı hazırlayalım',
        'a_quick_check_of_your_panel' => 'Ayarlarını seçmeden önce panelinin hızlı bir kontrolü.',
        'checking_your_panel' => 'Panelin kontrol ediliyor…',
        'view_checks' => 'Kontrolleri görüntüle',
        'keep_your_queue_worker_and_scheduler' => 'Kuyruk çalışanını ve zamanlayıcıyı çalışır durumda tut. Canlı durumları burada doğrulanamaz.',
        'check_again' => 'Tekrar kontrol et',
        'go_to_first_setup' => 'İlk kuruluma git',
        'preparing_your_system_checks' => 'Sistem kontrollerin hazırlanıyor…',
        'close_system_checks' => 'Sistem kontrollerini kapat',
        'first_setup_eyebrow' => 'İlk kurulum · Sistem kontrolü',
        'diagnostics' => 'Sistem tanılaması',
        'panel_ready' => 'Panelin hazır',
        'needs_attention_some' => 'Birkaç şey ilgi bekliyor',
        'passed' => ':total kontrolden :passed tanesi geçti',
        'ready' => 'Hazır',
        'needs_attention' => 'İlgi bekliyor',
        'warning_optional' => 'Uyarı · isteğe bağlı',
    ],
    'requirements' => [
        'php' => [
            'name' => 'PHP 8.3 veya daha yenisi',
            'fix' => 'Hem web sunucusu hem de kuyruk çalışanı için PHP\'yi 8.3 veya daha yeni bir sürüme yükselt.',
        ],
        'php_extension' => [
            'name' => 'PHP eklentisi: :extension',
            'fix' => 'Panelin ve kuyruk çalışanının kullandığı PHP ortamında :extension eklentisini etkinleştir, ardından bu hizmetleri yeniden başlat.',
        ],
        'pelican' => [
            'name' => 'Pelican uygulaması ve Composer bağımlılıkları',
            'fix' => 'Pelican kurulumunu tamamla ve Composer bağımlılıklarını yükle.',
        ],
        'composer' => [
            'name' => 'Composer: :class',
            'fix' => 'Pelican sürümüne uyan Composer bağımlılıklarını yükle.',
        ],
        'tabler' => [
            'name' => 'Tabler simge bileşenleri',
            'fix' => 'blade-tabler-icons Composer bağımlılığını geri yükle ve Blade görünüm önbelleğini temizle.',
        ],
        'node' => [
            'name' => 'Node.js 22.12+ / 24+',
            'fix' => 'Node.js 22.12 veya daha yenisini (ya da Node.js 24+) yükle. node komutunu web sunucusu ve kuyruk çalışanının PATH\'inde kullanılabilir yap.',
        ],
        'yarn' => [
            'name' => 'Yarn',
            'fix' => 'Yarn\'ı yükle veya Corepack ile etkinleştir. yarn komutunu web sunucusu ve kuyruk çalışanının PATH\'inde kullanılabilir yap.',
        ],
        'writable' => [
            'name' => 'Yazılabilir: :directory',
            'fix' => 'Panele ve kuyruk çalışanına şu klasör için okuma/yazma erişimi ver: :directory.',
        ],
        'plugin_asset' => [
            'name' => 'Eklenti dosyası: :file',
            'fix' => 'PelicanSimplify paketinin tamamını yeniden içe aktar; bu CSS/JavaScript dosyası eksik, boş veya okunamıyor.',
        ],
        'legacy_creator' => [
            'name' => 'Bağımsız oluşturucu kullanımdan kaldırıldı',
            'fix' => 'Birleşik oluşturucuyu etkinleştirmeden önce eski bağımsız oluşturucuyu (simple-server-creator) Admin → Plugins altında devre dışı bırak ve sil.',
        ],
        'built_assets' => [
            'name' => 'Derlenmiş Pelican CSS ve JavaScript',
            'fix' => 'Pelican klasöründe yarn install ve yarn build ile panel dosyalarını yeniden derle. :errors',
        ],
        'tested_release' => [
            'name' => 'Test edilmiş Pelican sürümü',
            'fix' => 'Simplify, Pelican :tested üzerinde test edildi; bu panel :release bildiriyor. Kurulum devam eder, ancak sorunları GitHub\'da bildir.',
            'no_version' => 'sürüm yok',
        ],
        'database' => [
            'name' => 'Veritabanı bağlantısı',
            'fix' => 'Devam etmeden önce panelin veritabanı bağlantısını onar.',
        ],
        'cache' => [
            'name' => 'Önbellek okuma/yazma',
            'fix' => 'Yapılandırılmış önbellek arka ucunu ve izinlerini onar.',
        ],
        'storage' => [
            'name' => 'Eklenti ayarları depolaması',
            'fix' => 'Yerel Laravel depolama diskini onar ve panele yazma erişimi ver.',
        ],
        'queue' => [
            'name' => 'Arka plan kuyruğu yapılandırıldı',
            'fix' => 'Kalıcı bir Pelican kuyruk bağlantısı yapılandır ve kuyruk çalışanını başlat.',
        ],
    ],
    'assets' => [
        'manifest' => 'Derleme manifestosu eksik veya geçersiz.',
        'missing_entry' => 'Eksik giriş: :source.',
        'invalid_entry' => 'Geçersiz derleme girişi.',
        'invalid_css' => 'Geçersiz CSS listesi.',
        'invalid_path' => 'Geçersiz derleme dosyası yolu.',
        'missing_asset' => 'Eksik veya boş dosya: :file.',
        'invalid_imports' => 'Geçersiz derleme içe aktarmaları.',
        'missing_import' => 'İçe aktarılan derleme girişi eksik.',
        'no_css' => 'Derlenmiş CSS bulunamadı.',
        'no_js' => 'Derlenmiş JavaScript bulunamadı.',
    ],
    'uploads' => [
        'runtime_cli' => 'CLI PHP (PHP-FPM\'i ayrıca doğrula)',
        'runtime_web' => 'Web PHP',
        'capacity' => 'Yükleme kapasitesi · banner\'lar için :size',
        'capacity_detail' => ':runtime: upload_max_filesize=:upload; post_max_size=:post. Banner yüklemeleri, düşük olan PHP/Livewire sınırıyla kısıtlanır (en fazla 5 MB).',
        'capacity_fix' => '5 MB\'lık banner\'lara izin vermek için PHP-FPM\'de upload_max_filesize = 10M ve post_max_size = 12M ayarla, varsa Livewire max kuralını kontrol et, ardından PHP-FPM\'i yeniden yükle.',
        'proxy' => 'NGINX / proxy yükleme sınırı · doğrulanmadı',
        'proxy_detail' => 'Bir proxy, yukarıdaki kontroller geçse bile yüklemeleri PHP\'ye ulaşmadan reddedebilir (HTTP 413).',
        'proxy_fix' => 'Bu paneli sunan NGINX server bloğunda client_max_body_size 12m; ayarla, daha düşük location geçersiz kılmalarını ve üst proxy sınırlarını kontrol et. sudo nginx -t, ardından sudo systemctl reload nginx çalıştır. Gerçek bir yüklemeyle doğrula. Sunucu yapılandırması otomatik olarak değiştirilmez.',
    ],
];

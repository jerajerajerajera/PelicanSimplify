<?php
return [
    'health' => [
        'ok' => ': OK',
    ],
    'system' => [
        'let_s_get_simplify_ready' => 'لنجهّز Simplify',
        'a_quick_check_of_your_panel' => 'فحص سريع للوحتك قبل أن تختار إعداداتك.',
        'checking_your_panel' => 'جارٍ فحص اللوحة…',
        'view_checks' => 'عرض الفحوصات',
        'keep_your_queue_worker_and_scheduler' => 'أبقِ عامل الطابور والمجدول قيد التشغيل. لا يمكن التحقق من حالتهما المباشرة هنا.',
        'check_again' => 'افحص مرة أخرى',
        'go_to_first_setup' => 'الانتقال إلى الإعداد الأول',
        'preparing_your_system_checks' => 'جارٍ تجهيز فحوصات النظام…',
        'close_system_checks' => 'إغلاق فحوصات النظام',
        'first_setup_eyebrow' => 'الإعداد الأول · فحص النظام',
        'diagnostics' => 'تشخيص النظام',
        'panel_ready' => 'لوحتك جاهزة',
        'needs_attention_some' => 'بعض الأمور تحتاج إلى انتباه',
        'passed' => 'نجح :passed من :total فحصًا',
        'ready' => 'جاهز',
        'needs_attention' => 'يحتاج إلى انتباه',
        'warning_optional' => 'تحذير · اختياري',
    ],
    'requirements' => [
        'php' => [
            'name' => 'PHP 8.3 أو أحدث',
            'fix' => 'رقِّ PHP لكل من خادم الويب وعامل الطابور إلى PHP 8.3 أو أحدث.',
        ],
        'php_extension' => [
            'name' => 'امتداد PHP: :extension',
            'fix' => 'فعّل الامتداد :extension في بيئة PHP التي تستخدمها اللوحة وعامل الطابور، ثم أعد تشغيل هذه الخدمات.',
        ],
        'pelican' => [
            'name' => 'تطبيق Pelican واعتماديات Composer',
            'fix' => 'أكمل تثبيت Pelican وثبّت اعتماديات Composer الخاصة به.',
        ],
        'composer' => [
            'name' => 'Composer: :class',
            'fix' => 'ثبّت اعتماديات Composer المطابقة لإصدار Pelican لديك.',
        ],
        'tabler' => [
            'name' => 'مكوّنات أيقونات Tabler',
            'fix' => 'استعد اعتمادية Composer المسماة blade-tabler-icons وامسح ذاكرة التخزين المؤقت لعروض Blade.',
        ],
        'node' => [
            'name' => 'Node.js 22.12+ / 24+',
            'fix' => 'ثبّت Node.js 22.12 أو أحدث (أو Node.js 24+). اجعل node متاحًا في PATH لخادم الويب وعامل الطابور.',
        ],
        'yarn' => [
            'name' => 'Yarn',
            'fix' => 'ثبّت Yarn أو فعّله عبر Corepack. اجعل yarn متاحًا في PATH لخادم الويب وعامل الطابور.',
        ],
        'writable' => [
            'name' => 'قابلية الكتابة: :directory',
            'fix' => 'امنح اللوحة وعامل الطابور صلاحية القراءة/الكتابة على :directory.',
        ],
        'plugin_asset' => [
            'name' => 'ملف الإضافة: :file',
            'fix' => 'أعد استيراد حزمة PelicanSimplify كاملة؛ ملف CSS/JavaScript هذا مفقود أو فارغ أو غير قابل للقراءة.',
        ],
        'legacy_creator' => [
            'name' => 'تم إيقاف أداة الإنشاء المستقلة',
            'fix' => 'عطّل أداة الإنشاء المستقلة السابقة (simple-server-creator) واحذفها من Admin → Plugins قبل تفعيل أداة الإنشاء الموحدة.',
        ],
        'built_assets' => [
            'name' => 'ملفات CSS وJavaScript المبنية لـ Pelican',
            'fix' => 'أعد بناء ملفات اللوحة باستخدام yarn install وyarn build في مجلد Pelican. :errors',
        ],
        'tested_release' => [
            'name' => 'إصدار Pelican مُختبَر',
            'fix' => 'تم اختبار Simplify على Pelican :tested؛ وهذه اللوحة تُبلغ عن :release. يستمر التثبيت، لكن أبلغ عن المشكلات على GitHub.',
            'no_version' => 'بلا إصدار',
        ],
        'database' => [
            'name' => 'الاتصال بقاعدة البيانات',
            'fix' => 'أصلح اتصال اللوحة بقاعدة البيانات قبل المتابعة.',
        ],
        'cache' => [
            'name' => 'قراءة/كتابة ذاكرة التخزين المؤقت',
            'fix' => 'أصلح واجهة التخزين المؤقت المضبوطة وصلاحياتها.',
        ],
        'storage' => [
            'name' => 'تخزين إعدادات الإضافة',
            'fix' => 'أصلح قرص التخزين المحلي في Laravel وامنح اللوحة صلاحية الكتابة.',
        ],
        'queue' => [
            'name' => 'طابور الخلفية مضبوط',
            'fix' => 'اضبط اتصال طابور Pelican دائمًا وشغّل عامل الطابور الخاص به.',
        ],
    ],
    'assets' => [
        'manifest' => 'ملف بيان البناء مفقود أو غير صالح.',
        'missing_entry' => 'مدخل مفقود: :source.',
        'invalid_entry' => 'مدخل بناء غير صالح.',
        'invalid_css' => 'قائمة CSS غير صالحة.',
        'invalid_path' => 'مسار ملف بناء غير صالح.',
        'missing_asset' => 'ملف مفقود أو فارغ: :file.',
        'invalid_imports' => 'استيرادات بناء غير صالحة.',
        'missing_import' => 'مدخل بناء مستورد مفقود.',
        'no_css' => 'لم يُعثر على CSS مُترجَم.',
        'no_js' => 'لم يُعثر على JavaScript مُترجَم.',
    ],
    'uploads' => [
        'runtime_cli' => 'PHP لسطر الأوامر (تحقق من PHP-FPM بشكل منفصل)',
        'runtime_web' => 'PHP للويب',
        'capacity' => 'سعة الرفع · :size للافتات',
        'capacity_detail' => ':runtime: upload_max_filesize=:upload؛ post_max_size=:post. يقتصر رفع اللافتات على الحد الأدنى بين PHP وLivewire (بحد أقصى 5 MB).',
        'capacity_fix' => 'للسماح بلافتات حجمها 5 MB، اضبط في PHP-FPM ‏upload_max_filesize = 10M وpost_max_size = 12M، وتحقق من أي قاعدة max في Livewire، ثم أعد تحميل PHP-FPM.',
        'proxy' => 'حد الرفع في NGINX / الوكيل · لم يُتحقق منه',
        'proxy_detail' => 'قد يرفض الوكيل عمليات الرفع قبل أن يستلمها PHP (HTTP 413)، حتى عندما تنجح الفحوصات أعلاه.',
        'proxy_fix' => 'في كتلة server الخاصة بـ NGINX التي تخدم هذه اللوحة، اضبط client_max_body_size 12m؛ وتحقق من وجود قيم location أقل ومن حدود الوكيل الأعلى. شغّل sudo nginx -t، ثم sudo systemctl reload nginx. تحقق برفع حقيقي. لا يُغيَّر أي إعداد للخادم تلقائيًا.',
    ],
];

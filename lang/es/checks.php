<?php
return [
    'health' => [
        'ok' => ': OK',
    ],
    'system' => [
        'let_s_get_simplify_ready' => 'Preparemos Simplify',
        'a_quick_check_of_your_panel' => 'Una comprobación rápida de tu panel antes de elegir tus ajustes.',
        'checking_your_panel' => 'Comprobando tu panel…',
        'view_checks' => 'Ver comprobaciones',
        'keep_your_queue_worker_and_scheduler' => 'Mantén en marcha el queue worker y el scheduler. Su estado en vivo no se puede verificar aquí.',
        'check_again' => 'Comprobar de nuevo',
        'go_to_first_setup' => 'Ir a la primera configuración',
        'preparing_your_system_checks' => 'Preparando las comprobaciones del sistema…',
        'close_system_checks' => 'Cerrar comprobaciones del sistema',
        'first_setup_eyebrow' => 'Primera configuración · Comprobación del sistema',
        'diagnostics' => 'Diagnóstico del sistema',
        'panel_ready' => 'Tu panel está listo',
        'needs_attention_some' => 'Algunas cosas necesitan atención',
        'passed' => ':passed de :total comprobaciones superadas',
        'ready' => 'Listo',
        'needs_attention' => 'Necesita atención',
        'warning_optional' => 'Aviso · opcional',
    ],
    'requirements' => [
        'php' => [
            'name' => 'PHP 8.3 o más reciente',
            'fix' => 'Actualiza PHP del servidor web y del queue worker a PHP 8.3 o más reciente.',
        ],
        'php_extension' => [
            'name' => 'Extensión de PHP: :extension',
            'fix' => 'Activa la extensión :extension en el PHP que usan el panel y el queue worker, y reinicia esos servicios.',
        ],
        'pelican' => [
            'name' => 'Aplicación Pelican y dependencias de Composer',
            'fix' => 'Completa la instalación de Pelican e instala sus dependencias de Composer.',
        ],
        'composer' => [
            'name' => 'Composer: :class',
            'fix' => 'Instala las dependencias de Composer de tu versión de Pelican.',
        ],
        'tabler' => [
            'name' => 'Componentes de iconos Tabler',
            'fix' => 'Restaura la dependencia de Composer blade-tabler-icons y vacía la caché de vistas de Blade.',
        ],
        'node' => [
            'name' => 'Node.js 22.12+ / 24+',
            'fix' => 'Instala Node.js 22.12 o más reciente (o Node.js 24+). Haz que node esté en el PATH del servidor web y del queue worker.',
        ],
        'yarn' => [
            'name' => 'Yarn',
            'fix' => 'Instala Yarn o actívalo con Corepack. Haz que yarn esté en el PATH del servidor web y del queue worker.',
        ],
        'writable' => [
            'name' => ':directory con escritura',
            'fix' => 'Da al panel y al queue worker acceso de lectura/escritura a :directory.',
        ],
        'plugin_asset' => [
            'name' => 'Archivo del plugin: :file',
            'fix' => 'Vuelve a importar el paquete completo de PelicanSimplify; este archivo CSS/JavaScript falta, está vacío o no se puede leer.',
        ],
        'legacy_creator' => [
            'name' => 'Creador independiente retirado',
            'fix' => 'Desactiva y elimina el antiguo creador independiente (simple-server-creator) en Admin → Plugins antes de activar el creador unificado.',
        ],
        'built_assets' => [
            'name' => 'CSS y JavaScript de Pelican compilados',
            'fix' => 'Vuelve a compilar los archivos del panel con yarn install y yarn build en tu carpeta de Pelican. :errors',
        ],
        'tested_release' => [
            'name' => 'Versión de Pelican probada',
            'fix' => 'Simplify está probado en Pelican :tested; este panel indica :release. La instalación continúa, pero informa de problemas en GitHub.',
            'no_version' => 'sin versión',
        ],
        'database' => [
            'name' => 'Conexión a la base de datos',
            'fix' => 'Repara la conexión del panel a la base de datos antes de continuar.',
        ],
        'cache' => [
            'name' => 'Lectura/escritura de caché',
            'fix' => 'Repara el backend de caché configurado y sus permisos.',
        ],
        'storage' => [
            'name' => 'Almacenamiento de ajustes del plugin',
            'fix' => 'Repara el disco de almacenamiento local de Laravel y da al panel acceso de escritura.',
        ],
        'queue' => [
            'name' => 'Cola en segundo plano configurada',
            'fix' => 'Configura una conexión de cola persistente de Pelican e inicia su queue worker.',
        ],
    ],
    'assets' => [
        'manifest' => 'El manifiesto de compilación falta o no es válido.',
        'missing_entry' => 'Falta la entrada: :source.',
        'invalid_entry' => 'Entrada de compilación no válida.',
        'invalid_css' => 'Lista CSS no válida.',
        'invalid_path' => 'Ruta de archivo de compilación no válida.',
        'missing_asset' => 'Archivo ausente o vacío: :file.',
        'invalid_imports' => 'Importaciones de compilación no válidas.',
        'missing_import' => 'Falta una entrada de compilación importada.',
        'no_css' => 'No se encontró CSS compilado.',
        'no_js' => 'No se encontró JavaScript compilado.',
    ],
    'uploads' => [
        'runtime_cli' => 'PHP de CLI (comprueba PHP-FPM por separado)',
        'runtime_web' => 'PHP web',
        'capacity' => 'Capacidad de subida · :size para banners',
        'capacity_detail' => ':runtime: upload_max_filesize=:upload; post_max_size=:post. Las subidas de banners se limitan al menor límite de PHP/Livewire (máximo 5 MB).',
        'capacity_fix' => 'Para permitir banners de 5 MB, pon en PHP-FPM upload_max_filesize = 10M y post_max_size = 12M, revisa cualquier regla max de Livewire y recarga PHP-FPM.',
        'proxy' => 'Límite de subida de NGINX / proxy · sin verificar',
        'proxy_detail' => 'Un proxy puede rechazar subidas antes de que lleguen a PHP (HTTP 413), aunque las comprobaciones de arriba pasen.',
        'proxy_fix' => 'En el bloque server de NGINX que sirve este panel, pon client_max_body_size 12m; busca location con límites más bajos y límites de proxies anteriores. Ejecuta sudo nginx -t y luego sudo systemctl reload nginx. Verifica con una subida real. No se cambia ninguna configuración del servidor automáticamente.',
    ],
];

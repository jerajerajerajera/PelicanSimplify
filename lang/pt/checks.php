<?php
return [
    'health' => [
        'ok' => ': OK',
    ],
    'system' => [
        'let_s_get_simplify_ready' => 'Vamos preparar o Simplify',
        'a_quick_check_of_your_panel' => 'Uma verificação rápida do seu painel antes de escolher as definições.',
        'checking_your_panel' => 'A verificar o seu painel…',
        'view_checks' => 'Ver verificações',
        'keep_your_queue_worker_and_scheduler' => 'Mantenha o queue worker e o scheduler em execução. O estado em direto deles não pode ser verificado aqui.',
        'check_again' => 'Verificar de novo',
        'go_to_first_setup' => 'Ir para a primeira configuração',
        'preparing_your_system_checks' => 'A preparar as verificações do sistema…',
        'close_system_checks' => 'Fechar verificações do sistema',
        'first_setup_eyebrow' => 'Primeira configuração · Verificação do sistema',
        'diagnostics' => 'Diagnóstico do sistema',
        'panel_ready' => 'O seu painel está pronto',
        'needs_attention_some' => 'Algumas coisas precisam de atenção',
        'passed' => ':passed de :total verificações aprovadas',
        'ready' => 'Pronto',
        'needs_attention' => 'Precisa de atenção',
        'warning_optional' => 'Aviso · opcional',
    ],
    'requirements' => [
        'php' => [
            'name' => 'PHP 8.3 ou mais recente',
            'fix' => 'Atualize o PHP do servidor web e do queue worker para PHP 8.3 ou mais recente.',
        ],
        'php_extension' => [
            'name' => 'Extensão PHP: :extension',
            'fix' => 'Ative a extensão :extension no PHP usado pelo painel e pelo queue worker, depois reinicie esses serviços.',
        ],
        'pelican' => [
            'name' => 'Aplicação Pelican e dependências do Composer',
            'fix' => 'Conclua a instalação do Pelican e instale as suas dependências do Composer.',
        ],
        'composer' => [
            'name' => 'Composer: :class',
            'fix' => 'Instale as dependências do Composer da sua versão do Pelican.',
        ],
        'tabler' => [
            'name' => 'Componentes de ícones Tabler',
            'fix' => 'Restaure a dependência do Composer blade-tabler-icons e limpe o cache de views do Blade.',
        ],
        'node' => [
            'name' => 'Node.js 22.12+ / 24+',
            'fix' => 'Instale o Node.js 22.12 ou mais recente (ou Node.js 24+). Deixe o node disponível no PATH do servidor web e do queue worker.',
        ],
        'yarn' => [
            'name' => 'Yarn',
            'fix' => 'Instale o Yarn ou ative-o pelo Corepack. Deixe o yarn disponível no PATH do servidor web e do queue worker.',
        ],
        'writable' => [
            'name' => ':directory gravável',
            'fix' => 'Dê ao painel e ao queue worker acesso de leitura/gravação a :directory.',
        ],
        'plugin_asset' => [
            'name' => 'Ficheiro do plugin: :file',
            'fix' => 'Reimporte o pacote completo do PelicanSimplify; este ficheiro CSS/JavaScript está em falta, vazio ou ilegível.',
        ],
        'legacy_creator' => [
            'name' => 'Criador independente aposentado',
            'fix' => 'Desative e elimine o antigo criador independente (simple-server-creator) em Admin → Plugins antes de ativar o criador unificado.',
        ],
        'built_assets' => [
            'name' => 'CSS e JavaScript do Pelican compilados',
            'fix' => 'Recompile os ficheiros do painel com yarn install e yarn build na pasta do Pelican. :errors',
        ],
        'tested_release' => [
            'name' => 'Versão do Pelican testada',
            'fix' => 'O Simplify é testado no Pelican :tested; este painel informa :release. A instalação continua, mas relate problemas no GitHub.',
            'no_version' => 'sem versão',
        ],
        'database' => [
            'name' => 'Ligação com a base de dados',
            'fix' => 'Corrija a ligação do painel com a base de dados antes de continuar.',
        ],
        'cache' => [
            'name' => 'Leitura/gravação do cache',
            'fix' => 'Corrija o backend de cache configurado e as suas permissões.',
        ],
        'storage' => [
            'name' => 'Armazenamento das definições do plugin',
            'fix' => 'Corrija o disco de armazenamento local do Laravel e dê ao painel acesso de gravação.',
        ],
        'queue' => [
            'name' => 'Fila em segundo plano configurada',
            'fix' => 'Configure uma ligação de fila persistente do Pelican e inicie o queue worker.',
        ],
    ],
    'assets' => [
        'manifest' => 'O manifesto de build está em falta ou é inválido.',
        'missing_entry' => 'Entrada em falta: :source.',
        'invalid_entry' => 'Entrada de build inválida.',
        'invalid_css' => 'Lista de CSS inválida.',
        'invalid_path' => 'Caminho de ficheiro de build inválido.',
        'missing_asset' => 'Ficheiro em falta ou vazio: :file.',
        'invalid_imports' => 'Imports de build inválidos.',
        'missing_import' => 'Entrada de build importada em falta.',
        'no_css' => 'Nenhum CSS compilado encontrado.',
        'no_js' => 'Nenhum JavaScript compilado encontrado.',
    ],
    'uploads' => [
        'runtime_cli' => 'PHP de CLI (verifique o PHP-FPM separadamente)',
        'runtime_web' => 'PHP web',
        'capacity' => 'Capacidade de upload · :size para banners',
        'capacity_detail' => ':runtime: upload_max_filesize=:upload; post_max_size=:post. Os uploads de banner ficam limitados ao menor limite de PHP/Livewire (máximo 5 MB).',
        'capacity_fix' => 'Para permitir banners de 5 MB, defina no PHP-FPM upload_max_filesize = 10M e post_max_size = 12M, verifique qualquer regra max do Livewire e recarregue o PHP-FPM.',
        'proxy' => 'Limite de upload do NGINX / proxy · não verificado',
        'proxy_detail' => 'Um proxy pode recusar uploads antes de chegarem ao PHP (HTTP 413), mesmo que as verificações acima passem.',
        'proxy_fix' => 'No bloco server do NGINX que serve este painel, defina client_max_body_size 12m; verifique locations com limites menores e limites de proxies anteriores. Execute sudo nginx -t e depois sudo systemctl reload nginx. Verifique com um upload real. Nenhuma configuração do servidor é alterada automaticamente.',
    ],
];

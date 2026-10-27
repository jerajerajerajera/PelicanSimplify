<?php
return [
    'health' => [
        'ok' => ': OK',
    ],
    'system' => [
        'let_s_get_simplify_ready' => 'Prepariamo Simplify',
        'a_quick_check_of_your_panel' => 'Un rapido controllo del tuo pannello prima di scegliere le impostazioni.',
        'checking_your_panel' => 'Controllo del pannello…',
        'view_checks' => 'Vedi i controlli',
        'keep_your_queue_worker_and_scheduler' => 'Tieni in funzione il queue worker e lo scheduler. Il loro stato in tempo reale non può essere verificato qui.',
        'check_again' => 'Controlla di nuovo',
        'go_to_first_setup' => 'Vai alla prima configurazione',
        'preparing_your_system_checks' => 'Preparazione dei controlli di sistema…',
        'close_system_checks' => 'Chiudi i controlli di sistema',
        'first_setup_eyebrow' => 'Prima configurazione · Controllo di sistema',
        'diagnostics' => 'Diagnostica di sistema',
        'panel_ready' => 'Il tuo pannello è pronto',
        'needs_attention_some' => 'Alcune cose richiedono attenzione',
        'passed' => ':passed controlli superati su :total',
        'ready' => 'Pronto',
        'needs_attention' => 'Richiede attenzione',
        'warning_optional' => 'Avviso · opzionale',
    ],
    'requirements' => [
        'php' => [
            'name' => 'PHP 8.3 o successivo',
            'fix' => 'Aggiorna PHP del server web e del queue worker a PHP 8.3 o successivo.',
        ],
        'php_extension' => [
            'name' => 'Estensione PHP: :extension',
            'fix' => 'Attiva l\'estensione :extension nel PHP usato dal pannello e dal queue worker, poi riavvia questi servizi.',
        ],
        'pelican' => [
            'name' => 'Applicazione Pelican e dipendenze Composer',
            'fix' => 'Completa l\'installazione di Pelican e installa le sue dipendenze Composer.',
        ],
        'composer' => [
            'name' => 'Composer: :class',
            'fix' => 'Installa le dipendenze Composer corrispondenti alla tua versione di Pelican.',
        ],
        'tabler' => [
            'name' => 'Componenti icone Tabler',
            'fix' => 'Ripristina la dipendenza Composer blade-tabler-icons e svuota la cache delle viste Blade.',
        ],
        'node' => [
            'name' => 'Node.js 22.12+ / 24+',
            'fix' => 'Installa Node.js 22.12 o successivo (o Node.js 24+). Rendi node disponibile nel PATH del server web e del queue worker.',
        ],
        'yarn' => [
            'name' => 'Yarn',
            'fix' => 'Installa Yarn o attivalo con Corepack. Rendi yarn disponibile nel PATH del server web e del queue worker.',
        ],
        'writable' => [
            'name' => ':directory scrivibile',
            'fix' => 'Dai al pannello e al queue worker accesso in lettura/scrittura a :directory.',
        ],
        'plugin_asset' => [
            'name' => 'File del plugin: :file',
            'fix' => 'Reimporta il pacchetto completo di PelicanSimplify; questo file CSS/JavaScript manca, è vuoto o illeggibile.',
        ],
        'legacy_creator' => [
            'name' => 'Creatore autonomo dismesso',
            'fix' => 'Disattiva ed elimina il vecchio creatore autonomo (simple-server-creator) in Admin → Plugins prima di attivare il creatore unificato.',
        ],
        'built_assets' => [
            'name' => 'CSS e JavaScript di Pelican compilati',
            'fix' => 'Ricompila i file del pannello con yarn install e yarn build nella cartella di Pelican. :errors',
        ],
        'tested_release' => [
            'name' => 'Versione di Pelican testata',
            'fix' => 'Simplify è testato su Pelican :tested; questo pannello indica :release. L\'installazione continua, ma segnala i problemi su GitHub.',
            'no_version' => 'nessuna versione',
        ],
        'database' => [
            'name' => 'Connessione al database',
            'fix' => 'Ripara la connessione del pannello al database prima di continuare.',
        ],
        'cache' => [
            'name' => 'Lettura/scrittura cache',
            'fix' => 'Ripara il backend di cache configurato e i suoi permessi.',
        ],
        'storage' => [
            'name' => 'Archivio impostazioni del plugin',
            'fix' => 'Ripara il disco di archiviazione locale di Laravel e dai al pannello accesso in scrittura.',
        ],
        'queue' => [
            'name' => 'Coda in background configurata',
            'fix' => 'Configura una connessione di coda persistente di Pelican e avvia il suo queue worker.',
        ],
    ],
    'assets' => [
        'manifest' => 'Il manifest di build manca o non è valido.',
        'missing_entry' => 'Voce mancante: :source.',
        'invalid_entry' => 'Voce di build non valida.',
        'invalid_css' => 'Elenco CSS non valido.',
        'invalid_path' => 'Percorso file di build non valido.',
        'missing_asset' => 'File mancante o vuoto: :file.',
        'invalid_imports' => 'Import di build non validi.',
        'missing_import' => 'Voce di build importata mancante.',
        'no_css' => 'Nessun CSS compilato trovato.',
        'no_js' => 'Nessun JavaScript compilato trovato.',
    ],
    'uploads' => [
        'runtime_cli' => 'PHP da CLI (verifica PHP-FPM a parte)',
        'runtime_web' => 'PHP web',
        'capacity' => 'Capacità di upload · :size per i banner',
        'capacity_detail' => ':runtime: upload_max_filesize=:upload; post_max_size=:post. Gli upload dei banner sono limitati al limite più basso tra PHP e Livewire (massimo 5 MB).',
        'capacity_fix' => 'Per consentire banner da 5 MB, imposta in PHP-FPM upload_max_filesize = 10M e post_max_size = 12M, controlla eventuali regole max di Livewire, poi ricarica PHP-FPM.',
        'proxy' => 'Limite di upload NGINX / proxy · non verificato',
        'proxy_detail' => 'Un proxy può rifiutare gli upload prima che arrivino a PHP (HTTP 413), anche se i controlli sopra passano.',
        'proxy_fix' => 'Nel blocco server di NGINX che serve questo pannello, imposta client_max_body_size 12m; controlla eventuali location con limiti più bassi e i limiti dei proxy a monte. Esegui sudo nginx -t, poi sudo systemctl reload nginx. Verifica con un upload reale. Nessuna configurazione del server viene modificata automaticamente.',
    ],
];

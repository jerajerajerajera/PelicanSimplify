<?php
return [
    'health' => [
        'ok' => ' : OK',
    ],
    'system' => [
        'let_s_get_simplify_ready' => 'Préparons Simplify',
        'a_quick_check_of_your_panel' => 'Une vérification rapide de votre panel avant de choisir vos paramètres.',
        'checking_your_panel' => 'Vérification de votre panel…',
        'view_checks' => 'Voir les vérifications',
        'keep_your_queue_worker_and_scheduler' => 'Gardez votre queue worker et votre scheduler en marche. Leur état en direct ne peut pas être vérifié ici.',
        'check_again' => 'Vérifier à nouveau',
        'go_to_first_setup' => 'Aller à la première configuration',
        'preparing_your_system_checks' => 'Préparation des vérifications système…',
        'close_system_checks' => 'Fermer les vérifications système',
        'first_setup_eyebrow' => 'Première configuration · Vérification système',
        'diagnostics' => 'Diagnostic système',
        'panel_ready' => 'Votre panel est prêt',
        'needs_attention_some' => 'Quelques points demandent votre attention',
        'passed' => ':passed vérifications réussies sur :total',
        'ready' => 'Prêt',
        'needs_attention' => 'Attention requise',
        'warning_optional' => 'Avertissement · facultatif',
    ],
    'requirements' => [
        'php' => [
            'name' => 'PHP 8.3 ou plus récent',
            'fix' => 'Mettez à jour PHP vers 8.3 ou plus récent pour le serveur web et le queue worker.',
        ],
        'php_extension' => [
            'name' => 'Extension PHP : :extension',
            'fix' => 'Activez l’extension :extension dans le PHP utilisé par le panel et le queue worker, puis redémarrez ces services.',
        ],
        'pelican' => [
            'name' => 'Application Pelican et dépendances Composer',
            'fix' => 'Terminez l’installation de Pelican et installez ses dépendances Composer.',
        ],
        'composer' => [
            'name' => 'Composer : :class',
            'fix' => 'Installez les dépendances Composer correspondant à votre version de Pelican.',
        ],
        'tabler' => [
            'name' => 'Icônes Tabler',
            'fix' => 'Rétablissez la dépendance Composer blade-tabler-icons et videz le cache des vues Blade.',
        ],
        'node' => [
            'name' => 'Node.js 22.12+ / 24+',
            'fix' => 'Installez Node.js 22.12 ou plus récent (ou Node.js 24+). Rendez node disponible dans le PATH du serveur web et du queue worker.',
        ],
        'yarn' => [
            'name' => 'Yarn',
            'fix' => 'Installez Yarn ou activez-le via Corepack. Rendez yarn disponible dans le PATH du serveur web et du queue worker.',
        ],
        'writable' => [
            'name' => ':directory accessible en écriture',
            'fix' => 'Donnez au panel et au queue worker l’accès en lecture et écriture à :directory.',
        ],
        'plugin_asset' => [
            'name' => 'Fichier du plugin : :file',
            'fix' => 'Réimportez le paquet PelicanSimplify complet ; ce fichier CSS/JavaScript est manquant, vide ou illisible.',
        ],
        'legacy_creator' => [
            'name' => 'Ancien créateur autonome retiré',
            'fix' => 'Désactivez et supprimez l’ancien créateur autonome (simple-server-creator) sous Admin → Plugins avant d’activer le nouveau créateur.',
        ],
        'built_assets' => [
            'name' => 'CSS et JavaScript compilés de Pelican',
            'fix' => 'Recompilez les fichiers du panel avec yarn install et yarn build dans votre dossier Pelican. :errors',
        ],
        'tested_release' => [
            'name' => 'Version de Pelican testée',
            'fix' => 'Simplify est testé sur Pelican :tested ; ce panel indique :release. L’installation continue, mais signalez les problèmes sur GitHub.',
            'no_version' => 'aucune version',
        ],
        'database' => [
            'name' => 'Connexion à la base de données',
            'fix' => 'Réparez la connexion à la base de données du panel avant de continuer.',
        ],
        'cache' => [
            'name' => 'Lecture/écriture du cache',
            'fix' => 'Réparez le cache configuré et ses permissions.',
        ],
        'storage' => [
            'name' => 'Stockage des paramètres du plugin',
            'fix' => 'Réparez le disque de stockage local de Laravel et donnez au panel l’accès en écriture.',
        ],
        'queue' => [
            'name' => 'File d’attente en arrière-plan configurée',
            'fix' => 'Configurez une connexion de file d’attente persistante pour Pelican et démarrez son queue worker.',
        ],
    ],
    'assets' => [
        'manifest' => 'Le manifeste de build est manquant ou invalide.',
        'missing_entry' => 'Entrée manquante : :source.',
        'invalid_entry' => 'Entrée de build invalide.',
        'invalid_css' => 'Liste CSS invalide.',
        'invalid_path' => 'Chemin de fichier de build invalide.',
        'missing_asset' => 'Fichier manquant ou vide : :file.',
        'invalid_imports' => 'Imports de build invalides.',
        'missing_import' => 'Entrée de build importée manquante.',
        'no_css' => 'Aucun CSS compilé trouvé.',
        'no_js' => 'Aucun JavaScript compilé trouvé.',
    ],
    'uploads' => [
        'runtime_cli' => 'PHP CLI (vérifiez PHP-FPM séparément)',
        'runtime_web' => 'PHP web',
        'capacity' => 'Capacité d’envoi · :size pour les bannières',
        'capacity_detail' => ':runtime : upload_max_filesize=:upload ; post_max_size=:post. Les envois de bannières sont plafonnés à la limite PHP/Livewire la plus basse (5 Mo maximum).',
        'capacity_fix' => 'Pour autoriser des bannières de 5 Mo, réglez dans PHP-FPM upload_max_filesize = 10M et post_max_size = 12M, vérifiez une éventuelle règle max de Livewire, puis rechargez PHP-FPM.',
        'proxy' => 'Limite d’envoi NGINX / proxy · non vérifiée',
        'proxy_detail' => 'Un proxy peut refuser des envois avant que PHP ne les reçoive (HTTP 413), même si les vérifications ci-dessus réussissent.',
        'proxy_fix' => 'Dans le bloc server NGINX de ce panel, réglez client_max_body_size 12m ; vérifiez les réglages location plus bas et les limites des proxys en amont. Lancez sudo nginx -t, puis sudo systemctl reload nginx. Vérifiez avec un vrai envoi. Aucune configuration serveur n’est modifiée automatiquement.',
    ],
];

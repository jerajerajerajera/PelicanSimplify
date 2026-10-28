<?php
return [
    'health' => [
        'ok' => ': OK',
    ],
    'system' => [
        'let_s_get_simplify_ready' => 'Ας ετοιμάσουμε το Simplify',
        'a_quick_check_of_your_panel' => 'Ένας γρήγορος έλεγχος του πάνελ σας πριν επιλέξετε τις ρυθμίσεις σας.',
        'checking_your_panel' => 'Έλεγχος του πάνελ σας…',
        'view_checks' => 'Προβολή ελέγχων',
        'keep_your_queue_worker_and_scheduler' => 'Διατηρήστε σε λειτουργία τον queue worker και τον χρονοπρογραμματιστή σας. Η τρέχουσα κατάστασή τους δεν μπορεί να επαληθευτεί εδώ.',
        'check_again' => 'Νέος έλεγχος',
        'go_to_first_setup' => 'Μετάβαση στην πρώτη ρύθμιση',
        'preparing_your_system_checks' => 'Προετοιμασία των ελέγχων συστήματος…',
        'close_system_checks' => 'Κλείσιμο ελέγχων συστήματος',
        'first_setup_eyebrow' => 'Πρώτη ρύθμιση · Έλεγχος συστήματος',
        'diagnostics' => 'Διαγνωστικά συστήματος',
        'panel_ready' => 'Το πάνελ σας είναι έτοιμο',
        'needs_attention_some' => 'Μερικά πράγματα χρειάζονται προσοχή',
        'passed' => 'Πέρασαν :passed από :total ελέγχους',
        'ready' => 'Έτοιμο',
        'needs_attention' => 'Χρειάζεται προσοχή',
        'warning_optional' => 'Προειδοποίηση · προαιρετικό',
    ],
    'requirements' => [
        'php' => [
            'name' => 'PHP 8.3 ή νεότερη',
            'fix' => 'Αναβαθμίστε την PHP τόσο για τον web server όσο και για τον queue worker σε PHP 8.3 ή νεότερη.',
        ],
        'php_extension' => [
            'name' => 'Επέκταση PHP: :extension',
            'fix' => 'Ενεργοποιήστε την επέκταση :extension στο περιβάλλον PHP που χρησιμοποιούν το πάνελ και ο queue worker και, στη συνέχεια, επανεκκινήστε αυτές τις υπηρεσίες.',
        ],
        'pelican' => [
            'name' => 'Εφαρμογή Pelican και εξαρτήσεις Composer',
            'fix' => 'Ολοκληρώστε την εγκατάσταση του Pelican και εγκαταστήστε τις εξαρτήσεις Composer του.',
        ],
        'composer' => [
            'name' => 'Composer: :class',
            'fix' => 'Εγκαταστήστε τις εξαρτήσεις Composer που αντιστοιχούν στην έκδοση Pelican σας.',
        ],
        'tabler' => [
            'name' => 'Στοιχεία εικονιδίων Tabler',
            'fix' => 'Επαναφέρετε την εξάρτηση Composer blade-tabler-icons και καθαρίστε την cache προβολών Blade.',
        ],
        'node' => [
            'name' => 'Node.js 22.12+ / 24+',
            'fix' => 'Εγκαταστήστε Node.js 22.12 ή νεότερο (ή Node.js 24+). Κάντε το node διαθέσιμο στο PATH του web server και του queue worker.',
        ],
        'yarn' => [
            'name' => 'Yarn',
            'fix' => 'Εγκαταστήστε το Yarn ή ενεργοποιήστε το μέσω Corepack. Κάντε το yarn διαθέσιμο στο PATH του web server και του queue worker.',
        ],
        'writable' => [
            'name' => 'Εγγράψιμος φάκελος :directory',
            'fix' => 'Δώστε στο πάνελ και στον queue worker δικαίωμα ανάγνωσης/εγγραφής στο :directory.',
        ],
        'plugin_asset' => [
            'name' => 'Αρχείο πρόσθετου: :file',
            'fix' => 'Εισαγάγετε ξανά ολόκληρο το πακέτο PelicanSimplify· αυτό το αρχείο CSS/JavaScript λείπει, είναι κενό ή δεν διαβάζεται.',
        ],
        'legacy_creator' => [
            'name' => 'Το αυτόνομο εργαλείο δημιουργίας καταργήθηκε',
            'fix' => 'Απενεργοποιήστε και διαγράψτε το παλιό αυτόνομο εργαλείο δημιουργίας (simple-server-creator) στο Admin → Plugins πριν ενεργοποιήσετε το ενιαίο εργαλείο δημιουργίας.',
        ],
        'built_assets' => [
            'name' => 'Μεταγλωττισμένα CSS και JavaScript του Pelican',
            'fix' => 'Δημιουργήστε ξανά τα αρχεία του πάνελ με yarn install και yarn build στον φάκελο του Pelican. :errors',
        ],
        'tested_release' => [
            'name' => 'Δοκιμασμένη έκδοση Pelican',
            'fix' => 'Το Simplify έχει δοκιμαστεί στο Pelican :tested· αυτό το πάνελ αναφέρει :release. Η εγκατάσταση συνεχίζεται, αλλά αναφέρετε προβλήματα στο GitHub.',
            'no_version' => 'χωρίς έκδοση',
        ],
        'database' => [
            'name' => 'Σύνδεση βάσης δεδομένων',
            'fix' => 'Επιδιορθώστε τη σύνδεση της βάσης δεδομένων του πάνελ πριν συνεχίσετε.',
        ],
        'cache' => [
            'name' => 'Ανάγνωση/εγγραφή cache',
            'fix' => 'Επιδιορθώστε το ρυθμισμένο backend cache και τα δικαιώματά του.',
        ],
        'storage' => [
            'name' => 'Αποθήκευση ρυθμίσεων πρόσθετου',
            'fix' => 'Επιδιορθώστε τον τοπικό δίσκο αποθήκευσης Laravel και δώστε στο πάνελ δικαίωμα εγγραφής.',
        ],
        'queue' => [
            'name' => 'Η ουρά παρασκηνίου έχει ρυθμιστεί',
            'fix' => 'Ρυθμίστε μια μόνιμη σύνδεση ουράς Pelican και ξεκινήστε τον queue worker της.',
        ],
    ],
    'assets' => [
        'manifest' => 'Το manifest της μεταγλώττισης λείπει ή δεν είναι έγκυρο.',
        'missing_entry' => 'Λείπει η καταχώριση: :source.',
        'invalid_entry' => 'Μη έγκυρη καταχώριση μεταγλώττισης.',
        'invalid_css' => 'Μη έγκυρη λίστα CSS.',
        'invalid_path' => 'Μη έγκυρη διαδρομή αρχείου μεταγλώττισης.',
        'missing_asset' => 'Λείπει ή είναι κενό το αρχείο: :file.',
        'invalid_imports' => 'Μη έγκυρες εισαγωγές μεταγλώττισης.',
        'missing_import' => 'Λείπει εισαγόμενη καταχώριση μεταγλώττισης.',
        'no_css' => 'Δεν βρέθηκε μεταγλωττισμένο CSS.',
        'no_js' => 'Δεν βρέθηκε μεταγλωττισμένη JavaScript.',
    ],
    'uploads' => [
        'runtime_cli' => 'PHP γραμμής εντολών (ελέγξτε το PHP-FPM ξεχωριστά)',
        'runtime_web' => 'PHP του web server',
        'capacity' => 'Χωρητικότητα μεταφόρτωσης · :size για banners',
        'capacity_detail' => ':runtime: upload_max_filesize=:upload· post_max_size=:post. Οι μεταφορτώσεις banner περιορίζονται στο χαμηλότερο όριο PHP/Livewire (μέγιστο 5 MB).',
        'capacity_fix' => 'Για να επιτρέψετε banners 5 MB, ορίστε στο PHP-FPM upload_max_filesize = 10M και post_max_size = 12M, ελέγξτε τυχόν κανόνα max του Livewire και, στη συνέχεια, φορτώστε ξανά το PHP-FPM.',
        'proxy' => 'Όριο μεταφόρτωσης NGINX / proxy · δεν επαληθεύτηκε',
        'proxy_detail' => 'Ένας proxy μπορεί να απορρίψει μεταφορτώσεις πριν τις λάβει η PHP (HTTP 413), ακόμη κι αν περνούν οι παραπάνω έλεγχοι.',
        'proxy_fix' => 'Στο server block του NGINX που εξυπηρετεί αυτό το πάνελ, ορίστε client_max_body_size 12m· ελέγξτε για χαμηλότερες παρακάμψεις location και όρια upstream proxy. Εκτελέστε sudo nginx -t και μετά sudo systemctl reload nginx. Επαληθεύστε με μια πραγματική μεταφόρτωση. Καμία ρύθμιση διακομιστή δεν αλλάζει αυτόματα.',
    ],
];

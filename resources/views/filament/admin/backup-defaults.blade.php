@php
    $every = (int) \Simplify\Panel\Services\PerformanceSettings::get('backup_interval_minutes');
    $interval = $every % 60 === 0 ? trans_choice('pelican-simplify::settings.backups.every_hours', $every / 60) : trans_choice('pelican-simplify::settings.backups.every_minutes', $every);
    $points = \Simplify\Panel\Services\PerformanceSettings::get('backup_retention');
    $unused = \Simplify\Panel\Services\PerformanceSettings::get('backup_only_when_used') ? ', '.__('pelican-simplify::settings.backups.skipped_unused') : '';
@endphp
<p class="ssc-backup-defaults">{{ \Simplify\Panel\Services\FeatureSettings::enabled('automatic_backups') ? __('pelican-simplify::settings.backups.uses_automatic', ['interval' => $interval, 'points' => $points, 'unused' => $unused]) : __('pelican-simplify::settings.backups.paused') }}</p>

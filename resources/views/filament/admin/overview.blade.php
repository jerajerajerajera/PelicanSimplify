@php
    $settings = [];
    foreach (['server_type', 'server_software', 'minecraft_version', 'modpack_project', 'modpack_version', 'java_version', 'image'] as $key) {
        $settings[$key] = $get($key);
    }
    $egg = \App\Models\Egg::find($get('egg_id'));
    $runtime = \Simplify\Panel\Services\JavaRuntime::class;
    $other = $get('game') === 'other';
    $java = $other ? null : $runtime::major($settings);
    $image = $other && $egg ? \Simplify\Panel\Services\OtherGameCatalog::image($egg) : $runtime::image($settings, $egg);
    $ram = $get('ram_gb') === 'custom' ? $get('ram_custom_gb') : $get('ram_gb');
    $catalog = \Simplify\Panel\Services\MinecraftCatalog::class;
    $kind = (string) $get('server_type');
    $software = $catalog::software($kind, $get('server_software'));
    $type = (\Simplify\Panel\Filament\Admin\Pages\CreateMinecraftServer::minecraftChoices()[$kind][0] ?? '—') . ($software ? ' · ' . $catalog::SOFTWARE[$kind][$software] : '');
    $java = $kind === 'bedrock' ? null : $java;
    $version = $runtime::minecraftVersion($settings);
    $javaServer = !$other && $catalog::javaServer($kind);
    $t = fn (string $key, array $replace = []) => __('pelican-simplify::maker.review.'.$key, $replace);
    $backupEvery = $get('change_backup_schedule') ? $get('backup_interval_minutes') : \Simplify\Panel\Services\PerformanceSettings::get('backup_interval_minutes');
    $backupPoints = $get('change_backup_schedule') ? $get('backup_retention') : \Simplify\Panel\Services\PerformanceSettings::get('backup_retention');
    $rows = [
        'name' => $get('name') ?: '—',
        'color' => $get('server_color') ?: $t('automatic'),
        'first_start' => $javaServer ? $t('first_start_eula') : $t('first_start_stopped'),
        'type' => $type,
        'minecraft' => $version ?: ($kind === 'modded' ? $t('select_modpack_version') : $t('latest')),
        'connection' => \App\Models\Allocation::find($get('allocation_id'))?->address ?? $t('automatic'),
        'ram' => $ram ? $ram . ' GB' : '—',
        'cpu' => (int) $get('cpu') === 0 ? $t('unlimited') : $get('cpu') . '%',
        'disk' => round((int) $get('disk_mb') / 1024, 1) . ' GB',
        'java' => trim((string) $get('image')) !== '' ? $t('custom_image') : ($java ? 'Java ' . $java . ' · ' . ((($get('java_version') ?: 'auto') === 'auto') ? $t('automatic') : $t('manual')) : $t('not_determined')),
        'egg' => $egg?->name ?? '—',
        'backups' => \Simplify\Panel\Services\FeatureSettings::enabled('automatic_backups') ? $t('backups_value', ['minutes' => $backupEvery, 'points' => $backupPoints]) : $t('paused'),
        'properties' => $get('properties_enabled') ? $t('properties_value', ['players' => $get('max_players'), 'gamemode' => __('pelican-simplify::maker.review.gamemodes.'.$get('gamemode')), 'difficulty' => __('pelican-simplify::maker.review.difficulties.'.$get('difficulty'))]) : $t('installer_defaults'),
        'motd' => $get('properties_enabled') ? $get('motd') : $t('installer_default'),
        'icon' => $get('server_icon') ? $t('ready_to_upload') : $t('installer_default'),
        'start' => $get('start_on_completion') ? $t('yes') : $t('no'),
    ];
    if (\Simplify\Panel\Services\Subdomains::active()) {
        $rows['subdomain'] = ($sub = strtolower(trim((string) $get('requested_subdomain')))) !== '' ? $sub . '.' . \Simplify\Panel\Services\Subdomains::settings()['domain'] : $t('none');
    }
    if ($other) {
        $rows['game'] = $egg?->name ?? '—';
        foreach (['minecraft', 'java', 'type', 'properties', 'motd', 'icon'] as $key) { unset($rows[$key]); }
    }
    if (!$other && !$javaServer) {
        foreach (['properties', 'motd', 'icon', ...($kind === 'bedrock' ? ['java'] : [])] as $key) { unset($rows[$key]); }
    }
    if (!$other && $get('server_type') === 'modded') {
        $rows['modpack'] = \Simplify\Panel\Services\ModpackCatalog::pack($get('modpack_project'))['title'] ?? '—';
        $rows['modpack_version'] = $get('modpack_version') ?: '—';
    }
@endphp
<section class="ssc-overview">
    <div class="ssc-overview-heading">
        <h3>{{ __('pelican-simplify::maker.review.server_overview') }}</h3>
        <p>{{ __('pelican-simplify::maker.review.everything_is_prepared_change_settings_below') }}</p>
    </div>
    <dl class="ssc-summary-grid">
        @foreach ($rows as $label => $value)
            <div class="ssc-summary-item"><dt>{{ __('pelican-simplify::maker.review.rows.'.$label) }}</dt><dd>{{ $value }}</dd></div>
        @endforeach
        <div class="ssc-summary-item ssc-summary-wide"><dt>{{ __('pelican-simplify::maker.review.docker_image') }}</dt><dd><code>{{ $image ?: $t('choose_java') }}</code></dd></div>
    </dl>
    @if (!$other && $kind !== 'bedrock' && !$java && !$get('image'))
        <p class="ssc-summary-notice">{{ __('pelican-simplify::maker.review.java_could_not_be_determined_choose') }}</p>
    @endif
    <div class="ssc-overview-footer">
        <x-filament::button type="button" color="gray" outlined wire:click="toggleAdvanced">
            {{ $this->editingAdvanced ? $t('done_editing') : $t('edit_settings') }}
        </x-filament::button>
    </div>
</section>

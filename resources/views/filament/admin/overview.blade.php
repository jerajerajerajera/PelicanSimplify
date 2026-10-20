@php
    $settings = [];
    foreach (['server_type', 'minecraft_version', 'modpack_project', 'modpack_version', 'java_version', 'image'] as $key) {
        $settings[$key] = $get($key);
    }
    $egg = \App\Models\Egg::find($get('egg_id'));
    $runtime = \Simplify\Panel\Services\JavaRuntime::class;
    $other = $get('game') === 'other';
    $java = $other ? null : $runtime::major($settings);
    $image = $other && $egg ? \Simplify\Panel\Services\OtherGameCatalog::image($egg) : $runtime::image($settings, $egg);
    $ram = $get('ram_gb') === 'custom' ? $get('ram_custom_gb') : $get('ram_gb');
    $type = ['vanilla' => 'Vanilla', 'plus' => 'Vanilla+ / Paper', 'modded' => 'Modded'][$get('server_type')] ?? '—';
    $version = $runtime::minecraftVersion($settings);
    $rows = [
        'Server name' => $get('name') ?: '—',
        'Server color'=>$get('server_color') ?: 'Automatic',
        'First start'=>'Manual · server stays stopped',
        'Server type' => $type,
        'Minecraft' => $version ?: 'Select a modpack version',
        'Connection' => \App\Models\Allocation::find($get('allocation_id'))?->address ?? 'Automatic',
        'RAM' => $ram ? $ram . ' GB' : '—',
        'CPU limit' => (int) $get('cpu') === 0 ? 'Unlimited' : $get('cpu') . '%',
        'Disk space' => round((int) $get('disk_mb') / 1024, 1) . ' GB',
        'Java runtime' => trim((string) $get('image')) !== '' ? 'Custom image' : ($java ? 'Java ' . $java . (($get('java_version') ?: 'auto') === 'auto' ? ' · Automatic' : ' · Manual') : 'Not determined'),
        'Egg' => $egg?->name ?? '—',
        'Automatic backups' => \Simplify\Panel\Services\FeatureSettings::enabled('automatic_backups') ? (($get('change_backup_schedule')?$get('backup_interval_minutes'):\Simplify\Panel\Services\PerformanceSettings::get('backup_interval_minutes')).' minutes · '.($get('change_backup_schedule')?$get('backup_retention'):\Simplify\Panel\Services\PerformanceSettings::get('backup_retention')).' recovery points') : 'Paused',
        'Server properties' => $get('properties_enabled') ? $get('max_players') . ' players · ' . ucfirst($get('gamemode')) . ' · ' . ucfirst($get('difficulty')) : 'Installer defaults',
        'MOTD' => $get('properties_enabled') ? $get('motd') : 'Installer default',
        'Server icon' => $get('server_icon') ? 'Ready to upload' : 'Installer default',
        'Start after installation' => $get('start_on_completion') ? 'Yes' : 'No',
    ];
    if (\Simplify\Panel\Services\Subdomains::active()) {
        $rows['Subdomain'] = ($sub = strtolower(trim((string) $get('requested_subdomain')))) !== '' ? $sub . '.' . \Simplify\Panel\Services\Subdomains::settings()['domain'] : 'None';
    }
    if ($other) {
        $rows['Game'] = $egg?->name ?? '—';
        foreach (['Minecraft', 'Java runtime', 'Server type', 'Server properties', 'MOTD', 'Server icon'] as $key) { unset($rows[$key]); }
    }
    if (!$other && $get('server_type') === 'modded') {
        $rows['Modpack'] = \Simplify\Panel\Services\ModpackCatalog::pack($get('modpack_project'))['title'] ?? '—';
        $rows['Modpack version ID'] = $get('modpack_version') ?: '—';
    }
@endphp
<section class="ssc-overview">
    <div class="ssc-overview-heading">
        <h3>Server overview</h3>
        <p>Everything is prepared. Change settings below if needed.</p>
    </div>
    <dl class="ssc-summary-grid">
        @foreach ($rows as $label => $value)
            <div class="ssc-summary-item"><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>
        @endforeach
        <div class="ssc-summary-item ssc-summary-wide"><dt>Docker image</dt><dd><code>{{ $image ?: 'Choose Java in Edit settings' }}</code></dd></div>
    </dl>
    @if (!$other && !$java && !$get('image'))
        <p class="ssc-summary-notice">Java could not be determined. Choose a modpack version, or set Java manually below.</p>
    @endif
    <div class="ssc-overview-footer">
        <x-filament::button type="button" color="gray" outlined wire:click="toggleAdvanced">
            {{ $this->editingAdvanced ? 'Done editing' : 'Edit settings' }}
        </x-filament::button>
    </div>
</section>

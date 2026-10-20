<?php

declare(strict_types=1);

namespace Simplify\Panel\Jobs;

use App\Models\Server;
use App\Repositories\Daemon\DaemonFileRepository;
use App\Repositories\Daemon\DaemonServerRepository;
use Simplify\Panel\Services\ModpackIcon;
use Simplify\Panel\Services\ServerProperties;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class ApplyServerProperties implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 480;
    public int $timeout = 60;
    public int $backoff = 30;

    public function __construct(public string $uuid, public array $settings, public bool $start) {}

    public function handle(DaemonFileRepository $files, DaemonServerRepository $daemon): void
    {
        $server = Server::where('uuid', $this->uuid)->first();
        if (!$server) {
            return;
        }
        // Only a successful installation has installed_at set and a null status.
        // Failed/suspended/installing servers stay stopped and can be retried later.
        if (!$server->installed_at || $server->status !== null) {
            $this->release(30);
            return;
        }
        $disk = Storage::disk('local');
        $marker = 'ssc/server-settings/' . $this->uuid . '.json';
        $saved = $disk->exists($marker) ? json_decode($disk->get($marker), true) : [];
        if (($saved['status'] ?? '') === 'complete') {
            return;
        }
        if (($saved['status'] ?? '') !== 'applied') {
            $files->setServer($server);
            if (!empty($this->settings['properties'])) {
                try {
                    $original = $files->getContent('/server.properties', 1048576);
                } catch (FileNotFoundException) {
                    $original = '';
                }
                $files->putContent('/server.properties', ServerProperties::merge($original, $this->settings['properties']))->throw();
            }
            if (!empty($this->settings['icon'])) {
                $files->putContent('/server-icon.png', base64_decode($this->settings['icon'], true))->throw();
            } elseif (!empty($this->settings['icon_url']) && !$this->hasFile($files, 'server-icon.png') && ($icon = ModpackIcon::png($this->settings['icon_url'])) !== null) {
                // No uploaded icon: use the modpack's icon, unless the pack ships its own server-icon.png.
                $files->putContent('/server-icon.png', $icon)->throw();
            }
            if (($this->settings['eula']??false)===true) {
                $files->putContent('/eula.txt', "# Accepted by the server creator during setup.\neula=true\n")->throw();
            }
            if (!$disk->put($marker, json_encode(['status' => 'applied', 'applied_at' => now()->toIso8601String()], JSON_THROW_ON_ERROR))) {
                throw new \RuntimeException('Could not persist SSC settings status.');
            }
        }
        if ($this->start) {
            $daemon->setServer($server);
            $daemon->power('start')->throw();
        }
        if (!$disk->put($marker, json_encode(['status' => 'complete', 'completed_at' => now()->toIso8601String()], JSON_THROW_ON_ERROR))) {
            throw new \RuntimeException('Could not persist SSC completion status.');
        }
    }

    private function hasFile(DaemonFileRepository $files, string $name): bool
    {
        foreach ($files->getDirectory('/') as $file) {
            if (($file['name'] ?? '') === $name) return true;
        }
        return false;
    }

    public function failed(?Throwable $exception): void
    {
        report($exception ?? new \RuntimeException('SSC settings job failed for ' . $this->uuid));
        Storage::disk('local')->put('ssc/server-settings/' . $this->uuid . '.json', json_encode([
            'status' => 'failed', 'server_uuid' => $this->uuid,
            'message' => 'Check the queue logs, retry the failed job, then start the server.',
        ], JSON_THROW_ON_ERROR));
    }
}

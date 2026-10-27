<?php
declare(strict_types=1);
namespace App\Models {class Egg {}class Server {public ?string $external_id=null;}class User {}class Allocation {}class Node {}}
namespace App\Enums {enum EggFormat {case JSON;case YAML;}}
namespace App\Services\Eggs\Sharing {class EggExporterService {}class EggImporterService {}}
namespace App\Services\Servers {class ServerCreationService {}class ServerDeletionService {}}
namespace App\Repositories\Daemon {class DaemonServerRepository {}}
namespace {
require_once __DIR__.'/fixtures/lang.php';
require __DIR__.'/../src/Services/EggUpdates.php';
require __DIR__.'/../src/Services/EggUpdateTest.php';
use Simplify\Panel\Services\EggUpdates;
$previous = ['startup_commands' => ['Default' => 'java -jar old.jar', 'Aikar' => 'java -XX:+Aikar -jar old.jar'], 'docker_images' => ['Java 17' => 'yolks:java_17', 'Java 21' => 'yolks:java_21']];
$servers = [
    ['id' => 1, 'startup' => 'java -jar old.jar', 'image' => 'yolks:java_17'],
    ['id' => 2, 'startup' => 'java -XX:+Aikar -jar old.jar', 'image' => 'yolks:java_21'],
    ['id' => 3, 'startup' => 'my own command', 'image' => 'my/image'],
    ['id' => 4, 'startup' => 'java -jar new.jar', 'image' => 'yolks:java_21'],
];
$plan = EggUpdates::moves($servers, $previous, ['Default' => 'java -jar new.jar', 'Aikar' => 'java -XX:+Aikar -jar new.jar'], ['Java 21' => 'yolks:java_21', 'Java 25' => 'yolks:java_25']);
// Same name follows its new command; a removed image goes to the new default; own commands and current ones stay.
if ($plan !== [1 => ['startup' => 'java -jar new.jar', 'image' => 'yolks:java_21'], 2 => ['startup' => 'java -XX:+Aikar -jar new.jar']]) throw new RuntimeException('egg update moves the wrong servers: '.json_encode($plan));
// Older exports have a single "startup"; a name the new egg lacks falls back to its default.
$plan = EggUpdates::moves([['id' => 5, 'startup' => 'old', 'image' => 'x']], ['startup' => 'old'], ['Default' => 'new'], ['Java' => 'x']);
if ($plan !== [5 => ['startup' => 'new']]) throw new RuntimeException('single startup of an older export not moved');
if (EggUpdates::moves($servers, [], ['Default' => 'x'], ['Java' => 'y']) !== []) throw new RuntimeException('servers moved without an earlier version');
if (EggUpdates::names(['Paper']) !== 'Paper' || EggUpdates::names(['Paper', 'Fabric', 'Forge']) !== 'Paper, Fabric and Forge') throw new RuntimeException('egg names not listed in a sentence');
if (array_keys(EggUpdates::MODES) !== ['ask', 'auto', 'test', 'off'] || EggUpdates::defaults()['mode'] !== 'ask') throw new RuntimeException('egg update modes or default changed');
// Test servers are recognised by their external id only.
$test = new App\Models\Server; $test->external_id = Simplify\Panel\Services\EggUpdateTest::MARK.'4-1793137000'; $own = new App\Models\Server; $own->external_id = 'billing-42';
if (!Simplify\Panel\Services\EggUpdateTest::isTest($test) || Simplify\Panel\Services\EggUpdateTest::isTest($own) || Simplify\Panel\Services\EggUpdateTest::isTest(new App\Models\Server) || Simplify\Panel\Services\EggUpdateTest::isTest(null)) throw new RuntimeException('egg update test servers not recognised');
echo "PASS egg updates: servers move only off the previous egg's own commands and images, names, modes, default and test servers\n";
}

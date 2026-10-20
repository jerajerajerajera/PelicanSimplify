<?php
declare(strict_types=1);
$marker=$argv[2]??null;
$panel=$argv[1]??'/var/www/pelican';
require $panel.'/vendor/autoload.php';$app=require $panel.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
\Simplify\Panel\Services\DependencyCheck::assertReady(\Simplify\Panel\Services\DependencyCheck::inspect($panel, $panel.'/plugins/pelican-simplify'));
foreach(['Simplify\\Panel\\Filament\\App\\Pages\\Dashboard','Simplify\\Panel\\Filament\\Admin\\Pages\\SimplifySettings'] as $class){if(!class_exists($class))throw new RuntimeException('Missing class: '.$class);}
foreach(['filament.app.pages.simple-server-creator','filament.app.pages.pelican-simplify-settings','simplify.asset'] as $route)if(!\Illuminate\Support\Facades\Route::has($route))throw new RuntimeException('Missing route: '.$route);
if(!\Illuminate\Support\Facades\Route::has('filament.app.pages.simplify'))throw new RuntimeException('Dashboard route was not registered.');
$plugins=['pelican-simplify'];
foreach(['server-directory'] as $id)if(\Simplify\Panel\Services\Extensions::enabled($id))$plugins[]=$id;
foreach($plugins as $id){
    $manifest=json_decode(file_get_contents($panel.'/plugins/'.$id.'/plugin.json'),true,512,JSON_THROW_ON_ERROR);
    if(($manifest['meta']['status']??'')!=='enabled')throw new RuntimeException($id.' is not enabled.');
    $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($panel.'/plugins/'.$id.'/resources/views',FilesystemIterator::SKIP_DOTS));
    foreach($it as $file){if(!str_ends_with($file->getPathname(),'.blade.php'))continue;
        $compiled=app('blade.compiler')->compileString(file_get_contents($file->getPathname()));
        $tmp=tempnam(sys_get_temp_dir(),'simplify-blade-');\Illuminate\Support\Facades\File::put($tmp,$compiled);
        $lint=\Illuminate\Support\Facades\Process::run([PHP_BINARY,'-l',$tmp]);\Illuminate\Support\Facades\File::delete($tmp);
        if(!$lint->successful())throw new RuntimeException('Blade syntax failed: '.$file->getPathname().' '.$lint->output().$lint->errorOutput());
    }
}
if ($marker!==null && \Illuminate\Support\Facades\File::put($marker,"PELICAN_SIMPLIFY_VALIDATED\n")===false) throw new RuntimeException('Could not write validation marker.');
echo "Pelican bootstrap, dashboard route, plugin status and Blade checks passed.\n";
echo "Browser, Wings and backup-host checks still require a real server test.\n";

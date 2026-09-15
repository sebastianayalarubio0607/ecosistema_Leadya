<?php

// Solo consola: no exponer rutas del servidor mediante una URL publica.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
$manifestPath = __DIR__.'/integration-views-manifest.json';
if (! is_file($manifestPath)) {
    fwrite(STDERR, "Falta tools/integration-views-manifest.json. Sube el paquete completo.\n");
    exit(1);
}

$manifest = json_decode(file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
echo "Paquete: ".$manifest['version']."\nProyecto: ".realpath($root)."\n\n";
$errors = 0;
foreach ($manifest['files'] as $relative => $expected) {
    $path = $root.'/'.$relative;
    if (! is_file($path)) {
        echo "FALTA     $relative\n";
        $errors++;
        continue;
    }
    // Normaliza CRLF/LF para admitir FTP en modo texto.
    $actual = hash('sha256', str_replace("\r\n", "\n", file_get_contents($path)));
    $matches = hash_equals($expected, $actual);
    echo ($matches ? 'OK        ' : 'DIFERENTE ').$relative."\n";
    $errors += $matches ? 0 : 1;
}

if (! is_file($root.'/vendor/autoload.php')) {
    fwrite(STDERR, "No hay vendor/autoload.php; esta no es una instalacion completa de Laravel.\n");
    exit(1);
}
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "\nRutas efectivas de Laravel (consola):\n";
echo 'Cache de vistas: '.config('view.compiled')."\n";
echo 'Configuracion cacheada: '.($app->configurationIsCached() ? 'SI' : 'NO')."\n";
echo 'Cache escribible por este usuario: '.(is_writable(config('view.compiled')) ? 'SI' : 'NO')."\n";
$compiler = $app->make('blade.compiler');
foreach (['integrations.create', 'integrations.edit', 'integrations._form', 'livewire.integrations.form'] as $name) {
    try {
        $source = $app->make('view')->getFinder()->find($name);
        $expectedPath = $root.'/resources/views/'.str_replace('.', '/', $name).'.blade.php';
        $expectedReal = realpath($expectedPath);
        $actualReal = realpath($source);
        $samePath = PHP_OS_FAMILY === 'Windows'
            ? strcasecmp((string) $expectedReal, (string) $actualReal) === 0
            : $expectedReal === $actualReal;
        echo "$name => $source\n";
        if (! $samePath) {
            echo "  ALERTA: Laravel resuelve otra copia de esta vista.\n";
            $errors++;
        }
        $compiled = $compiler->getCompiledPath($source);
        if (is_file($compiled)) {
            $contents = file_get_contents($compiled);
            echo '  Compilada: '.$compiled."\n";
            echo '  Fuente UTC: '.gmdate('Y-m-d H:i:s', filemtime($source))."\n";
            echo '  Cache UTC:  '.gmdate('Y-m-d H:i:s', filemtime($compiled))."\n";
            echo '  Laravel recompilara por fecha: '.($compiler->isExpired($source) ? 'SI' : 'NO')."\n";
            if (str_contains($contents, 'Guarda aqui el enlace directo')
                || str_contains($contents, '>Status *</label>')) {
                echo "  ALERTA: esta cache contiene el formulario antiguo.\n";
            }
            if (($name === 'integrations.create' || $name === 'integrations.edit')
                && str_contains($contents, 'integrations._form')) {
                echo "  ALERTA: esta cache todavia incluye la entrada antigua.\n";
            }
        } else {
            echo "  Sin cache Blade para esta vista.\n";
        }
    } catch (Throwable $exception) {
        echo "  ERROR al resolver $name: ".$exception->getMessage()."\n";
        $errors++;
    }
}

echo "\n".($errors ? "Hay $errors diferencias/errores. Revisa la subida antes de limpiar cache."
    : 'Los archivos coinciden con el paquete y Laravel CLI resuelve estas copias.')."\n";
echo "Esta comprobacion no verifica el document root de Nginx ni la memoria OPcache de PHP-FPM.\n";
echo "No se han borrado caches ni modificado archivos de la aplicacion.\n";
exit($errors ? 1 : 0);

<?php

// Autoloader para las clases de SiNube en el namespace App\
spl_autoload_register(function ($class) {
    $prefixApp = 'App\\';
    $baseDir = __DIR__ . '/';

    $len = strlen($prefixApp);
    if (strncmp($prefixApp, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);

    // Mapeo de subdirectorios
    // App\DTOs\SiNube\... => classes/SiNube/DTOs/...
    // App\Services\SiNube\... => classes/SiNube/...
    $relativeClass = str_replace('DTOs\\SiNube\\', 'DTOs/', $relativeClass);
    $relativeClass = str_replace('Services\\SiNube\\', '', $relativeClass);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

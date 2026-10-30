<?php
declare(strict_types=1);
spl_autoload_register(static function (string $name): void {
    $prefix = 'Mapa\\GeoFeed\\';
    if (!str_starts_with($name, $prefix)) { return; }
    $relative = substr($name, strlen($prefix));
    $aliases = [
        'TemplateFieldsReader' => 'TemplateFields.php',
        'Contracts\\Lock' => 'Contracts/BuildEnvironment.php',
        'Contracts\\ChangeEpoch' => 'Contracts/BuildEnvironment.php',
        'Contracts\\Publisher' => 'Contracts/BuildEnvironment.php',
    ];
    $path = __DIR__ . '/src/' . ($aliases[$relative] ?? str_replace('\\', '/', $relative) . '.php');
    if (is_file($path)) { require_once $path; }
});

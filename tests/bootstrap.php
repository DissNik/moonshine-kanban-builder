<?php

declare(strict_types=1);

$autoloadCandidates = [
    __DIR__ . '/../vendor/autoload.php',
    dirname(__DIR__, 4) . '/franchise/vendor/autoload.php',
];

$autoload = null;

foreach ($autoloadCandidates as $candidate) {
    if (is_file($candidate)) {
        $autoload = $candidate;
        break;
    }
}

if ($autoload === null) {
    fwrite(STDERR, "Unable to locate Composer autoload.php for moonshine-kanban-builder tests.\n");
    exit(1);
}

require $autoload;

spl_autoload_register(static function (string $class): void {
    $prefixes = [
        'DissNik\\MoonShineKanBanBuilder\\' => dirname(__DIR__) . '/src/',
        'DissNik\\MoonShineKanBanBuilder\\Tests\\' => __DIR__ . '/',
    ];

    foreach ($prefixes as $prefix => $baseDir) {
        if (! str_starts_with($class, $prefix)) {
            continue;
        }

        $relative = substr($class, strlen($prefix));
        $path = $baseDir . str_replace('\\', '/', $relative) . '.php';

        if (is_file($path)) {
            require $path;
        }
    }
});

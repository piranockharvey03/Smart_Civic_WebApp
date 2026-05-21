<?php

declare(strict_types=1);

echo "cwd: " . getcwd() . PHP_EOL;
echo ".env exists? ";
var_export(file_exists(__DIR__ . '/../.env'));
echo PHP_EOL;

require_once __DIR__ . '/../vendor/autoload.php';

if (class_exists(\Dotenv\Dotenv::class)) {
    $d = \Dotenv\Dotenv::createImmutable(dirname(__DIR__));
    $d->safeLoad();
    echo "Loaded via phpdotenv\n";
} else {
    echo "Dotenv class not found\n";
}

echo "DB_NAME getenv: ";
var_export(getenv('DB_NAME'));
echo PHP_EOL;

echo "
";

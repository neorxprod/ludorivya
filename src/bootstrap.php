<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/Database.php';

$root = dirname(__DIR__);
$configPath = $root . '/config/database.php';

if (!is_file($configPath)) {
    $configPath = $root . '/config/database.example.php';
}

$config = require $configPath;
$pdo = null;
$databaseError = null;

try {
    $pdo = Database::connect($config['database']);
} catch (Throwable $exception) {
    $databaseError = $exception;
}


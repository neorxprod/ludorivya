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
    // Le site continue d'afficher une page d'aide meme si MySQL est eteint.
    $pdo = Database::connect($config['database']);
} catch (Throwable $exception) {
    $databaseError = $exception;
}

// Si un utilisateur est connecte, on garde ses infos a jour pour l'affichage.
if ($pdo !== null && isset($_SESSION['user_id'])) {
    $sessionStatement = $pdo->prepare('SELECT id, username, email FROM users WHERE id = :id');
    $sessionStatement->execute(['id' => (int)$_SESSION['user_id']]);
    $sessionUser = $sessionStatement->fetch();

    if ($sessionUser) {
        $_SESSION['user_username'] = $sessionUser['username'];
        $_SESSION['user_email'] = $sessionUser['email'];
    } else {
        session_regenerate_id(true);
        $_SESSION = [];
    }
}

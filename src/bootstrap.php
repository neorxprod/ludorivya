<?php

declare(strict_types=1);

// Securite : on logue les erreurs mais on ne les AFFICHE jamais au visiteur
// (une stack trace exposerait le SQL, les tables et les chemins serveur).
// XAMPP a display_errors=On par defaut : on le force a Off ici.
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Toute exception non rattrapee (ex: panne MySQL en cours de page) affiche
// une page d'erreur generique au lieu de la trace technique.
set_exception_handler(static function (Throwable $e): void {
    error_log('[Ludorivya] Exception non geree : ' . $e->getMessage());
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo '<!doctype html><html lang="fr"><meta charset="utf-8">'
        . '<title>Erreur - Ludorivya</title>'
        . '<body style="font-family:system-ui;background:#060a16;color:#f4f3fb;'
        . 'display:grid;place-items:center;height:100vh;margin:0;text-align:center">'
        . '<div><h1>Oups, une erreur est survenue</h1>'
        . '<p>Réessaie dans un instant. Si le problème persiste, vérifie que MySQL est démarré.</p>'
        . '<p><a href="index.php" style="color:#60a5fa">Retour à l\'accueil</a></p></div></body></html>';
});

// Durcissement du cookie de session AVANT session_start :
// - httponly : le cookie n'est pas lisible en JavaScript (anti vol de session)
// - samesite : le cookie n'est pas envoye depuis un autre site (limite le CSRF)
// - secure   : cookie uniquement en HTTPS quand le site est servi en HTTPS
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);

session_start();

// Jeton CSRF unique par session, verifie sur chaque formulaire POST.
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

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
        // Le compte n'existe plus : on repart sur une session vierge
        // (avec un nouveau jeton CSRF pour les formulaires de cette page).
        session_regenerate_id(true);
        $_SESSION = [];
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

// Deconnexion en POST + jeton CSRF : un simple lien (ou une image piegee
// sur un autre site) ne peut pas deconnecter l'utilisateur a son insu.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('index.php');
}

require_valid_csrf('index.php');

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool)$params['secure'], (bool)$params['httponly']);
}

session_destroy();
session_start();
session_regenerate_id(true);
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

flash('success', 'Tu es bien déconnecté. À bientôt !');
redirect_to('index.php');

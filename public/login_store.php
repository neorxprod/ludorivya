<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

if ($pdo === null) {
    flash('danger', 'Base de données non connectée.');
    redirect_to('login.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('login.php');
}

require_valid_csrf('login.php');

// Anti force brute : apres 5 echecs, on impose une pause de 60 secondes.
$failures = (int)($_SESSION['login_failures'] ?? 0);
$lockedUntil = (int)($_SESSION['login_locked_until'] ?? 0);

if ($lockedUntil > time()) {
    flash('warning', 'Trop de tentatives. Réessaie dans ' . max(1, $lockedUntil - time()) . ' secondes.');
    redirect_to('login.php');
}

$email = post_string('email', 160);
// Pas de trim sur le mot de passe : les espaces font partie du secret.
$password = post_raw_string('password', 255);
$redirect = post_string('redirect', 200);

// Securite anti open redirect : on n'accepte qu'une page interne du site
// (nom_de_page.php avec eventuellement des parametres simples).
if (preg_match('/^[a-z_]+\.php(\?[a-zA-Z0-9_=%&.\-]*)?$/', $redirect) !== 1) {
    $redirect = 'profile.php';
}

$statement = $pdo->prepare('SELECT id, username, email, password_hash FROM users WHERE email = :email');
$statement->execute(['email' => $email]);
$user = $statement->fetch();

// On ne dit pas si c'est l'email ou le mot de passe qui est faux.
if (!$user || !password_verify($password, $user['password_hash'])) {
    $failures++;
    $_SESSION['login_failures'] = $failures;
    if ($failures >= 5) {
        $_SESSION['login_locked_until'] = time() + 60;
        $_SESSION['login_failures'] = 0;
    }
    flash('danger', 'Email ou mot de passe incorrect.');
    redirect_to('login.php');
}

// Connexion reussie : nouvel identifiant de session (anti session fixation).
session_regenerate_id(true);
unset($_SESSION['login_failures'], $_SESSION['login_locked_until']);
$_SESSION['user_id'] = (int)$user['id'];
$_SESSION['user_username'] = $user['username'];
$_SESSION['user_email'] = $user['email'];

flash('success', 'Content de te revoir, ' . $user['username'] . ' !');
redirect_to($redirect);

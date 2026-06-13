<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

if ($pdo === null) {
    flash('danger', 'Base de donnees non connectee.');
    redirect_to('login.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('login.php');
}

$email = post_string('email', 160);
$password = post_string('password', 255);
$redirect = post_string('redirect', 200);

if ($redirect === '' || str_contains($redirect, '://')) {
    $redirect = 'profile.php';
}

$statement = $pdo->prepare('SELECT id, username, email, password_hash FROM users WHERE email = :email');
$statement->execute(['email' => $email]);
$user = $statement->fetch();

// On ne dit pas si c'est l'email ou le mot de passe qui est faux.
if (!$user || !password_verify($password, $user['password_hash'])) {
    flash('danger', 'Email ou mot de passe incorrect.');
    redirect_to('login.php');
}

session_regenerate_id(true);
$_SESSION['user_id'] = (int)$user['id'];
$_SESSION['user_username'] = $user['username'];
$_SESSION['user_email'] = $user['email'];

flash('success', 'Connexion reussie.');
redirect_to($redirect);


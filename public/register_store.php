<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

if ($pdo === null) {
    flash('danger', 'Base de données non connectée.');
    redirect_to('register.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('register.php');
}

require_valid_csrf('register.php');

$username = post_string('username', 60);
$email = post_string('email', 160);
// Pas de trim sur le mot de passe : les espaces font partie du secret.
$password = post_raw_string('password', 255);
$bio = post_string('bio', 500);
$favoritePlatformId = post_int('favorite_platform_id', 1, 1000000);

$errors = [];

if (preg_match('/^[A-Za-z0-9_\-]{3,60}$/', $username) !== 1) {
    $errors[] = 'Le pseudo doit faire 3 à 60 caractères (lettres, chiffres, tirets, underscores).';
}

if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    $errors[] = 'L’adresse email est invalide.';
}

if (mb_strlen($password) < 8) {
    $errors[] = 'Le mot de passe doit faire au moins 8 caractères.';
}

// La plateforme preferee doit exister (FK de la relation 1-N platforms -> profils).
if ($favoritePlatformId !== null) {
    $platformCheck = $pdo->prepare('SELECT COUNT(*) FROM platforms WHERE id = :id');
    $platformCheck->execute(['id' => $favoritePlatformId]);
    if ((int)$platformCheck->fetchColumn() === 0) {
        $favoritePlatformId = null;
    }
}

if ($errors === []) {
    // Unicite pseudo/email verifiee avant insertion pour un message clair.
    $duplicateCheck = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = :username OR email = :email');
    $duplicateCheck->execute(['username' => $username, 'email' => $email]);
    if ((int)$duplicateCheck->fetchColumn() > 0) {
        $errors[] = 'Ce pseudo ou cet email est déjà utilisé.';
    }
}

if ($errors !== []) {
    flash('danger', implode(' ', $errors));
    redirect_to('register.php');
}

try {
    // Transaction : le compte (users) et son profil (user_profiles, relation 1-1)
    // sont crees ensemble ou pas du tout.
    $pdo->beginTransaction();

    $insertUser = $pdo->prepare('
        INSERT INTO users (username, email, password_hash)
        VALUES (:username, :email, :password_hash)
    ');
    $insertUser->execute([
        'username' => $username,
        'email' => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    ]);

    $userId = (int)$pdo->lastInsertId();

    $insertProfile = $pdo->prepare('
        INSERT INTO user_profiles (user_id, bio, favorite_platform_id)
        VALUES (:user_id, :bio, :favorite_platform_id)
    ');
    $insertProfile->execute([
        'user_id' => $userId,
        'bio' => $bio === '' ? null : $bio,
        'favorite_platform_id' => $favoritePlatformId,
    ]);

    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[Ludorivya] Inscription impossible : ' . $exception->getMessage());
    flash('danger', 'L’inscription a échoué, merci de réessayer.');
    redirect_to('register.php');
}

// Connexion automatique apres inscription.
session_regenerate_id(true);
$_SESSION['user_id'] = $userId;
$_SESSION['user_username'] = $username;
$_SESSION['user_email'] = $email;

flash('success', 'Bienvenue sur Ludorivya, ' . $username . ' !');
redirect_to('profile.php');

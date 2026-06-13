<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

if ($pdo === null) {
    flash('danger', 'Base de donnees non connectee.');
    redirect_to('register.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('register.php');
}

$username = post_string('username', 60);
$email = post_string('email', 160);
$password = post_string('password', 255);
$bio = post_string('bio', 500);
$favoritePlatform = post_string('favorite_platform', 100);

if ($username === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
    flash('danger', 'Inscription invalide: verifie le pseudo, email et mot de passe.');
    redirect_to('register.php');
}

try {
    $pdo->beginTransaction();

    // On ne stocke jamais le mot de passe en clair dans la base.
    $statement = $pdo->prepare('
        INSERT INTO users (username, email, password_hash)
        VALUES (:username, :email, :password_hash)
    ');
    $statement->execute([
        'username' => $username,
        'email' => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    ]);

    $userId = (int)$pdo->lastInsertId();

    $profileStatement = $pdo->prepare('
        INSERT INTO user_profiles (user_id, avatar_url, bio, favorite_platform)
        VALUES (:user_id, :avatar_url, :bio, :favorite_platform)
    ');
    $profileStatement->execute([
        'user_id' => $userId,
        'avatar_url' => 'https://images.unsplash.com/photo-1542751371-adc38448a05e?auto=format&fit=crop&w=300&q=80',
        'bio' => $bio !== '' ? $bio : 'Nouveau membre de Ludorivya.',
        'favorite_platform' => $favoritePlatform !== '' ? $favoritePlatform : 'PC',
    ]);

    $pdo->commit();

    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    $_SESSION['user_username'] = $username;
    $_SESSION['user_email'] = $email;

    flash('success', 'Compte cree avec succes.');
    redirect_to('profile.php');
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    flash('danger', 'Impossible de creer le compte. Le pseudo ou l email existe peut-etre deja.');
    redirect_to('register.php');
}

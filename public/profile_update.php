<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

require_login('login.php');

if ($pdo === null) {
    flash('danger', 'Base de données non connectée.');
    redirect_to('profile.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('profile.php');
}

require_valid_csrf('profile.php');

$bio = post_string('bio', 500);
$favoritePlatformId = post_int('favorite_platform_id', 1, 1000000);

// La plateforme preferee doit exister dans la table platforms.
if ($favoritePlatformId !== null) {
    $platformCheck = $pdo->prepare('SELECT COUNT(*) FROM platforms WHERE id = :id');
    $platformCheck->execute(['id' => $favoritePlatformId]);
    if ((int)$platformCheck->fetchColumn() === 0) {
        $favoritePlatformId = null;
    }
}

try {
    // Mise a jour de la relation 1-1 users <-> user_profiles.
    // ON DUPLICATE KEY couvre le cas (rare) d'un compte sans ligne de profil.
    $statement = $pdo->prepare('
        INSERT INTO user_profiles (user_id, bio, favorite_platform_id)
        VALUES (:user_id, :bio, :favorite_platform_id)
        ON DUPLICATE KEY UPDATE bio = VALUES(bio), favorite_platform_id = VALUES(favorite_platform_id)
    ');
    $statement->execute([
        'user_id' => current_user_id(),
        'bio' => $bio === '' ? null : $bio,
        'favorite_platform_id' => $favoritePlatformId,
    ]);
} catch (Throwable $exception) {
    error_log('[Ludorivya] Mise a jour profil impossible : ' . $exception->getMessage());
    flash('danger', 'La mise à jour du profil a échoué, merci de réessayer.');
    redirect_to('profile.php');
}

flash('success', 'Ton profil a été mis à jour.');
redirect_to('profile.php');

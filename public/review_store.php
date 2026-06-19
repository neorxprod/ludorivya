<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

require_login('login.php');

if ($pdo === null) {
    flash('danger', 'Base de données non connectée.');
    redirect_to('index.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('games.php');
}

require_valid_csrf('games.php');

$gameId = post_int('game_id', 1, 1000000000);
// La note arrive en etoiles (1 a 10) et est stockee sur 20 en base.
$stars = post_int('stars', 1, 10);
// Le commentaire est facultatif : on peut noter d'un simple clic.
$comment = post_string('comment', 1000);

if ($gameId === null || $stars === null) {
    flash('danger', 'Choisis une note entre 1 et 10 étoiles.');
    redirect_to($gameId !== null ? 'game.php?id=' . $gameId : 'games.php');
}

$rating = $stars * 2;

// On verifie que le jeu existe avant d'inserer (message propre plutot
// qu'une violation de cle etrangere).
$gameCheck = $pdo->prepare('SELECT COUNT(*) FROM games WHERE id = :id');
$gameCheck->execute(['id' => $gameId]);
if ((int)$gameCheck->fetchColumn() === 0) {
    flash('warning', 'Ce jeu n’existe pas.');
    redirect_to('games.php');
}

try {
    // Grace a UNIQUE (game_id, user_id) : insertion du premier avis,
    // ou mise a jour de l'avis existant (un seul avis par joueur et par jeu).
    $statement = $pdo->prepare('
        INSERT INTO reviews (game_id, user_id, rating, comment)
        VALUES (:game_id, :user_id, :rating, :comment)
        ON DUPLICATE KEY UPDATE rating = VALUES(rating), comment = VALUES(comment), created_at = CURRENT_TIMESTAMP
    ');
    $statement->execute([
        'game_id' => $gameId,
        'user_id' => current_user_id(),
        'rating' => $rating,
        'comment' => $comment,
    ]);

    // Avec ON DUPLICATE KEY, rowCount vaut 1 pour une insertion
    // et 2 pour une mise a jour : l'XP n'est donnee qu'au premier avis.
    if ($statement->rowCount() === 1) {
        award_xp($pdo, (int)current_user_id(), 20);
    }
} catch (Throwable $exception) {
    error_log('[Ludorivya] Enregistrement d’avis impossible : ' . $exception->getMessage());
    flash('danger', 'La publication de l’avis a échoué, merci de réessayer.');
    redirect_to('game.php?id=' . $gameId);
}

flash('success', 'Ta note de ' . $stars . '/10 a bien été enregistrée.');
redirect_to('game.php?id=' . $gameId);

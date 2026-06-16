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
$rating = post_int('rating', 0, 20);
$comment = post_string('comment', 1000);

if ($gameId === null || $rating === null || $comment === '') {
    flash('danger', 'Avis invalide : il faut une note entre 0 et 20 et un commentaire.');
    redirect_to($gameId !== null ? 'game.php?id=' . $gameId : 'games.php');
}

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
} catch (Throwable $exception) {
    error_log('[Ludorivya] Enregistrement d’avis impossible : ' . $exception->getMessage());
    flash('danger', 'La publication de l’avis a échoué, merci de réessayer.');
    redirect_to('game.php?id=' . $gameId);
}

flash('success', 'Ton avis a bien été enregistré.');
redirect_to('game.php?id=' . $gameId);

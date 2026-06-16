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
if ($gameId === null) {
    redirect_to('games.php');
}

try {
    // Le WHERE user_id garantit qu'on ne supprime QUE son propre avis.
    $statement = $pdo->prepare('DELETE FROM reviews WHERE game_id = :game_id AND user_id = :user_id');
    $statement->execute(['game_id' => $gameId, 'user_id' => current_user_id()]);
    $deleted = $statement->rowCount() > 0;
} catch (Throwable $exception) {
    error_log('[Ludorivya] Suppression d’avis impossible : ' . $exception->getMessage());
    flash('danger', 'La suppression a échoué, merci de réessayer.');
    redirect_to('game.php?id=' . $gameId);
}

flash($deleted ? 'success' : 'warning', $deleted ? 'Ton avis a été supprimé.' : 'Aucun avis à supprimer.');
redirect_to('game.php?id=' . $gameId);

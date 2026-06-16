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
$status = post_string('status', 20);
$playtime = post_float('playtime_hours', 0, 99999);

$allowedStatuses = ['souhaite', 'en_cours', 'termine', 'abandonne'];

if ($gameId === null || !in_array($status, $allowedStatuses, true) || $playtime === null) {
    flash('danger', 'Entrée de bibliothèque invalide.');
    redirect_to($gameId !== null ? 'game.php?id=' . $gameId : 'games.php');
}

$gameCheck = $pdo->prepare('SELECT COUNT(*) FROM games WHERE id = :id');
$gameCheck->execute(['id' => $gameId]);
if ((int)$gameCheck->fetchColumn() === 0) {
    flash('warning', 'Ce jeu n’existe pas.');
    redirect_to('games.php');
}

try {
    // UNIQUE (user_id, game_id) : ajout a la bibliotheque ou mise a jour
    // du statut / temps de jeu si le jeu y est deja.
    $statement = $pdo->prepare('
        INSERT INTO library_entries (user_id, game_id, status, playtime_hours)
        VALUES (:user_id, :game_id, :status, :playtime_hours)
        ON DUPLICATE KEY UPDATE status = VALUES(status), playtime_hours = VALUES(playtime_hours)
    ');
    $statement->execute([
        'user_id' => current_user_id(),
        'game_id' => $gameId,
        'status' => $status,
        'playtime_hours' => $playtime,
    ]);
} catch (Throwable $exception) {
    error_log('[Ludorivya] Mise a jour bibliotheque impossible : ' . $exception->getMessage());
    flash('danger', 'La mise à jour de ta bibliothèque a échoué, merci de réessayer.');
    redirect_to('game.php?id=' . $gameId);
}

flash('success', 'Ta bibliothèque a été mise à jour.');
redirect_to('game.php?id=' . $gameId);

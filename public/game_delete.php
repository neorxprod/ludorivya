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
    flash('warning', 'Ce jeu n’existe pas.');
    redirect_to('games.php');
}

$gameStatement = $pdo->prepare('SELECT id, title, created_by FROM games WHERE id = :id');
$gameStatement->execute(['id' => $gameId]);
$game = $gameStatement->fetch();

if (!$game) {
    flash('warning', 'Ce jeu n’existe pas.');
    redirect_to('games.php');
}

// Autorisation : seul le createur de la fiche peut la supprimer.
if ($game['created_by'] === null || (int)$game['created_by'] !== current_user_id()) {
    flash('danger', 'Tu ne peux supprimer que les jeux que tu as ajoutés.');
    redirect_to('game.php?id=' . $gameId);
}

try {
    // Les ON DELETE CASCADE du schema suppriment automatiquement
    // les liaisons N-N, les avis et les entrees de bibliotheque.
    $deleteStatement = $pdo->prepare('DELETE FROM games WHERE id = :id');
    $deleteStatement->execute(['id' => $gameId]);
} catch (Throwable $exception) {
    error_log('[Ludorivya] Suppression de jeu impossible : ' . $exception->getMessage());
    flash('danger', 'La suppression a échoué, merci de réessayer.');
    redirect_to('game.php?id=' . $gameId);
}

flash('success', 'Le jeu « ' . $game['title'] . ' » a été supprimé du catalogue.');
redirect_to('games.php');

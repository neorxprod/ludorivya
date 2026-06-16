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

// Autorisation : seul le createur de la fiche peut la modifier.
if ($game['created_by'] === null || (int)$game['created_by'] !== current_user_id()) {
    flash('danger', 'Tu ne peux modifier que les jeux que tu as ajoutés.');
    redirect_to('game.php?id=' . $gameId);
}

['data' => $data, 'errors' => $errors] = validate_game_form($pdo);

if ($errors !== []) {
    flash('danger', implode(' ', $errors));
    redirect_to('edit.php?id=' . $gameId);
}

try {
    // Transaction : mise a jour du jeu + remplacement complet des liaisons N-N.
    $pdo->beginTransaction();

    $updateGame = $pdo->prepare('
        UPDATE games
        SET studio_id = :studio_id, title = :title, description = :description,
            release_date = :release_date, age_rating = :age_rating, cover_url = :cover_url
        WHERE id = :id
    ');
    $updateGame->execute([
        'studio_id' => $data['studio_id'],
        'title' => $data['title'],
        'description' => $data['description'],
        'release_date' => $data['release_date'],
        'age_rating' => $data['age_rating'],
        'cover_url' => $data['cover_url'],
        'id' => $gameId,
    ]);

    // Strategie simple et fiable pour les N-N : on efface puis on reinsere.
    $pdo->prepare('DELETE FROM game_platforms WHERE game_id = :id')->execute(['id' => $gameId]);
    $insertPlatform = $pdo->prepare('INSERT INTO game_platforms (game_id, platform_id) VALUES (:game_id, :platform_id)');
    foreach ($data['platform_ids'] as $platformId) {
        $insertPlatform->execute(['game_id' => $gameId, 'platform_id' => $platformId]);
    }

    $pdo->prepare('DELETE FROM game_genres WHERE game_id = :id')->execute(['id' => $gameId]);
    $insertGenre = $pdo->prepare('INSERT INTO game_genres (game_id, genre_id) VALUES (:game_id, :genre_id)');
    foreach ($data['genre_ids'] as $genreId) {
        $insertGenre->execute(['game_id' => $gameId, 'genre_id' => $genreId]);
    }

    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[Ludorivya] Modification de jeu impossible : ' . $exception->getMessage());
    flash('danger', 'La modification a échoué, merci de réessayer.');
    redirect_to('edit.php?id=' . $gameId);
}

flash('success', 'Le jeu « ' . $data['title'] . ' » a été mis à jour.');
redirect_to('game.php?id=' . $gameId);

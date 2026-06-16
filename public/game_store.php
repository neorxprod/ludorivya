<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

require_login('login.php?redirect=create.php');

if ($pdo === null) {
    flash('danger', 'Base de données non connectée.');
    redirect_to('index.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('create.php');
}

require_valid_csrf('create.php');

['data' => $data, 'errors' => $errors] = validate_game_form($pdo);

if ($errors !== []) {
    flash('danger', implode(' ', $errors));
    redirect_to('create.php');
}

try {
    // Transaction : le jeu et ses liaisons N-N sont inseres ensemble ou pas du tout.
    $pdo->beginTransaction();

    $insertGame = $pdo->prepare('
        INSERT INTO games (studio_id, created_by, title, description, release_date, age_rating, cover_url)
        VALUES (:studio_id, :created_by, :title, :description, :release_date, :age_rating, :cover_url)
    ');
    $insertGame->execute([
        'studio_id' => $data['studio_id'],
        'created_by' => current_user_id(),
        'title' => $data['title'],
        'description' => $data['description'],
        'release_date' => $data['release_date'],
        'age_rating' => $data['age_rating'],
        'cover_url' => $data['cover_url'],
    ]);

    $gameId = (int)$pdo->lastInsertId();

    $insertPlatform = $pdo->prepare('INSERT INTO game_platforms (game_id, platform_id) VALUES (:game_id, :platform_id)');
    foreach ($data['platform_ids'] as $platformId) {
        $insertPlatform->execute(['game_id' => $gameId, 'platform_id' => $platformId]);
    }

    $insertGenre = $pdo->prepare('INSERT INTO game_genres (game_id, genre_id) VALUES (:game_id, :genre_id)');
    foreach ($data['genre_ids'] as $genreId) {
        $insertGenre->execute(['game_id' => $gameId, 'genre_id' => $genreId]);
    }

    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // Le detail technique part dans les logs, pas a l'ecran.
    error_log('[Ludorivya] Ajout de jeu impossible : ' . $exception->getMessage());
    flash('danger', 'L’ajout du jeu a échoué, merci de réessayer.');
    redirect_to('create.php');
}

flash('success', 'Le jeu « ' . $data['title'] . ' » a été ajouté au catalogue.');
redirect_to('game.php?id=' . $gameId);

<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

require_login('login.php?redirect=create.php');

if ($pdo === null) {
    flash('danger', 'Base de donnees non connectee.');
    redirect_to('index.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('create.php');
}

$title = post_string('title', 150);
$description = post_string('description', 2000);
$coverUrl = post_string('cover_url', 500);
$releaseDate = post_string('release_date', 10);
$studioId = post_int('studio_id', 1, 1000000);
$ageRating = post_int('age_rating', 3, 18);
$livePlayers = post_int('live_players', 0, 100000000);
$platformIds = post_int_array('platform_ids');
$genreIds = post_int_array('genre_ids');

if ($title === '' || $description === '' || $releaseDate === '' || $studioId === null || $ageRating === null || $livePlayers === null || $platformIds === []) {
    flash('danger', 'Formulaire incomplet: titre, description, date, studio, age et au moins une plateforme sont obligatoires.');
    redirect_to('create.php');
}

if ($coverUrl === '') {
    $coverUrl = 'https://images.unsplash.com/photo-1550745165-9bc0b252726f?auto=format&fit=crop&w=900&q=80';
}

try {
    $pdo->beginTransaction();

    // On cree un slug unique pour eviter deux URL identiques.
    $baseSlug = slugify($title);
    $slug = $baseSlug;
    $suffix = 2;

    $slugStatement = $pdo->prepare('SELECT COUNT(*) FROM games WHERE slug = :slug');
    while (true) {
        $slugStatement->execute(['slug' => $slug]);
        if ((int)$slugStatement->fetchColumn() === 0) {
            break;
        }
        $slug = $baseSlug . '-' . $suffix;
        $suffix++;
    }

    // Les insertions du jeu + plateformes + genres doivent reussir ensemble.
    $insertGame = $pdo->prepare("
        INSERT INTO games (studio_id, slug, title, description, release_date, age_rating, cover_url, live_players)
        VALUES (:studio_id, :slug, :title, :description, :release_date, :age_rating, :cover_url, :live_players)
    ");
    $insertGame->execute([
        'studio_id' => $studioId,
        'slug' => $slug,
        'title' => $title,
        'description' => $description,
        'release_date' => $releaseDate,
        'age_rating' => $ageRating,
        'cover_url' => $coverUrl,
        'live_players' => $livePlayers,
    ]);

    $gameId = (int)$pdo->lastInsertId();

    $insertPlatform = $pdo->prepare('INSERT INTO game_platforms (game_id, platform_id, release_region) VALUES (:game_id, :platform_id, :release_region)');
    foreach (array_unique($platformIds) as $platformId) {
        $insertPlatform->execute([
            'game_id' => $gameId,
            'platform_id' => $platformId,
            'release_region' => 'Monde',
        ]);
    }

    $insertGenre = $pdo->prepare('INSERT INTO game_genres (game_id, genre_id) VALUES (:game_id, :genre_id)');
    foreach (array_unique($genreIds) as $genreId) {
        $insertGenre->execute([
            'game_id' => $gameId,
            'genre_id' => $genreId,
        ]);
    }

    $pdo->commit();
    flash('success', 'Jeu ajoute avec succes.');
    redirect_to('game.php?id=' . $gameId);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    flash('danger', 'Erreur pendant l ajout du jeu: ' . $exception->getMessage());
    redirect_to('create.php');
}

<?php
// =============================================================
//  Generateur de database/schema.sql a partir du dataset JSON.
//
//  Pipeline complet du catalogue (voir aussi fetch_covers.ps1) :
//    1. games.json : dataset redige a la main (titres reels, studios,
//       genres, plateformes, metascore approximatif, descriptions
//       originales en francais, appid Steam pour la jaquette).
//    2. fetch_covers.ps1 : telecharge une fois les jaquettes officielles
//       depuis le CDN public de Steam vers public/assets/img/covers/
//       (un appid invalide = pas d'image = jeu ecarte du seed).
//    3. build_seed.php (ce script) : genere schema.sql complet
//       (tables + donnees), avec une simulation de 150 duels Elo
//       reproductible (mt_srand fixe) pour un classement credible.
//
//  Usage : php build_seed.php   (depuis ce dossier)
// =============================================================

declare(strict_types=1);

$gamesJson = json_decode(file_get_contents(__DIR__ . '/games.json'), true);
$coversDir = dirname(__DIR__, 2) . '/public/assets/img/covers';
$output = dirname(__DIR__) . '/schema.sql';

function sqlString(?string $value): string
{
    if ($value === null) {
        return 'NULL';
    }
    return "'" . str_replace(["\\", "'"], ["\\\\", "''"], $value) . "'";
}

// ---- Normalisation : studios, genres, plateformes ----
$genreList = ['Action', 'Aventure', 'RPG', 'Stratégie', 'Simulation', 'Multijoueur', 'FPS', 'Course', 'Plateforme', 'Horreur', 'Roguelite', 'Gestion'];
$platformList = [
    ['PC', 'Multiple', 'Actuelle'],
    ['PlayStation 5', 'Sony', '9e génération'],
    ['PlayStation 4', 'Sony', '8e génération'],
    ['Xbox Series X/S', 'Microsoft', '9e génération'],
    ['Xbox One', 'Microsoft', '8e génération'],
    ['Nintendo Switch', 'Nintendo', '8e génération'],
    ['Mobile', 'Multiple', 'Actuelle'],
];

$genreId = [];
foreach ($genreList as $i => $g) { $genreId[$g] = $i + 1; }
$platformId = [];
foreach ($platformList as $i => $p) { $platformId[$p[0]] = $i + 1; }

// Studios uniques (premiere occurrence gagne pour pays/annee).
$studios = [];
$studioId = [];
foreach ($gamesJson as $g) {
    $name = trim($g['studio']);
    if (!isset($studioId[$name])) {
        $studioId[$name] = count($studios) + 1;
        $studios[] = ['name' => $name, 'country' => $g['studio_country'], 'year' => (int)$g['studio_year']];
    }
}

// Jeux : seulement ceux dont la jaquette a ete telechargee.
$games = [];
foreach ($gamesJson as $g) {
    $cover = $coversDir . '/' . $g['appid'] . '.jpg';
    if (!is_file($cover) || filesize($cover) < 5000) {
        fwrite(STDERR, "SKIP (pas de jaquette valide) : {$g['title']}\n");
        continue;
    }
    $genres = array_values(array_unique(array_filter($g['genres'], fn ($x) => isset($genreId[$x]))));
    $platforms = array_values(array_unique(array_filter($g['platforms'], fn ($x) => isset($platformId[$x]))));
    if ($genres === [] || $platforms === []) {
        fwrite(STDERR, "SKIP (genres/plateformes invalides) : {$g['title']}\n");
        continue;
    }
    $age = (int)$g['age_rating'];
    if (!in_array($age, [3, 7, 12, 16, 18], true)) { $age = 12; }
    $games[] = [
        'title' => trim($g['title']),
        'studio_id' => $studioId[trim($g['studio'])],
        'date' => $g['release_date'],
        'age' => $age,
        'cover' => 'assets/img/covers/' . $g['appid'] . '.jpg',
        'metascore' => max(0, min(100, (int)$g['metascore'])),
        'desc' => trim($g['description_fr']),
        'genres' => $genres,
        'platforms' => $platforms,
    ];
}
usort($games, fn ($a, $b) => strcasecmp($a['title'], $b['title']));
$gameIdByTitle = [];
foreach ($games as $i => $g) { $gameIdByTitle[$g['title']] = $i + 1; }
fwrite(STDERR, count($games) . " jeux retenus\n");

// ---- Simulation Elo : ~150 duels joues par les 3 comptes demo ----
mt_srand(42); // reproductible
$elo = array_fill(1, count($games), 1000);
$duels = [];
for ($i = 0; $i < 150; $i++) {
    $a = mt_rand(1, count($games));
    do { $b = mt_rand(1, count($games)); } while ($b === $a);
    // Le jeu au meilleur metascore gagne plus souvent (70 %), pour un classement credible.
    $msA = $games[$a - 1]['metascore'];
    $msB = $games[$b - 1]['metascore'];
    $favori = $msA >= $msB ? $a : $b;
    $outsider = $favori === $a ? $b : $a;
    $winner = (mt_rand(1, 100) <= 70) ? $favori : $outsider;
    $loser = $winner === $a ? $b : $a;
    $expected = 1 / (1 + 10 ** (($elo[$loser] - $elo[$winner]) / 400));
    $delta = max(1, (int)round(32 * (1 - $expected)));
    $elo[$winner] += $delta;
    $elo[$loser] -= $delta;
    $duels[] = [mt_rand(1, 3), $winner, $loser];
}

// ---- Avis de demonstration (titres reels -> ids resolus) ----
$reviewSeed = [
    ['Hades', 1, 18, 'Chaque mort rend la suivante plus intéressante : la narration roguelite parfaite.'],
    ['Hades', 2, 17, 'Le gameplay est d’une précision rare, et la bande-son porte chaque tentative.'],
    ['Elden Ring', 1, 19, 'L’Entre-terre est immense et chaque détour récompense la curiosité. Monumental.'],
    ['Elden Ring', 3, 16, 'Très exigeant mais juste : chaque victoire se mérite vraiment.'],
    ['The Witcher 3: Wild Hunt', 2, 19, 'Les quêtes secondaires sont mieux écrites que les quêtes principales d’autres jeux.'],
    ['The Witcher 3: Wild Hunt', 3, 18, 'Geralt, l’ambiance, les choix moraux : un sommet du RPG.'],
    ['Hollow Knight', 1, 18, 'Un métroidvania d’une élégance folle, dur mais jamais injuste.'],
    ['Stardew Valley', 3, 17, 'Le jeu le plus apaisant de ma bibliothèque, on y revient toujours.'],
    ['Portal 2', 2, 18, 'L’écriture la plus drôle du jeu vidéo, et des énigmes brillantes.'],
    ['Celeste', 1, 17, 'Une plateforme exigeante qui parle de santé mentale avec une vraie délicatesse.'],
    ['Baldur\'s Gate 3', 2, 19, 'La liberté totale : chaque partie raconte une histoire différente.'],
    ['Red Dead Redemption 2', 3, 18, 'Le monde ouvert le plus vivant jamais créé, tout simplement.'],
    ['DOOM Eternal', 2, 16, 'Un ballet de destruction d’une intensité permanente.'],
    ['Disco Elysium - The Final Cut', 1, 18, 'On ne joue pas, on lit le meilleur roman noir interactif du genre.'],
    ['Sekiro: Shadows Die Twice', 2, 17, 'Le système de parade le plus satisfaisant de FromSoftware.'],
    ['Vampire Survivors', 3, 15, 'Impossible de ne faire qu’une seule partie. Redoutable d’efficacité.'],
    ['Slay the Spire', 1, 16, 'Le deck-builder qui a défini le genre, toujours aussi addictif.'],
    ['Subnautica', 3, 17, 'La meilleure exploration sous-marine du jeu vidéo, entre émerveillement et angoisse.'],
    ['Cyberpunk 2077', 2, 15, 'Night City est spectaculaire, et le jeu est aujourd’hui à la hauteur de ses promesses.'],
    ['It Takes Two', 1, 17, 'La coop la plus inventive qui soit : chaque niveau change les règles.'],
    ['Terraria', 2, 16, 'Une profondeur infinie cachée derrière des pixels : le bac à sable ultime.'],
    ['Outer Wilds', 1, 19, 'La plus belle idée de game design de la décennie. À faire absolument sans spoiler.'],
];

$libsSeed = [
    [1, 'Elden Ring', 'termine', 112.5], [1, 'Hades', 'termine', 64.0], [1, 'Hollow Knight', 'en_cours', 31.0],
    [1, 'Outer Wilds', 'termine', 22.0], [1, 'Baldur\'s Gate 3', 'en_cours', 48.5], [1, 'Celeste', 'souhaite', 0],
    [2, 'The Witcher 3: Wild Hunt', 'termine', 180.0], [2, 'Cyberpunk 2077', 'en_cours', 55.0],
    [2, 'DOOM Eternal', 'termine', 28.0], [2, 'Portal 2', 'termine', 12.0], [2, 'Destiny 2', 'en_cours', 320.0],
    [2, 'Sekiro: Shadows Die Twice', 'abandonne', 14.5],
    [3, 'Stardew Valley', 'en_cours', 95.0], [3, 'Vampire Survivors', 'termine', 41.0],
    [3, 'Subnautica', 'termine', 38.5], [3, 'Red Dead Redemption 2', 'en_cours', 60.0], [3, 'Terraria', 'souhaite', 0],
];

// ---- Sujets de forum de demonstration ----
$topicSeed = [
    [1, 'Elden Ring', 'Le boss qui vous a fait abandonner ?', 'Pour moi c’est Malenia, j’ai dû y passer trois soirées entières. Vous avez tenu combien de tentatives ?', [
        [2, 'Malenia évidemment. Le waterfowl dance est juste injuste sans le bon timing.'],
        [3, 'Moi c’est Radahn avant le nerf, en coop il fondait mais en solo c’était l’enfer.'],
    ]],
    [2, null, 'Votre top 3 de l’année, sans réfléchir', 'Allez, les trois premiers qui vous viennent. Pas le droit de modifier après coup !', [
        [1, 'Baldur’s Gate 3, Hades, Outer Wilds. Aucun regret.'],
        [3, 'Stardew Valley, Vampire Survivors, Subnautica. Team détente.'],
        [2, 'CS2, DOOM Eternal, The Witcher 3. Oui je sais, ça pique.'],
    ]],
    [3, 'Stardew Valley', 'Stardew : plutôt élevage ou cultures ?', 'Je relance une ferme et j’hésite sur la spécialisation. Les animaux rapportent bien mais les cultures d’automne sont rentables…', [
        [1, 'Les canneberges d’automne, c’est le jackpot assuré. Les animaux c’est pour le plaisir.'],
    ]],
    [1, 'Hollow Knight', 'Silksong existe-t-il vraiment ?', 'Chaque Direct je me dis que c’est le bon. Chaque Direct je suis déçu. On tient le coup ensemble ?', [
        [2, 'C’est devenu un meme à ce stade. Mais la base Hollow Knight est tellement bonne que j’attendrai.'],
        [3, 'Il est sorti dans nos cœurs depuis longtemps.'],
    ]],
    [2, 'Counter-Strike 2', 'Vos réglages de visée en 2026', 'Sensibilité, DPI, résolution étirée ou pas ? Je refais ma config et je veux vos avis.', [
        [1, '800 DPI, sensi 1.2, résolution native. La constance avant tout.'],
    ]],
    [3, null, 'Quel jeu rétro faire découvrir à un débutant ?', 'Ma petite sœur veut découvrir les classiques. Je pensais commencer par Portal puis un Zelda-like. Des idées ?', [
        [1, 'Portal est parfait pour commencer : court, drôle, brillant. Ensuite Celeste avec le mode assisté.'],
        [2, 'Half-Life 2 reste une leçon de game design, même vingt ans après.'],
    ]],
];

// ---- Generation du SQL ----
$sql = [];
$sql[] = <<<'DDL'
-- =============================================================
--  LUDORIVYA - Schema relationnel v3 (MySQL / MariaDB, via XAMPP)
--  Necessite MySQL 8.0.16+ ou MariaDB 10.4+ pour les contraintes CHECK.
--
--  Relations demandees par le sujet :
--    1-1 : users <-> user_profiles            (PK partagee)
--    1-N : studios -> games                   (un studio, plusieurs jeux)
--    N-N : games <-> platforms                (table game_platforms)
--  Relations supplementaires :
--    N-N : games <-> genres                   (table game_genres)
--    N-N porteuse : users <-> games via library_entries (statut, heures)
--    N-N porteuse : users <-> games via reviews         (note, commentaire)
--    N-N porteuse : users <-> games via duels (mode versus, classement Elo)
--    1-N : users -> games (created_by)        (qui a ajoute le jeu)
--    1-N : platforms -> user_profiles         (plateforme preferee)
--
--  Donnees : ~115 vrais jeux (jaquettes locales, descriptions originales,
--  note presse approximative type Metacritic). Le score Elo de depart est
--  issu d'une simulation de 150 duels (voir table duels).
-- =============================================================

CREATE DATABASE IF NOT EXISTS ludorivya
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE ludorivya;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS topic_replies;
DROP TABLE IF EXISTS topics;
DROP TABLE IF EXISTS duels;
DROP TABLE IF EXISTS reviews;
DROP TABLE IF EXISTS library_entries;
DROP TABLE IF EXISTS game_genres;
DROP TABLE IF EXISTS game_platforms;
DROP TABLE IF EXISTS user_profiles;
DROP TABLE IF EXISTS games;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS genres;
DROP TABLE IF EXISTS platforms;
DROP TABLE IF EXISTS studios;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE studios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE,
    country VARCHAR(80) NOT NULL,
    founded_year SMALLINT UNSIGNED NULL,
    website VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE platforms (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    manufacturer VARCHAR(100) NOT NULL,
    generation VARCHAR(50) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE genres (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(60) NOT NULL UNIQUE,
    email VARCHAR(160) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    xp INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Relation 1-1 : user_id est a la fois PRIMARY KEY et FOREIGN KEY.
CREATE TABLE user_profiles (
    user_id INT UNSIGNED PRIMARY KEY,
    bio TEXT NULL,
    favorite_platform_id INT UNSIGNED NULL,
    CONSTRAINT fk_user_profiles_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_user_profiles_platform
        FOREIGN KEY (favorite_platform_id) REFERENCES platforms(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Relation 1-N : un studio produit plusieurs jeux.
-- metascore : note presse approximative (0-100).
-- elo : score du classement communautaire, mis a jour a chaque duel versus.
CREATE TABLE games (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    studio_id INT UNSIGNED NOT NULL,
    created_by INT UNSIGNED NULL,
    title VARCHAR(150) NOT NULL,
    description TEXT NOT NULL,
    release_date DATE NOT NULL,
    age_rating TINYINT UNSIGNED NOT NULL,
    cover_url VARCHAR(500) NULL,
    metascore TINYINT UNSIGNED NULL,
    elo INT NOT NULL DEFAULT 1000,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_games_age_rating CHECK (age_rating BETWEEN 3 AND 18),
    CONSTRAINT chk_games_metascore CHECK (metascore IS NULL OR metascore <= 100),
    CONSTRAINT fk_games_studio
        FOREIGN KEY (studio_id) REFERENCES studios(id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_games_created_by
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON DELETE SET NULL,
    INDEX idx_games_title (title),
    INDEX idx_games_release_date (release_date),
    INDEX idx_games_elo (elo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Relation N-N a cle primaire composite.
CREATE TABLE game_platforms (
    game_id INT UNSIGNED NOT NULL,
    platform_id INT UNSIGNED NOT NULL,
    release_region VARCHAR(60) NOT NULL DEFAULT 'Monde',
    PRIMARY KEY (game_id, platform_id),
    CONSTRAINT fk_game_platforms_game
        FOREIGN KEY (game_id) REFERENCES games(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_game_platforms_platform
        FOREIGN KEY (platform_id) REFERENCES platforms(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Deuxieme N-N : sert au filtrage du catalogue par genre.
CREATE TABLE game_genres (
    game_id INT UNSIGNED NOT NULL,
    genre_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (game_id, genre_id),
    CONSTRAINT fk_game_genres_game
        FOREIGN KEY (game_id) REFERENCES games(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_game_genres_genre
        FOREIGN KEY (genre_id) REFERENCES genres(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- N-N porteuse : la bibliotheque personnelle.
CREATE TABLE library_entries (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    game_id INT UNSIGNED NOT NULL,
    status ENUM('souhaite', 'en_cours', 'termine', 'abandonne') NOT NULL DEFAULT 'souhaite',
    playtime_hours DECIMAL(6, 1) NOT NULL DEFAULT 0,
    added_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_library_user_game (user_id, game_id),
    CONSTRAINT fk_library_entries_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_library_entries_game
        FOREIGN KEY (game_id) REFERENCES games(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- N-N porteuse : un seul avis par joueur et par jeu.
CREATE TABLE reviews (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    game_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    rating TINYINT UNSIGNED NOT NULL,
    comment TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_reviews_user_game (game_id, user_id),
    CONSTRAINT chk_reviews_rating CHECK (rating BETWEEN 0 AND 20),
    CONSTRAINT fk_reviews_game
        FOREIGN KEY (game_id) REFERENCES games(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_reviews_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mode versus : chaque ligne est un vote "winner bat loser".
-- Le user est conserve en SET NULL pour garder les statistiques des jeux
-- meme si le compte est supprime.
CREATE TABLE duels (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    winner_game_id INT UNSIGNED NOT NULL,
    loser_game_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_duels_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_duels_winner
        FOREIGN KEY (winner_game_id) REFERENCES games(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_duels_loser
        FOREIGN KEY (loser_game_id) REFERENCES games(id)
        ON DELETE CASCADE,
    INDEX idx_duels_winner (winner_game_id),
    INDEX idx_duels_loser (loser_game_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Forum : sujets de discussion, optionnellement lies a un jeu.
CREATE TABLE topics (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    game_id INT UNSIGNED NULL,
    title VARCHAR(150) NOT NULL,
    body TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_topics_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_topics_game
        FOREIGN KEY (game_id) REFERENCES games(id)
        ON DELETE SET NULL,
    INDEX idx_topics_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Relation 1-N : un sujet a plusieurs reponses.
CREATE TABLE topic_replies (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    topic_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    body TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_replies_topic
        FOREIGN KEY (topic_id) REFERENCES topics(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_replies_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE,
    INDEX idx_replies_topic (topic_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
--  Donnees de demonstration
-- =============================================================
DDL;

// Studios
$rows = [];
foreach ($studios as $s) {
    $year = $s['year'] > 1900 && $s['year'] < 2026 ? (string)$s['year'] : 'NULL';
    $rows[] = '(' . sqlString($s['name']) . ', ' . sqlString($s['country']) . ', ' . $year . ', NULL)';
}
$sql[] = "INSERT INTO studios (name, country, founded_year, website) VALUES\n" . implode(",\n", $rows) . ';';

// Platforms
$rows = [];
foreach ($platformList as $p) {
    $rows[] = '(' . sqlString($p[0]) . ', ' . sqlString($p[1]) . ', ' . sqlString($p[2]) . ')';
}
$sql[] = "INSERT INTO platforms (name, manufacturer, generation) VALUES\n" . implode(",\n", $rows) . ';';

// Genres
$rows = [];
foreach ($genreList as $g) { $rows[] = '(' . sqlString($g) . ')'; }
$sql[] = "INSERT INTO genres (name) VALUES\n" . implode(",\n", $rows) . ';';

// Users (xp coherent avec duels + avis + bibliotheques + forum)
$duelCountByUser = [1 => 0, 2 => 0, 3 => 0];
foreach ($duels as $d) { $duelCountByUser[$d[0]]++; }
$reviewCountByUser = [1 => 0, 2 => 0, 3 => 0];
foreach ($reviewSeed as $r) { $reviewCountByUser[$r[1]]++; }
$libCountByUser = [1 => 0, 2 => 0, 3 => 0];
foreach ($libsSeed as $l) { $libCountByUser[$l[0]]++; }
$topicCountByUser = [1 => 0, 2 => 0, 3 => 0];
$replyCountByUser = [1 => 0, 2 => 0, 3 => 0];
foreach ($topicSeed as $t) {
    $topicCountByUser[$t[0]]++;
    foreach ($t[4] as $rep) { $replyCountByUser[$rep[0]]++; }
}

$userRows = [];
$userData = [
    [1, 'nora', 'nora@example.test', '$2y$10$6RX979qMXQCassrp3nXhPuXHVO.PxDvwHwjWoCHHW8UWrhCRGm7ie'],
    [2, 'samir', 'samir@example.test', '$2y$10$Pr3EXuAtRdybD13yJS5IJ.4KGl6OmFOEMMXvtI7lP/9t/VJ.3PGg.'],
    [3, 'manel', 'manel@example.test', '$2y$10$Par5OsLxL7IIUBjNRBLa8.QbOXe0CHp1H90f.riUMvDxFiy1tPJdC'],
];
foreach ($userData as [$id, $name, $mail, $hash]) {
    $xp = $duelCountByUser[$id] * 5 + $reviewCountByUser[$id] * 20 + $libCountByUser[$id] * 10
        + $topicCountByUser[$id] * 15 + $replyCountByUser[$id] * 5;
    $userRows[] = '(' . sqlString($name) . ', ' . sqlString($mail) . ', ' . sqlString($hash) . ', ' . $xp . ')';
}
$sql[] = "-- Mots de passe : nora -> Ludorivya2026! / samir -> SamirJoue2026! / manel -> ManelTeste2026!\n"
    . "INSERT INTO users (username, email, password_hash, xp) VALUES\n" . implode(",\n", $userRows) . ';';

$sql[] = "INSERT INTO user_profiles (user_id, bio, favorite_platform_id) VALUES\n"
    . "(1, 'Fan de RPG et de mondes ouverts, toujours en quête de la quête parfaite.', 1),\n"
    . "(2, 'Joueur compétitif, adore comparer les statistiques et grimper les classements.', 2),\n"
    . "(3, 'Je teste surtout les jeux indépendants et les pépites méconnues.', 6);";

// Games
$rows = [];
foreach ($games as $i => $g) {
    $rows[] = '(' . $g['studio_id'] . ', NULL, ' . sqlString($g['title']) . ', ' . sqlString($g['desc']) . ', '
        . sqlString($g['date']) . ', ' . $g['age'] . ', ' . sqlString($g['cover']) . ', '
        . $g['metascore'] . ', ' . $elo[$i + 1] . ')';
}
$sql[] = "INSERT INTO games (studio_id, created_by, title, description, release_date, age_rating, cover_url, metascore, elo) VALUES\n" . implode(",\n", $rows) . ';';

// game_platforms
$rows = [];
foreach ($games as $i => $g) {
    foreach ($g['platforms'] as $p) {
        $rows[] = '(' . ($i + 1) . ', ' . $platformId[$p] . ')';
    }
}
$sql[] = "INSERT INTO game_platforms (game_id, platform_id) VALUES\n" . implode(",\n", $rows) . ';';

// game_genres
$rows = [];
foreach ($games as $i => $g) {
    foreach ($g['genres'] as $gen) {
        $rows[] = '(' . ($i + 1) . ', ' . $genreId[$gen] . ')';
    }
}
$sql[] = "INSERT INTO game_genres (game_id, genre_id) VALUES\n" . implode(",\n", $rows) . ';';

// library_entries
$rows = [];
foreach ($libsSeed as [$uid, $title, $status, $hours]) {
    if (!isset($gameIdByTitle[$title])) { fwrite(STDERR, "LIB titre inconnu : $title\n"); continue; }
    $rows[] = '(' . $uid . ', ' . $gameIdByTitle[$title] . ', ' . sqlString($status) . ', ' . $hours . ')';
}
$sql[] = "INSERT INTO library_entries (user_id, game_id, status, playtime_hours) VALUES\n" . implode(",\n", $rows) . ';';

// reviews
$rows = [];
foreach ($reviewSeed as [$title, $uid, $rating, $comment]) {
    if (!isset($gameIdByTitle[$title])) { fwrite(STDERR, "AVIS titre inconnu : $title\n"); continue; }
    $rows[] = '(' . $gameIdByTitle[$title] . ', ' . $uid . ', ' . $rating . ', ' . sqlString($comment) . ')';
}
$sql[] = "INSERT INTO reviews (game_id, user_id, rating, comment) VALUES\n" . implode(",\n", $rows) . ';';

// duels
$rows = [];
foreach ($duels as [$uid, $w, $l]) {
    $rows[] = '(' . $uid . ', ' . $w . ', ' . $l . ')';
}
$sql[] = "-- 150 duels simules : ils expliquent les scores Elo ci-dessus.\n"
    . "INSERT INTO duels (user_id, winner_game_id, loser_game_id) VALUES\n" . implode(",\n", $rows) . ';';

// topics + topic_replies
$topicRows = [];
$replyRows = [];
foreach ($topicSeed as $ti => [$uid, $gameTitle, $title, $body, $replies]) {
    $gid = 'NULL';
    if ($gameTitle !== null) {
        if (!isset($gameIdByTitle[$gameTitle])) { fwrite(STDERR, "FORUM titre inconnu : $gameTitle\n"); }
        else { $gid = (string)$gameIdByTitle[$gameTitle]; }
    }
    $topicRows[] = '(' . $uid . ', ' . $gid . ', ' . sqlString($title) . ', ' . sqlString($body) . ')';
    foreach ($replies as [$ruid, $rbody]) {
        $replyRows[] = '(' . ($ti + 1) . ', ' . $ruid . ', ' . sqlString($rbody) . ')';
    }
}
$sql[] = "INSERT INTO topics (user_id, game_id, title, body) VALUES\n" . implode(",\n", $topicRows) . ';';
$sql[] = "INSERT INTO topic_replies (topic_id, user_id, body) VALUES\n" . implode(",\n", $replyRows) . ';';

file_put_contents($output, implode("\n\n", $sql) . "\n");
fwrite(STDERR, "schema.sql ecrit : " . strlen(implode("\n\n", $sql)) . " octets\n");

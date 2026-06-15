-- =============================================================
--  LUDORIVYA - Schema relationnel (MySQL / MariaDB, via XAMPP)
--  Necessite MySQL 8.0.16+ ou MariaDB 10.4+ pour les contraintes CHECK.
--  (Les bornes sont de toute facon revalidees cote PHP.)
--
--  Relations demandees par le sujet :
--    1-1 : users <-> user_profiles            (PK partagee)
--    1-N : studios -> games                   (un studio, plusieurs jeux)
--    N-N : games <-> platforms                (table game_platforms)
--  Relations supplementaires :
--    N-N : games <-> genres                   (table game_genres)
--    N-N porteuse : users <-> games via library_entries (statut, temps de jeu)
--    N-N porteuse : users <-> games via reviews         (note, commentaire)
--    1-N : users -> games (created_by)        (qui a ajoute le jeu)
--    1-N : platforms -> user_profiles         (plateforme preferee)
-- =============================================================

CREATE DATABASE IF NOT EXISTS ludorivya
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE ludorivya;

SET FOREIGN_KEY_CHECKS = 0;
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
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Relation 1-1 : chaque utilisateur a au plus UN profil public.
-- user_id est a la fois PRIMARY KEY et FOREIGN KEY (PK partagee),
-- ce qui garantit strictement le 1-1 au niveau de la base.
-- users = identite / connexion ; user_profiles = presentation publique.
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

-- Relation 1-N : un studio produit plusieurs jeux, chaque jeu a un studio.
-- ON DELETE RESTRICT : on ne peut pas supprimer un studio qui a des jeux.
-- created_by : qui a ajoute le jeu (NULL = catalogue d'origine) ;
-- seule cette personne peut ensuite le modifier ou le supprimer.
CREATE TABLE games (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    studio_id INT UNSIGNED NOT NULL,
    created_by INT UNSIGNED NULL,
    title VARCHAR(150) NOT NULL,
    description TEXT NOT NULL,
    release_date DATE NOT NULL,
    age_rating TINYINT UNSIGNED NOT NULL,
    cover_url VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_games_age_rating CHECK (age_rating BETWEEN 3 AND 18),
    CONSTRAINT fk_games_studio
        FOREIGN KEY (studio_id) REFERENCES studios(id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_games_created_by
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON DELETE SET NULL,
    INDEX idx_games_title (title),
    INDEX idx_games_release_date (release_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Relation N-N : un jeu sort sur plusieurs plateformes, une plateforme
-- accueille plusieurs jeux. PRIMARY KEY composite = pas de doublon possible.
-- release_region est un attribut porte par la relation elle-meme.
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

-- Deuxieme relation N-N : sert au filtrage du catalogue par genre.
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

-- Relation N-N porteuse d'attributs : la bibliotheque personnelle.
-- UNIQUE (user_id, game_id) = un jeu ne peut etre qu'une fois
-- dans la bibliotheque d'un joueur.
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

-- Avis : N-N porteuse (note + commentaire).
-- UNIQUE (game_id, user_id) = un seul avis par joueur et par jeu.
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

-- =============================================================
--  Donnees de demonstration
-- =============================================================

INSERT INTO studios (name, country, founded_year, website) VALUES
('Nova Pixel Works', 'France', 2018, 'https://example.com/nova-pixel'),
('Orbit Forge', 'Canada', 2015, 'https://example.com/orbit-forge'),
('Mistral Byte', 'Japon', 2020, 'https://example.com/mistral-byte'),
('Echo Lantern', 'Allemagne', 2012, 'https://example.com/echo-lantern');

INSERT INTO platforms (name, manufacturer, generation) VALUES
('PC', 'Multiple', 'Actuelle'),
('PlayStation 5', 'Sony', '9e génération'),
('Xbox Series X/S', 'Microsoft', '9e génération'),
('Nintendo Switch', 'Nintendo', '8e génération'),
('Mobile', 'Multiple', 'Actuelle');

INSERT INTO genres (name) VALUES
('Action'),
('Aventure'),
('Stratégie'),
('RPG'),
('Simulation'),
('Multijoueur');

-- Mots de passe de demo :
--   nora  : Ludorivya2026!
--   samir : SamirJoue2026!
--   manel : ManelTeste2026!
INSERT INTO users (username, email, password_hash) VALUES
('nora', 'nora@example.test', '$2y$10$6RX979qMXQCassrp3nXhPuXHVO.PxDvwHwjWoCHHW8UWrhCRGm7ie'),
('samir', 'samir@example.test', '$2y$10$Pr3EXuAtRdybD13yJS5IJ.4KGl6OmFOEMMXvtI7lP/9t/VJ.3PGg.'),
('manel', 'manel@example.test', '$2y$10$Par5OsLxL7IIUBjNRBLa8.QbOXe0CHp1H90f.riUMvDxFiy1tPJdC');

INSERT INTO user_profiles (user_id, bio, favorite_platform_id) VALUES
(1, 'Fan de RPG et de mondes ouverts, toujours en quête de la quête parfaite.', 1),
(2, 'Joueur compétitif, adore comparer les statistiques et grimper les classements.', 2),
(3, 'Je teste surtout les jeux indépendants et les pépites méconnues.', 4);

INSERT INTO games (studio_id, created_by, title, description, release_date, age_rating, cover_url) VALUES
(1, 1, 'Starlane Runners', 'Course futuriste en équipes où les joueurs capturent des relais lumineux dans des circuits orbitaux à la vitesse de la lumière.', '2024-03-12', 7, 'assets/img/covers/starlane-runners.jpg'),
(2, 2, 'Terraforge Odyssey', 'Aventure de survie et de craft sur des îles flottantes, avec exploration coopérative et écosystèmes à reconstruire.', '2023-11-02', 12, 'assets/img/covers/terraforge-odyssey.jpg'),
(3, 3, 'Neon Katana Tactics', 'Stratégie au tour par tour dans une mégalopole cyberpunk, centrée sur les placements, les combos et les choix moraux.', '2025-01-18', 16, 'assets/img/covers/neon-katana-tactics.jpg'),
(4, NULL, 'Harbor of Echoes', 'RPG narratif où chaque choix du joueur transforme la ville portuaire et les relations entre ses factions.', '2022-09-07', 12, 'assets/img/covers/harbor-of-echoes.jpg'),
(1, 1, 'Aurora Drift', 'Exploration spatiale contemplative : cartographiez des nébuleuses, écoutez les étoiles et ramenez vos découvertes.', '2024-10-25', 3, 'assets/img/covers/aurora-drift.jpg'),
(4, 2, 'Ironroot Kingdoms', 'Stratégie fantasy où les royaumes poussent littéralement : faites croître vos forteresses-arbres et alliez-vous aux saisons.', '2023-04-14', 12, 'assets/img/covers/ironroot-kingdoms.jpg'),
(3, 3, 'Pocket Circuit Club', 'Course arcade de bolides miniatures : customisez votre circuit dans le salon, défiez le monde entier.', '2025-06-30', 3, 'assets/img/covers/pocket-circuit-club.jpg'),
(2, 2, 'Veilbound', 'Horreur coopérative à quatre joueurs : explorez des manoirs générés, mais un seul d''entre vous voit les esprits.', '2024-08-09', 18, 'assets/img/covers/veilbound.jpg'),
(1, 1, 'Chrono Bakery', 'Gestion d''une pâtisserie hors du temps : servez des clients venus de toutes les époques sans créer de paradoxe.', '2025-03-21', 3, 'assets/img/covers/chrono-bakery.jpg'),
(3, NULL, 'Solstice Arena', 'Arène stratégique en ligne où le terrain change avec les saisons : chaque solstice rebat les cartes du classement.', '2021-05-11', 12, 'assets/img/covers/solstice-arena.jpg');

INSERT INTO game_platforms (game_id, platform_id, release_region) VALUES
(1, 1, 'Monde'), (1, 2, 'Europe'), (1, 3, 'Monde'),
(2, 1, 'Monde'), (2, 4, 'Monde'), (2, 5, 'Monde'),
(3, 1, 'Monde'), (3, 2, 'Monde'),
(4, 1, 'Monde'), (4, 4, 'Europe'),
(5, 1, 'Monde'), (5, 2, 'Monde'), (5, 3, 'Monde'),
(6, 1, 'Monde'),
(7, 4, 'Monde'), (7, 5, 'Monde'),
(8, 1, 'Monde'), (8, 2, 'Monde'), (8, 3, 'Amérique du Nord'),
(9, 1, 'Monde'), (9, 4, 'Monde'), (9, 5, 'Monde'),
(10, 1, 'Monde');

INSERT INTO game_genres (game_id, genre_id) VALUES
(1, 1), (1, 6),
(2, 2), (2, 5), (2, 6),
(3, 3), (3, 4),
(4, 2), (4, 4),
(5, 2), (5, 5),
(6, 3),
(7, 1), (7, 6),
(8, 1), (8, 2), (8, 6),
(9, 5),
(10, 3), (10, 6);

INSERT INTO library_entries (user_id, game_id, status, playtime_hours) VALUES
(1, 2, 'en_cours', 48.5),
(1, 4, 'termine', 31.0),
(1, 5, 'en_cours', 12.5),
(1, 8, 'souhaite', 0),
(2, 1, 'en_cours', 112.0),
(2, 3, 'souhaite', 0),
(2, 6, 'termine', 64.0),
(2, 10, 'en_cours', 203.5),
(3, 2, 'termine', 64.0),
(3, 7, 'en_cours', 18.0),
(3, 9, 'termine', 26.5);

INSERT INTO reviews (game_id, user_id, rating, comment) VALUES
(1, 2, 17, 'Très nerveux et lisible même à pleine vitesse, le mode équipe est excellent.'),
(1, 3, 14, 'Bon jeu de course, mais la progression est un peu lente en solo.'),
(2, 1, 18, 'Exploration superbe et vraie sensation de progression en coopération.'),
(2, 3, 15, 'Très bon jeu de survie, malgré quelques recettes de craft interminables.'),
(3, 1, 16, 'Tactique exigeante et direction artistique magnifique.'),
(4, 1, 16, 'Très bon scénario, et les choix ont des conséquences réellement visibles.'),
(4, 2, 13, 'Belle écriture mais le rythme s''essouffle dans le dernier acte.'),
(5, 3, 19, 'Une merveille contemplative, la bande-son vaut le voyage à elle seule.'),
(6, 1, 12, 'Concept original, mais l''interface de gestion manque de clarté.'),
(7, 2, 15, 'Parfait sur mobile, les circuits du salon sont une super idée.'),
(8, 1, 17, 'Le concept du seul joueur qui voit les esprits crée des moments inoubliables.'),
(8, 3, 16, 'Excellente horreur coopérative, à condition d''avoir trois amis motivés.'),
(9, 2, 14, 'Mignon et malin, idéal pour des sessions courtes.'),
(10, 3, 13, 'Le système de saisons est génial mais la communauté est rude avec les débutants.');

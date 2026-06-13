CREATE DATABASE IF NOT EXISTS ludorivya
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE ludorivya;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS reviews;
DROP TABLE IF EXISTS library_entries;
DROP TABLE IF EXISTS game_genres;
DROP TABLE IF EXISTS game_platforms;
DROP TABLE IF EXISTS oauth_accounts;
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
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE platforms (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    manufacturer VARCHAR(100) NOT NULL,
    generation VARCHAR(50) NULL
) ENGINE=InnoDB;

CREATE TABLE genres (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(60) NOT NULL UNIQUE,
    email VARCHAR(160) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Comptes OAuth prevus pour Google / Apple plus tard.
-- Pour les activer vraiment, il faudra des cles OAuth et une URL HTTPS.
CREATE TABLE oauth_accounts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    provider ENUM('google', 'apple') NOT NULL,
    provider_user_id VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_oauth_provider_user (provider, provider_user_id),
    CONSTRAINT fk_oauth_accounts_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- Relation 1-1: chaque utilisateur a au maximum un profil public.
CREATE TABLE user_profiles (
    user_id INT UNSIGNED PRIMARY KEY,
    avatar_url VARCHAR(500) NULL,
    bio TEXT NULL,
    favorite_platform VARCHAR(100) NULL,
    CONSTRAINT fk_user_profiles_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- Relation 1-N: un studio produit plusieurs jeux, chaque jeu a un studio principal.
CREATE TABLE games (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    studio_id INT UNSIGNED NOT NULL,
    slug VARCHAR(170) NOT NULL UNIQUE,
    title VARCHAR(150) NOT NULL,
    description TEXT NOT NULL,
    release_date DATE NOT NULL,
    age_rating TINYINT UNSIGNED NOT NULL,
    cover_url VARCHAR(500) NOT NULL,
    live_players INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_games_age_rating CHECK (age_rating BETWEEN 3 AND 18),
    CONSTRAINT fk_games_studio
        FOREIGN KEY (studio_id) REFERENCES studios(id)
        ON DELETE RESTRICT,
    INDEX idx_games_title (title),
    INDEX idx_games_release_date (release_date),
    INDEX idx_games_live_players (live_players)
) ENGINE=InnoDB;

-- Relation N-N: un jeu sort sur plusieurs plateformes, une plateforme a plusieurs jeux.
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
) ENGINE=InnoDB;

-- Autre relation N-N utile pour le filtrage.
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
) ENGINE=InnoDB;

-- Relation N-N utilisateur/jeu enrichie par des attributs.
CREATE TABLE library_entries (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    game_id INT UNSIGNED NOT NULL,
    status ENUM('souhaite', 'en_cours', 'termine', 'abandonne') NOT NULL DEFAULT 'souhaite',
    playtime_hours DECIMAL(6, 1) NOT NULL DEFAULT 0,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_library_user_game (user_id, game_id),
    CONSTRAINT fk_library_entries_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_library_entries_game
        FOREIGN KEY (game_id) REFERENCES games(id)
        ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE reviews (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    game_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    rating TINYINT UNSIGNED NOT NULL,
    comment TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_reviews_user_game (game_id, user_id),
    CONSTRAINT chk_reviews_rating CHECK (rating BETWEEN 0 AND 20),
    CONSTRAINT fk_reviews_game
        FOREIGN KEY (game_id) REFERENCES games(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_reviews_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO studios (name, country, founded_year, website) VALUES
('Nova Pixel Works', 'France', 2018, 'https://example.com/nova-pixel'),
('Orbit Forge', 'Canada', 2015, 'https://example.com/orbit-forge'),
('Mistral Byte', 'Japon', 2020, 'https://example.com/mistral-byte'),
('Echo Lantern', 'Allemagne', 2012, 'https://example.com/echo-lantern');

INSERT INTO platforms (name, manufacturer, generation) VALUES
('PC', 'Multiple', 'Actuelle'),
('PlayStation 5', 'Sony', '9e generation'),
('Xbox Series X/S', 'Microsoft', '9e generation'),
('Nintendo Switch', 'Nintendo', '8e generation'),
('Mobile', 'Multiple', 'Actuelle');

INSERT INTO genres (name) VALUES
('Action'),
('Aventure'),
('Strategie'),
('RPG'),
('Simulation'),
('Multijoueur');

INSERT INTO users (username, email, password_hash) VALUES
('nora', 'nora@example.test', '$2y$10$vxBVKoGUHEJDJ4pRoIf0ougUfbjNVCqE1vYHu9DaioX5gEpo9Io1K'),
('samir', 'samir@example.test', '$2y$10$vxBVKoGUHEJDJ4pRoIf0ougUfbjNVCqE1vYHu9DaioX5gEpo9Io1K'),
('manel', 'manel@example.test', '$2y$10$vxBVKoGUHEJDJ4pRoIf0ougUfbjNVCqE1vYHu9DaioX5gEpo9Io1K');

INSERT INTO user_profiles (user_id, avatar_url, bio, favorite_platform) VALUES
(1, 'https://images.unsplash.com/photo-1511512578047-dfb367046420?auto=format&fit=crop&w=300&q=80', 'Fan de RPG et de mondes ouverts.', 'PC'),
(2, 'https://images.unsplash.com/photo-1493711662062-fa541adb3fc8?auto=format&fit=crop&w=300&q=80', 'Joueur competitif, aime comparer les stats.', 'PlayStation 5'),
(3, 'https://images.unsplash.com/photo-1542751371-adc38448a05e?auto=format&fit=crop&w=300&q=80', 'Teste surtout les jeux independants.', 'Nintendo Switch');

INSERT INTO games (studio_id, slug, title, description, release_date, age_rating, cover_url, live_players) VALUES
(1, 'starlane-runners', 'Starlane Runners', 'Course futuriste en equipes ou les joueurs capturent des relais lumineux dans des circuits orbitaux.', '2024-03-12', 7, 'https://images.unsplash.com/photo-1550745165-9bc0b252726f?auto=format&fit=crop&w=900&q=80', 183420),
(2, 'terraforge-odyssey', 'Terraforge Odyssey', 'Aventure de survie et craft sur des iles flottantes avec exploration cooperative.', '2023-11-02', 12, 'https://images.unsplash.com/photo-1511512578047-dfb367046420?auto=format&fit=crop&w=900&q=80', 64210),
(3, 'neon-katana-tactics', 'Neon Katana Tactics', 'Jeu de strategie au tour par tour dans une ville cyberpunk, centre sur les placements et combos.', '2025-01-18', 16, 'https://images.unsplash.com/photo-1542751110-97427bbecf20?auto=format&fit=crop&w=900&q=80', 29880),
(4, 'harbor-of-echoes', 'Harbor of Echoes', 'RPG narratif ou les choix du joueur modifient la ville portuaire et les relations entre factions.', '2022-09-07', 12, 'https://images.unsplash.com/photo-1509198397868-475647b2a1e5?auto=format&fit=crop&w=900&q=80', 12750);

INSERT INTO game_platforms (game_id, platform_id, release_region) VALUES
(1, 1, 'Monde'), (1, 2, 'Europe'), (1, 3, 'Monde'),
(2, 1, 'Monde'), (2, 4, 'Monde'), (2, 5, 'Monde'),
(3, 1, 'Monde'), (3, 2, 'Monde'),
(4, 1, 'Monde'), (4, 4, 'Europe');

INSERT INTO game_genres (game_id, genre_id) VALUES
(1, 1), (1, 6),
(2, 2), (2, 5), (2, 6),
(3, 3), (3, 4),
(4, 2), (4, 4);

INSERT INTO library_entries (user_id, game_id, status, playtime_hours) VALUES
(1, 2, 'en_cours', 48.5),
(1, 4, 'termine', 31.0),
(2, 1, 'en_cours', 112.0),
(2, 3, 'souhaite', 0),
(3, 2, 'termine', 64.0);

INSERT INTO reviews (game_id, user_id, rating, comment) VALUES
(1, 2, 17, 'Tres dynamique, parfait pour montrer les statistiques live.'),
(2, 1, 18, 'Exploration agreable et bonne progression cooperative.'),
(2, 3, 15, 'Bon jeu mais certaines recettes de craft sont longues.'),
(4, 1, 16, 'Tres bon scenario et choix vraiment visibles.');

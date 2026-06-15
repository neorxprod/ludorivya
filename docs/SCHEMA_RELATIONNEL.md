# Schéma relationnel

## Objectif

Ludorivya est une médiathèque de jeux vidéo : jeux, studios, plateformes, genres, avis des joueurs et bibliothèques personnelles.

Le schéma complet est dans [`database/schema.sql`](../database/schema.sql) (10 tables).

## Diagramme

![Schéma relationnel](images/schema-relations.svg)

```text
studios   (1) ---- (N) games                          <- 1-N demandee
users     (1) ---- (1) user_profiles                  <- 1-1 demandee (PK partagee)
games     (N) ---- (N) platforms  via game_platforms  <- N-N demandee (PK composite)
games     (N) ---- (N) genres     via game_genres     <- 2e N-N (filtrage)
users     (N) ---- (N) games      via library_entries <- N-N porteuse (statut, heures)
users     (N) ---- (N) games      via reviews         <- N-N porteuse (note, commentaire)
users     (1) ---- (N) games      via created_by      <- qui a ajoute la fiche
platforms (1) ---- (N) user_profiles                  <- plateforme preferee (FK)
```

## Tables

### `studios`

Studios de développement.

- `id` clé primaire.
- `name` nom unique, `country`, `founded_year`, `website`.

### `games`

Les jeux du catalogue.

- `id` clé primaire.
- `studio_id` clé étrangère **NOT NULL** vers `studios` (`ON DELETE RESTRICT` : impossible de supprimer un studio qui a encore des jeux).
- `created_by` clé étrangère **NULL** vers `users` (`ON DELETE SET NULL`) : l'utilisateur qui a ajouté la fiche. Seul lui peut la modifier ou la supprimer.
- `title`, `description`, `release_date`, `age_rating` (CHECK 3–18), `cover_url` (facultatif).

**Relation 1-N** : un studio produit plusieurs jeux, chaque jeu a un studio principal.

### `users` et `user_profiles`

- `users` : identité et connexion (`username` et `email` uniques, `password_hash` bcrypt).
- `user_profiles` : présentation publique (`bio`, `favorite_platform_id`).

**Relation 1-1** : `user_profiles.user_id` est à la fois **clé primaire et clé étrangère** (PK partagée). C'est la forme la plus stricte du 1-1 : un utilisateur ne peut pas avoir deux profils.

`favorite_platform_id` est une clé étrangère vers `platforms` (`ON DELETE SET NULL`) : pas de texte libre, pas d'incohérence possible.

### `platforms` et `game_platforms`

**Relation N-N** : un jeu sort sur plusieurs plateformes, une plateforme accueille plusieurs jeux.

`game_platforms` a une **clé primaire composite** `(game_id, platform_id)` — aucun doublon possible — et porte l'attribut de liaison `release_region`.

### `genres` et `game_genres`

Deuxième relation N-N, clé primaire composite `(game_id, genre_id)`. Elle sert au **filtrage du catalogue par genre**.

### `library_entries`

La bibliothèque personnelle : relation **N-N porteuse d'attributs** entre `users` et `games` :

- `status` (souhaité, en cours, terminé, abandonné),
- `playtime_hours`,
- `added_at`.

La contrainte `UNIQUE (user_id, game_id)` garantit qu'un jeu n'apparaît qu'une fois par bibliothèque, et permet le `INSERT ... ON DUPLICATE KEY UPDATE` côté PHP (ajout ou mise à jour en une requête).

### `reviews`

Avis des joueurs : note `rating` (CHECK 0–20) + `comment`.

La contrainte `UNIQUE (game_id, user_id)` impose **un seul avis par joueur et par jeu** (modifiable via upsert).

## Choix d'intégrité

| Cas | Politique | Pourquoi |
|---|---|---|
| Supprimer un studio qui a des jeux | `RESTRICT` | on ne perd jamais un jeu par accident |
| Supprimer un jeu | `CASCADE` | ses liaisons, avis et entrées de bibliothèque partent avec |
| Supprimer un utilisateur | `CASCADE` profil/avis/bibliothèque, `SET NULL` sur `games.created_by` | ses contributions au catalogue restent |
| Supprimer une plateforme | `SET NULL` sur la plateforme préférée | le profil survit |

## Remarques techniques

- Toutes les tables : `ENGINE=InnoDB`, `utf8mb4_unicode_ci`.
- Les contraintes `CHECK` sont appliquées à partir de MySQL 8.0.16 / MariaDB 10.4 ; les bornes sont de toute façon revalidées côté PHP.
- Le script est rejouable : `CREATE DATABASE IF NOT EXISTS` + `DROP TABLE IF EXISTS` dans l'ordre des dépendances.

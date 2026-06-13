# Schema relationnel

## Objectif

Ludorivya gere des jeux video, leurs studios, plateformes, genres, avis et bibliotheques utilisateur.

## Tables

### `studios`

Contient les studios de developpement.

- `id` cle primaire.
- `name` nom unique.
- `country` pays.
- `founded_year` annee de creation.
- `website` site officiel.

### `games`

Contient les jeux.

- `id` cle primaire.
- `studio_id` cle etrangere vers `studios`.
- `slug` identifiant lisible unique.
- `title` titre.
- `description` resume.
- `release_date` date de sortie.
- `age_rating` age conseille.
- `cover_url` image.
- `live_players` valeur de demonstration.

Relation 1-N: un studio peut avoir plusieurs jeux, mais un jeu a un studio principal.

### `users` et `user_profiles`

`users` stocke les comptes de demonstration.

`user_profiles` stocke le profil public.

Relation 1-1: un utilisateur a au maximum un profil, grace a `user_profiles.user_id` qui est a la fois cle primaire et cle etrangere.

### `platforms`

Contient les plateformes de jeu.

### `game_platforms`

Table de liaison entre jeux et plateformes.

Relation N-N: un jeu peut sortir sur plusieurs plateformes, et une plateforme peut contenir plusieurs jeux.

### `genres` et `game_genres`

Deuxieme relation N-N entre jeux et genres.

### `library_entries`

Bibliotheque utilisateur.

Relation N-N enrichie entre `users` et `games`, avec des attributs:

- statut,
- temps de jeu,
- date d'ajout.

### `reviews`

Avis des joueurs sur les jeux.

Un utilisateur peut noter plusieurs jeux. Un jeu peut recevoir plusieurs avis. La contrainte `UNIQUE(game_id, user_id)` evite deux avis du meme utilisateur sur le meme jeu.

## Diagramme simplifie

```text
studios (1) ---- (N) games
games   (N) ---- (N) platforms via game_platforms
games   (N) ---- (N) genres    via game_genres
users   (1) ---- (1) user_profiles
users   (N) ---- (N) games     via library_entries
users   (N) ---- (N) games     via reviews
```

## Requetes SQL interessantes

- Catalogue avec studio, plateformes et note moyenne.
- Filtre par plateforme avec `EXISTS`.
- Statistiques de jeux par plateforme.
- Classement par note moyenne.
- Bibliotheque utilisateur avec jointure `users`, `library_entries`, `games`.


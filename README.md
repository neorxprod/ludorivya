# Ludorivya

![Bannière Ludorivya](docs/images/ludorivya-banner.svg)

**Ludorivya** est le réseau de tes jeux vidéo : une application web dynamique en **PHP 8**, **PDO** et **MySQL/MariaDB**. Explore un catalogue de **plus de 320 vrais jeux**, note-les **en un clic avec des étoiles**, construis ta bibliothèque, débats sur le **forum**… et surtout, affronte la communauté dans le **mode Versus** : deux jeux face à face, ton vote fait évoluer leur classement Elo en direct.

- **Dépôt** : <https://github.com/neorxprod/ludorivya>
- **Auteur** : neorxprod
- Projet réalisé dans le cadre d'une SAE (application web + base de données relationnelle).

## Aperçu

| Accueil | Mode Versus |
| --- | --- |
| ![Page d'accueil](docs/images/accueil.png) | ![Mode versus](docs/images/versus.png) |

| Catalogue | Classements |
| --- | --- |
| ![Catalogue](docs/images/catalogue.png) | ![Classements](docs/images/classements.png) |

| Fiche jeu | Forum |
| --- | --- |
| ![Fiche d'un jeu](docs/images/fiche-jeu.png) | ![Forum](docs/images/forum.png) |

## Fonctionnalités

**Le mode Versus (le cœur du concept) :**

- deux jeux tirés au sort s'affrontent, tu votes pour ton préféré ;
- le **score Elo** des deux jeux est recalculé par le serveur dans une transaction (formule Elo, K = 32) ;
- chaque vote rapporte **5 XP**, enchaîne les duels et fais grimper ta série ;
- anti-triche : le serveur ne valide un vote que pour la paire qu'il a lui-même servie, avec un délai minimal entre deux votes ;
- tout est animé : cartes qui claquent, deltas Elo flottants, compteurs, sans recharger la page (fetch + JSON).

**Pour tous les visiteurs :**

- catalogue de plus de 320 vrais jeux (des classiques des années 90 aux sorties récentes) : jaquettes, **note presse (metascore)**, note des joueurs, studio, genres, plateformes ;
- recherche, filtres par **genre** et **plateforme**, tri (récents / mieux notés / alphabétique), **pagination** ;
- **classements** : Elo communautaire (podium), jeux les mieux notés, joueurs les plus actifs ;
- fiche détaillée par jeu : description, bilan de duels (victoires/défaites), avis, **jeux similaires** (par genres partagés) ;
- statistiques en direct issues de la base (répartitions, totaux).

**Le forum (comme au bon vieux temps) :**

- des sujets de discussion, optionnellement rattachés à un jeu du catalogue ;
- réponses, suppression de ses propres messages, XP à la clé (+15 sujet, +5 réponse) ;
- tri par dernière activité, compteur de réponses.

**Pour les joueurs connectés :**

- inscription / connexion sécurisées, **XP et niveaux** (1 niveau tous les 250 XP) ;
- noter un jeu **directement aux étoiles** (1 à 10), commentaire facultatif (+20 XP) ;
- bibliothèque personnelle : statut (souhaité / en cours / terminé / abandonné), heures de jeu (+10 XP) ;
- ajouter un jeu au catalogue (+30 XP), modifier/supprimer **ses propres** fiches ;
- modifier son profil public (bio, plateforme préférée).

## Technologies

| Partie | Technologie |
| --- | --- |
| Interface | HTML, CSS, Bootstrap 5 (local) + design system personnalisé (effets « flamme », liquid glass, particules) |
| Interactions | JavaScript vanilla : versus en fetch/JSON, validation, animations au scroll, compteurs, cartes 3D |
| Serveur | PHP 8.x |
| Base de données | MySQL / MariaDB |
| Accès BDD | PDO (requêtes préparées natives, transactions) |
| Versionnage | Git + GitHub |

Le site fonctionne **sans connexion internet** : Bootstrap, les icônes, la police Inter et les 328 jaquettes sont stockés en local dans `public/assets/`.

> Les jaquettes et noms de jeux appartiennent à leurs éditeurs respectifs et sont utilisés ici à des fins pédagogiques uniquement (projet universitaire non commercial). Les descriptions sont rédigées originales, et les metascores sont des approximations.

## Comment le catalogue a été construit (le pipeline)

Le catalogue n'est pas saisi à la main dans le SQL : il est **généré par un pipeline reproductible**, versionné dans [`database/dataset/`](database/dataset/) :

1. **`games.json`** — le dataset source : pour chaque jeu, le titre réel, le studio (et son pays/année), la date de sortie, le PEGI, 1-3 genres, les plateformes, un metascore approximatif, une **description française originale** et l'**appid Steam** du jeu.
2. **`fetch_covers.ps1`** — télécharge une seule fois les jaquettes officielles (600×900) depuis le **CDN public de Steam** (`cdn.cloudflare.steamstatic.com/steam/apps/<appid>/library_600x900.jpg`) vers `public/assets/img/covers/`. Le téléchargement sert aussi de **validation** : un appid faux ne renvoie pas d'image, et le jeu est écarté.
3. **`build_seed.php`** — génère `database/schema.sql` complet : les 13 tables, les 328 jeux avec leurs liaisons genres/plateformes, les comptes de démonstration, les avis, les sujets de forum, et une **simulation de 150 duels Elo** (graine aléatoire fixe → résultat reproductible, le favori au metascore gagne 70 % du temps) pour que le classement Versus soit crédible dès l'import.

Pour régénérer la base de zéro :

```bat
cd database\dataset
powershell -File fetch_covers.ps1
php build_seed.php
```

L'application, elle, ne dépend **d'aucune API externe** : tout est servi en local depuis MySQL.

## Relations SQL demandées

Le sujet demande au minimum une relation 1-1, une relation 1-N et une relation N-N — toutes sont **réellement utilisées par l'application** (lecture ET écriture) :

| Type | Tables | Utilisation dans le site |
| --- | --- | --- |
| **1-1** | `users` / `user_profiles` (PK partagée) | création à l'inscription, modification depuis « Mon profil » |
| **1-N** | `studios` → `games` | affichée sur chaque fiche, choisie à l'ajout d'un jeu |
| **N-N** | `games` ↔ `platforms` via `game_platforms` (PK composite) | filtres du catalogue, fiches, statistiques |

Le schéma va plus loin :

- `games` ↔ `genres` (2e N-N, filtre par genre + jeux similaires) ;
- `users` ↔ `games` via `library_entries` (N-N **porteuse** : statut, heures) ;
- `users` ↔ `games` via `reviews` (un avis par joueur et par jeu, garanti par `UNIQUE`) ;
- `users` ↔ `games` via `duels` (N-N porteuse **orientée** : vainqueur/perdant, alimente le classement Elo) ;
- `users` → `games` via `created_by` (seul l'auteur d'une fiche peut la modifier/supprimer).

![Schéma relationnel](docs/images/schema-relations.svg)

Détails table par table : [docs/SCHEMA_RELATIONNEL.md](docs/SCHEMA_RELATIONNEL.md)

## Installation avec XAMPP

1. **Démarrer** Apache et MySQL dans le panneau XAMPP.

2. **Importer la base** (au choix) :

   - *Avec phpMyAdmin* : ouvrir `http://localhost/phpmyadmin` → onglet **Importer** → choisir `database/schema.sql` → Exécuter.
   - *En ligne de commande* (depuis le dossier du projet) :

   ```bat
   C:\xampp\mysql\bin\mysql.exe -u root --default-character-set=utf8mb4 < database\schema.sql
   ```

3. **Relier le projet à Apache** (lien symbolique, terminal administrateur) :

   ```bat
   mklink /J C:\xampp\htdocs\ludorivya "C:\chemin\vers\LUDORIVYA"
   ```

   (ou copier simplement le dossier du projet dans `C:\xampp\htdocs\`).

4. **Ouvrir le site** : <http://localhost/ludorivya/public/>

5. *(Facultatif)* Si tes identifiants MySQL ne sont pas ceux par défaut de XAMPP (`root` sans mot de passe), copie `config/database.example.php` vers `config/database.php` et adapte-le. Ce fichier est ignoré par Git.

### Compte de démonstration

```text
Email : nora@example.test
Mot de passe : Ludorivya2026!
```

(deux autres comptes existent : samir / SamirJoue2026! et manel / ManelTeste2026!)

## Structure du projet

```text
LUDORIVYA/
  .github/workflows/php.yml   <- CI : vérification de syntaxe PHP à chaque push
  config/
    database.example.php      <- modèle de configuration (le vrai est gitignoré)
  database/
    schema.sql                <- création de la base + 328 vrais jeux + 150 duels simulés
    dataset/                  <- le pipeline du catalogue (games.json + scripts)
  docs/
    SCHEMA_RELATIONNEL.md     <- le schéma expliqué table par table
    BONUS_NOSQL.md            <- bonus : limites du relationnel + MongoDB
    PLAN_ACTION.md
    images/                   <- captures, bannière, diagramme
  public/                     <- racine web
    index.php                 <- accueil
    games.php                 <- catalogue (recherche, filtres, tri, pagination)
    game.php                  <- fiche d'un jeu + avis + bibliothèque + jeux similaires
    versus.php / duel_store.php  <- mode versus (votes Elo en fetch/JSON)
    rankings.php              <- classements (Elo, notes, joueurs)
    forum.php / topic.php     <- forum (sujets + reponses)
    topic_store.php / reply_store.php / topic_delete.php / reply_delete.php
    create.php / edit.php     <- formulaires d'ajout / modification d'un jeu
    game_store.php / game_update.php / game_delete.php
    review_store.php / review_delete.php
    library_store.php / library_delete.php
    login.php / login_store.php / logout.php
    register.php / register_store.php
    profile.php / profile_update.php
    users.php / stats.php
    assets/                   <- CSS, JS, police, jaquettes, Bootstrap local
  src/
    bootstrap.php             <- session, CSRF, connexion PDO
    Database.php              <- connexion PDO centralisée
    functions.php             <- helpers (validation, sécurité, XP/niveaux, rendu)
```

Convention : chaque formulaire a sa page d'affichage (`page.php`) et son traitement POST séparé (`*_store.php`, `*_update.php`, `*_delete.php`).

## Sécurité appliquée

- **Requêtes préparées PDO partout** (`ATTR_EMULATE_PREPARES = false`) — aucune concaténation de données utilisateur dans le SQL.
- **Jeton CSRF** vérifié (`hash_equals`) sur tous les POST, y compris les votes du versus et la déconnexion.
- **Serveur autoritaire sur le versus** : la paire de jeux est choisie et mémorisée côté serveur, le calcul Elo et l'XP sont faits côté serveur, délai minimal entre deux votes.
- Mots de passe : `password_hash()` / `password_verify()`, **anti force brute** (pause après 5 échecs).
- Sessions durcies : cookie `HttpOnly` + `SameSite=Lax`, `session_regenerate_id()` après connexion/inscription.
- Échappement HTML systématique (`e()`), validation serveur de toutes les entrées, autorisations vérifiées côté serveur.
- Redirections restreintes aux pages internes (anti open redirect), erreurs techniques journalisées jamais affichées.

## Vérification automatique

`.github/workflows/php.yml` vérifie la syntaxe de tous les fichiers PHP à chaque push sur GitHub.

## Bonus NoSQL

[docs/BONUS_NOSQL.md](docs/BONUS_NOSQL.md) décrit un scénario de montée en charge (millions de jeux, milliards d'avis et de votes versus), identifie les requêtes du projet qui deviendraient coûteuses, et propose une alternative conceptuelle avec MongoDB, sans implémentation.

## État du projet

- [x] Base relationnelle 13 tables (1-1, 1-N, 2× N-N, 3× N-N porteuses dont les duels, forum).
- [x] Forum : sujets liés aux jeux, réponses, modération de ses messages.
- [x] Notes aux étoiles en un clic, commentaire facultatif.
- [x] Catalogue de 328 vrais jeux, rétro inclus (jaquettes locales, metascore, descriptions originales).
- [x] Mode Versus : votes Elo en direct, XP, séries, anti-triche serveur.
- [x] Classements : Elo (podium), notes, joueurs.
- [x] CRUD complet : jeux, avis, bibliothèque, profil.
- [x] Recherche, filtres par genre et plateforme, tri, pagination.
- [x] Authentification sécurisée + CSRF partout.
- [x] Design « gaming premium » : effets flamme, particules, cartes 3D, fonctionne hors ligne.
- [x] Documentation (README, schéma, bonus NoSQL) + CI.

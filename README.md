# Ludorivya

Ludorivya est une application web dynamique de gestion de jeux video, realisee pour une SAE PHP/PDO/MySQL. Le site permet de consulter un catalogue de jeux, filtrer par plateforme, afficher des fiches detaillees, ajouter des jeux, publier des avis, consulter les joueurs et visualiser des statistiques SQL.

Nom du depot GitHub recommande: `ludorivya`

## Technologies

- PHP 8.x
- PDO pour l'acces a la base de donnees
- MySQL / MariaDB
- JavaScript pour validation et interactions
- Bootstrap 5 pour l'interface
- Git pour le versionnage

## Fonctionnalites

- Catalogue de jeux avec recherche.
- Filtre par plateforme.
- Fiche detaillee d'un jeu.
- Ajout d'un nouveau jeu.
- Ajout ou mise a jour d'un avis joueur.
- Page plateformes avec nombre de jeux lies.
- Page joueurs montrant profils et bibliotheques.
- Page statistiques basee sur des jointures SQL.

## Relations SQL obligatoires

Le projet respecte les trois types de relations demandes:

- Relation 1-1: `users` vers `user_profiles`.
- Relation 1-N: `studios` vers `games`.
- Relation N-N: `games` vers `platforms` via `game_platforms`.

Le projet contient aussi:

- N-N `games` vers `genres` via `game_genres`.
- N-N enrichie `users` vers `games` via `library_entries`.
- Avis `reviews` entre utilisateurs et jeux.

## Installation avec XAMPP

1. Placer ou garder le projet dans:

```text
C:\Users\rosyp\Documents\LUDORIVYA
```

2. Verifier PHP et MariaDB:

```powershell
C:\xampp\php\php.exe -v
C:\xampp\mysql\bin\mysql.exe --version
```

3. Creer la base de donnees et importer les donnees:

```powershell
C:\xampp\mysql\bin\mysql.exe -u root < database\schema.sql
```

4. Verifier la configuration locale:

```text
config/database.php
```

Par defaut, le projet utilise:

- host: `127.0.0.1`
- port: `3306`
- database: `ludorivya`
- user: `root`
- password: vide

5. Lancer le serveur local depuis le dossier du projet:

```powershell
C:\xampp\php\php.exe -S localhost:8080 -t public
```

6. Ouvrir:

```text
http://localhost:8080
```

## Structure du projet

```text
LUDORIVYA/
  config/
    database.example.php
    database.php
  database/
    schema.sql
  docs/
    BONUS_NOSQL.md
    COMMITS.md
    SCHEMA_RELATIONNEL.md
  public/
    assets/
      css/styles.css
      js/app.js
    index.php
    game.php
    create.php
    store_game.php
    review_store.php
    platforms.php
    users.php
    stats.php
  src/
    bootstrap.php
    Database.php
    functions.php
  README.md
```

## Securite

- Les requetes SQL qui utilisent des entrees utilisateur passent par des requetes preparees PDO.
- Les sorties HTML sont echappees avec la fonction `e()`.
- Les formulaires sont valides cote JavaScript et cote serveur.
- Le fichier `config/database.php` est ignore par Git.

## Limites et scalabilite

Le bonus NoSQL est documente dans:

```text
docs/BONUS_NOSQL.md
```

Il explique pourquoi certaines requetes relationnelles deviennent couteuses quand le catalogue grandit fortement, puis propose une alternative conceptuelle avec MongoDB.

## Auteurs

Projet a realiser en binome ou trinome selon les consignes de la SAE.


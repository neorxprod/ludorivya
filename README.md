# Ludorivya

![Banniere Ludorivya](docs/images/ludorivya-banner.svg)

Ludorivya est une application web dynamique en **PHP 8**, **PDO** et **MySQL/MariaDB** pour gerer un catalogue de jeux video. Le projet est realise dans le cadre d'une SAE demandant une base relationnelle, une interface interactive, du JavaScript, Bootstrap et un depot Git avec des commits reguliers.

## Apercu du projet

![Schema visuel du projet](docs/images/schema-relations.svg)

Le site permet de:

- consulter un catalogue de jeux video;
- rechercher un jeu par titre, description ou studio;
- filtrer les jeux par plateforme;
- voir une fiche detaillee avec studio, plateformes, genres et avis;
- creer un compte local;
- se connecter et se deconnecter;
- ajouter un jeu quand on est connecte;
- publier un avis quand on est connecte;
- consulter les joueurs et leurs bibliotheques;
- afficher des statistiques basees sur des jointures SQL.

## Technologies utilisees

| Partie | Technologie |
| --- | --- |
| Interface | HTML, CSS, Bootstrap 5 |
| Interactions | JavaScript |
| Serveur | PHP 8.x |
| Base de donnees | MySQL / MariaDB |
| Acces BDD | PDO |
| Versionnage | Git + GitHub |

## Relations SQL demandees

Le sujet demande au minimum une relation 1-1, une relation 1-N et une relation N-N.

| Type | Tables | Explication |
| --- | --- | --- |
| 1-1 | `users` / `user_profiles` | chaque utilisateur a un seul profil public |
| 1-N | `studios` / `games` | un studio peut creer plusieurs jeux |
| N-N | `games` / `platforms` | un jeu peut sortir sur plusieurs plateformes |

Le projet ajoute aussi:

- `games` / `genres` en N-N;
- `users` / `games` via `library_entries`;
- `reviews` pour les avis des joueurs.

## Installation avec XAMPP

1. Demarrer **Apache** et **MySQL** dans le panneau XAMPP.

2. Importer la base:

```powershell
cd "C:\chemin\vers\LUDORIVYA"
Get-Content database\schema.sql -Raw | C:\xampp\mysql\bin\mysql.exe -u root
```

3. Le projet est relie a XAMPP avec:

```text
C:\xampp\htdocs\ludorivya -> C:\chemin\vers\LUDORIVYA
```

4. Ouvrir le site:

```text
http://localhost/ludorivya/public/
```

## Compte de test

```text
Email: nora@example.test
Mot de passe: Ludorivya2026!
```

Les boutons Google et Apple sont presents dans l'interface, mais ils sont en mode preparation. Pour les activer vraiment, il faudra creer des cles OAuth, utiliser HTTPS et brancher les callbacks.

## Structure du projet

```text
LUDORIVYA/
  .github/
    workflows/php.yml
  config/
    database.example.php
  database/
    schema.sql
  docs/
    BONUS_NOSQL.md
    COMMITS.md
    PLAN_ACTION.md
    SCHEMA_RELATIONNEL.md
    images/
  public/
    assets/
      css/styles.css
      js/app.js
    index.php
    login.php
    register.php
    profile.php
    game.php
    create.php
    stats.php
  src/
    bootstrap.php
    Database.php
    functions.php
  README.md
```

## Securite appliquee

- Requetes preparees PDO pour les donnees venant des formulaires.
- Echappement HTML avec la fonction `e()`.
- Mots de passe stockes avec `password_hash`.
- Verification avec `password_verify`.
- Sessions PHP avec regeneration de session apres connexion.
- Fichier `config/database.php` ignore par Git.
- Validation cote JavaScript et cote serveur.

## Verification automatique

Le projet contient une action GitHub dans:

```text
.github/workflows/php.yml
```

Elle verifie la syntaxe de tous les fichiers PHP a chaque push sur GitHub.

## Bonus NoSQL

Le fichier [docs/BONUS_NOSQL.md](docs/BONUS_NOSQL.md) explique pourquoi certaines requetes relationnelles deviennent couteuses avec beaucoup de jeux, beaucoup d'avis et beaucoup de statistiques live. Il propose une alternative conceptuelle avec MongoDB, sans implementation.

## Etat du projet

- [x] Base SQL relationnelle.
- [x] Donnees de demonstration.
- [x] Pages principales.
- [x] Connexion locale.
- [x] Inscription locale.
- [x] Interface Bootstrap.
- [x] JavaScript de validation.
- [x] Documentation SAE.
- [ ] Depot GitHub public.
- [ ] Vraies captures d'ecran finales dans le README.
- [ ] OAuth Google/Apple reel.

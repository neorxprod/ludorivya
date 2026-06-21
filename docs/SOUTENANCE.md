# Soutenance orale — Ludorivya (déroulé + script + Q&R)

> Objectif : présenter le projet en ~10 min + démo, puis répondre aux questions. Tout ce qu'il faut dire est ici. Adapte la longueur au temps imposé.

## 0. Avant de commencer (checklist 2 min avant)

- [ ] XAMPP : **Apache** et **MySQL** démarrés (verts).
- [ ] Base importée (sinon : phpMyAdmin → Importer → `database/schema.sql`).
- [ ] Onglet navigateur ouvert sur **http://localhost/ludorivya/public/**.
- [ ] Connecté avec le compte démo `nora@example.test` / `Ludorivya2026!`.
- [ ] Onglet GitHub ouvert sur le dépôt (pour montrer commits + README).
- [ ] phpMyAdmin ouvert sur la base `ludorivya` (pour montrer le schéma si demandé).

## 1. Accroche (30 s)

> « Ludorivya, c'est le réseau social de nos jeux vidéo. On a voulu dépasser le simple catalogue : un vrai catalogue de **328 jeux réels**, mais surtout un **mode Versus** où la communauté fait s'affronter les jeux deux par deux, et leur classement évolue en direct. Techniquement c'est du **PHP 8 + PDO + MySQL**, avec une base relationnelle de **13 tables**. »

## 2. Plan annoncé (15 s)

> « Je vais vous montrer : 1) la base de données et ses relations, 2) une démo de l'appli, 3) comment le PHP/PDO interroge la base en sécurité, 4) le bonus sur la scalabilité. »

## 3. La base de données (2-3 min) — *critère 25% + 20%*

Montre le schéma (Mermaid sur GitHub, ou phpMyAdmin → onglet « Concepteur »).

Points à dire **obligatoirement** (le jury coche les relations demandées) :

- **Relation 1-1** : `users` ↔ `user_profiles`. *« Le profil partage la clé primaire de l'utilisateur : `user_profiles.user_id` est à la fois clé primaire ET clé étrangère. C'est la forme la plus stricte du 1-1 : impossible d'avoir deux profils. »*
- **Relation 1-N** : `studios` → `games`. *« Un studio a plusieurs jeux, un jeu a un seul studio. La clé étrangère `studio_id` est dans `games`, avec `ON DELETE RESTRICT` : on ne peut pas supprimer un studio qui a encore des jeux. »*
- **Relation N-N** : `games` ↔ `platforms` via `game_platforms`. *« Un jeu sort sur plusieurs plateformes et inversement. On le traduit par une table de liaison avec une clé primaire composite `(game_id, platform_id)` : aucun doublon possible. »*
- **Et on va plus loin** : trois **N-N porteuses** (`reviews`, `library_entries`, `duels`) — *« la table de liaison porte des attributs : la note pour `reviews`, le statut et les heures pour `library_entries`, le vainqueur/perdant pour `duels`. »*
- **Normalisation 3FN** : *« pas de redondance, chaque attribut dépend de la clé entière ; la plateforme préférée est une clé étrangère, pas du texte libre. »*

## 4. Démo de l'appli (3-4 min) — clique dans cet ordre

1. **Accueil** → *« interface en PHP, design fait maison par-dessus Bootstrap. »*
2. **Jeux** (catalogue) → tape une recherche, change un **filtre genre/plateforme**, change de **page**. *« Recherche, filtres et pagination, tout en SQL avec des requêtes préparées. »*
3. **Fiche d'un jeu** → montre la note aux **étoiles**, les avis, les **jeux similaires**. *« Les jeux similaires, c'est une requête qui compte les genres partagés. »*
4. **Note un jeu aux étoiles** → *« un clic, le serveur enregistre, +20 XP. »*
5. **Versus** → vote sur 2-3 duels. *« Le serveur choisit la paire, calcule l'Elo dans une transaction, et renvoie le résultat sans recharger la page. »*
6. **Classements** → *« le podium Elo bouge grâce aux votes. »*
7. **Forum** → ouvre un sujet, montre une réponse.
8. *(optionnel)* **phpMyAdmin** → montre une ligne ajoutée dans `reviews` ou `duels` après ton action en direct. **Effet garanti** sur le jury.

## 5. Le code PHP / PDO (2 min) — *critère 25%*

Ouvre **`public/duel_store.php`** (le plus impressionnant) et explique :

> « Quand on vote, le serveur ne fait **jamais confiance au client** :
> 1. il vérifie le **jeton CSRF** ;
> 2. il vérifie que la paire votée est bien **celle qu'il a servie** (mémorisée en session) — c'est l'anti-triche ;
> 3. il recalcule l'**Elo** lui-même ;
> 4. il met à jour les deux scores + insère le duel + ajoute l'XP dans **une seule transaction** : tout réussit ou tout est annulé. »

Montre une **requête préparée** type (n'importe quel `prepare(...)->execute([...])`) :

> « Toutes nos requêtes sont préparées : la requête et les données voyagent séparément, donc l'**injection SQL est impossible**. On a même désactivé l'émulation (`EMULATE_PREPARES = false`) pour de vraies requêtes préparées côté serveur. »

## 6. Bonus scalabilité (1 min) — *bonus +5%*

> « On a réfléchi aux limites : avec des millions de jeux et des milliards de votes, les `UPDATE` du score Elo créeraient des "lignes chaudes" et les agrégats deviendraient coûteux. La piste, c'est une base **NoSQL type MongoDB** en complément : des documents dénormalisés pour la recherche et des compteurs précalculés pour les classements. Tout est détaillé dans `docs/BONUS_NOSQL.md`. »

## 7. Conclusion (20 s)

> « En résumé : une base relationnelle complète et normalisée, du PHP/PDO sécurisé, un concept original avec le Versus, et une vraie réflexion sur la scalabilité. Merci, on répond à vos questions. »

---

## 🎤 Banque de questions / réponses

**« Pourquoi PDO et pas mysqli ? »**
> API objet propre, requêtes préparées avec paramètres nommés, et c'est ce que demande le sujet. PDO permet aussi de changer de SGBD plus facilement.

**« Comment empêchez-vous l'injection SQL ? »**
> Toutes les requêtes sont préparées : les valeurs ne sont jamais collées dans le texte SQL, elles passent par des paramètres. Et `EMULATE_PREPARES = false` force de vraies requêtes préparées côté MySQL.

**« Comment empêchez-vous la triche dans le Versus ? »**
> Le serveur est autoritaire : il choisit la paire de jeux, la mémorise en session, et n'accepte un vote que pour cette paire. Il recalcule l'Elo lui-même, dans une transaction, avec un délai minimal entre deux votes.

**« Qu'est-ce qu'une relation N-N "porteuse" ? »**
> Une table de liaison qui, en plus des deux clés étrangères, porte ses propres attributs. Exemple : `library_entries` relie un user et un jeu, et stocke en plus le statut et les heures de jeu.

**« Pourquoi le niveau n'est-il pas une colonne ? »**
> Le niveau se déduit de l'XP (1 niveau tous les 250 XP). Le stocker créerait une donnée redondante à maintenir — risque d'incohérence. On le calcule en PHP.

**« Que se passe-t-il si deux personnes votent en même temps ? »**
> Chaque vote est une transaction ; MySQL (InnoDB) verrouille les lignes concernées, donc les mises à jour de l'Elo sont sérialisées, pas de perte.

**« Votre CHECK (rating BETWEEN 0 AND 20) marche partout ? »**
> Les CHECK sont appliqués à partir de MySQL 8.0.16 / MariaDB 10.4. Par sécurité, on revalide aussi toutes les bornes côté PHP.

**« Comment garantissez-vous un seul avis par joueur et par jeu ? »**
> Une contrainte `UNIQUE (game_id, user_id)` sur `reviews`, et un `INSERT ... ON DUPLICATE KEY UPDATE` côté PHP : ça crée l'avis ou met à jour l'existant.

**« Et si on supprime un utilisateur ? »**
> `ON DELETE CASCADE` sur son profil, ses avis, sa bibliothèque ; mais `ON DELETE SET NULL` sur les jeux qu'il a ajoutés et sur ses duels, pour ne pas perdre les données du catalogue et les statistiques.

**« D'où viennent les 328 jeux ? »**
> D'un pipeline reproductible (dossier `database/dataset/`) : un fichier JSON décrit les jeux, un script télécharge les jaquettes officielles, un autre génère le `schema.sql`. Détaillé dans le README.

**« Comment avez-vous géré le travail à plusieurs / le versionnage ? »**
> Git avec une branche par fonctionnalité fusionnée dans `main`, des commits réguliers avec messages explicites, et une CI GitHub qui vérifie la syntaxe PHP à chaque push.

**« Pourquoi avoir choisi les jeux vidéo ? »**
> Thème riche en relations (studios, plateformes, genres, joueurs) — parfait pour démontrer 1-1, 1-N et N-N — et motivant pour imaginer des fonctionnalités originales comme le Versus.

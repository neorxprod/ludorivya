# Script de la vidéo de présentation — Ludorivya

> Cible : 3 à 4 minutes. Format : capture d'écran de l'appli + voix off. Le texte en *italique* = ce que tu dis ; les **[crochets]** = ce que tu montres à l'écran.

## Matériel

- Logiciel de capture : **OBS Studio** (gratuit) ou l'enregistreur Windows (`Win + G`).
- Résolution 1920×1080, navigateur en plein écran sur `http://localhost/ludorivya/public/`.
- Écris ton texte et lis-le calmement — ou enregistre la voix après coup sur la vidéo.

---

## Séquence 1 — Intro (0:00 → 0:25)

**[Page d'accueil, on scrolle doucement de haut en bas]**

> *« Voici Ludorivya, le réseau social de nos jeux vidéo. C'est une application web développée en PHP 8, PDO et MySQL, dans le cadre de la SAE. Plus qu'un catalogue : un vrai réseau autour de 328 jeux réels, avec avis, classements et un mode de duel entre jeux. »*

## Séquence 2 — Le catalogue (0:25 → 1:00)

**[Onglet « Jeux » → taper une recherche → changer un filtre genre → changer de page]**

> *« Le catalogue propose plus de 300 jeux, des classiques rétro aux sorties récentes. On peut chercher par titre ou studio, filtrer par genre et par plateforme, trier, et naviguer page par page. Chaque résultat est une requête SQL préparée avec des jointures. »*

**[Cliquer sur une fiche de jeu]**

> *« Sur la fiche, on retrouve le studio, les plateformes, les genres, les avis des joueurs, et des jeux similaires calculés à partir des genres en commun. »*

## Séquence 3 — Noter et contribuer (1:00 → 1:30)

**[Mettre une note en cliquant sur les étoiles → publier]**

> *« On note un jeu en un clic, avec les étoiles. Le commentaire est facultatif. Tout est enregistré côté serveur, et ça rapporte de l'expérience. »*

**[Aller sur « Mon profil » pour montrer l'XP / le niveau]**

> *« Chaque action — noter, voter, poster — fait gagner de l'XP et monter de niveau. »*

## Séquence 4 — Le mode Versus, le cœur du projet (1:30 → 2:30)

**[Onglet « Versus » → voter sur 3-4 duels d'affilée]**

> *« Voici notre fonctionnalité phare : le Versus. Deux jeux s'affrontent, on vote pour son préféré. Le serveur recalcule leur score Elo — le système de classement des échecs — et l'affiche en direct, sans recharger la page. »*

**[Montrer les confettis / le score qui change / la série]**

> *« Tout est animé, et surtout tout est sécurisé : c'est le serveur qui choisit les jeux et calcule le résultat, le joueur ne peut pas tricher. Et si on ne connaît pas un jeu, un bouton permet de passer sans fausser le classement. »*

**[Onglet « Classements »]**

> *« Les votes alimentent ce classement Elo de la communauté, en plus du classement par notes et du classement des joueurs. »*

## Séquence 5 — Le forum (2:30 → 2:50)

**[Onglet « Forum » → ouvrir un sujet]**

> *« Un forum permet d'ouvrir des discussions, reliées ou non à un jeu, et d'y répondre. »*

## Séquence 6 — La technique (2:50 → 3:30)

**[Montrer phpMyAdmin avec le schéma, OU le schéma du README]**

> *« Côté base, 13 tables avec toutes les relations demandées : du un-à-un, du un-à-plusieurs, et plusieurs relations plusieurs-à-plusieurs. »*

**[Montrer le code de `duel_store.php` dans l'éditeur]**

> *« Côté code, toutes les requêtes sont préparées pour empêcher les injections SQL, les écritures sont protégées par jeton anti-CSRF, et les opérations sensibles comme le vote se font dans une transaction. »*

**[Montrer le dépôt GitHub : commits + README]**

> *« Le projet est versionné sur Git avec des commits réguliers, et documenté par un README complet et un guide technique. »*

## Séquence 7 — Conclusion (3:30 → 3:50)

**[Revenir sur l'accueil]**

> *« Ludorivya, c'est une base relationnelle complète, du PHP sécurisé, un concept original, et une réflexion sur la scalabilité détaillée dans notre bonus NoSQL. Merci d'avoir regardé. »*

---

## Conseils

- Enregistre chaque séquence séparément, c'est plus simple à refaire si tu te trompes.
- Parle lentement, l'écran a le temps de suivre.
- Coupe les temps de chargement au montage.
- Mets une musique de fond discrète et libre de droits (volume bas).

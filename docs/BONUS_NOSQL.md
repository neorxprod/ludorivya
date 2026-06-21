# Bonus NoSQL : limites du relationnel

## Scénario de forte montée en charge

Imaginons que Ludorivya devienne une grande plateforme publique :

- 3 millions de jeux et d'éditions régionales.
- 200 millions d'utilisateurs.
- 10 milliards d'avis.
- 50 milliards d'entrées de bibliothèque.
- Des **millions de votes versus par heure** aux heures de pointe.
- Filtres combinés : plateforme, genre, studio, note, année, popularité.

## Pourquoi le relationnel devient limitant

Le modèle relationnel reste fiable, mais certaines opérations deviennent coûteuses :

- Beaucoup de jointures entre `games`, `platforms`, `genres`, `reviews`, `library_entries`.
- Agrégations fréquentes : note moyenne par jeu, tops, totaux par joueur.
- Recherche plein texte sur des millions de titres, descriptions et avis (`LIKE '%...%'` ne profite d'aucun index et balaye la table).
- Pagination : le `SELECT COUNT(*)` du catalogue devient lui-même une requête lourde sur des millions de lignes filtrées.
- Données hétérogènes selon les plateformes (un jeu mobile et un jeu console n'ont pas les mêmes métadonnées).

Exemples concrets tirés de ce projet, problématiques à grande échelle :

- La requête du catalogue (`games.php`) : deux sous-requêtes agrégées (note moyenne, genres concaténés) **par ligne affichée** + deux `EXISTS` de filtrage sur les tables de liaison.
- Les classements (`rankings.php`) : `AVG` + `GROUP BY` + `HAVING` sur la totalité de la table `reviews`, plus un tri du catalogue par score Elo, à chaque visite.
- La recherche (`LIKE` sur titre, description et nom de studio joints) sur des millions de jeux.
- Le mode versus (`duel_store.php`) : chaque vote fait un `UPDATE games SET elo = ...` : à des millions de votes/heure, les jeux populaires deviennent des **lignes chaudes** (contention de verrous sur les mêmes enregistrements), et la table `duels` grossit sans fin.

## Alternative NoSQL conceptuelle (sans implémentation)

Une solution réaliste serait d'utiliser **MongoDB en complément**, sans remplacer toute la base relationnelle.

### Ce qui resterait en SQL

- Comptes utilisateurs et authentification.
- Le catalogue canonique des jeux (source de vérité).
- Tout ce qui est transactionnel et doit rester cohérent.

### Ce qui passerait en NoSQL

- Des **documents de recherche dénormalisés** : un document par jeu, qui embarque déjà studio, plateformes, genres et note moyenne : le catalogue se lit alors **sans aucune jointure**.
- Des **snapshots de statistiques** précalculés (tops, compteurs) régénérés périodiquement.
- Les avis récents, dénormalisés avec le pseudo de l'auteur.
- Les **votes versus** dans un journal append-only (ou des compteurs distribués) : on absorbe les pics d'écriture, et le classement Elo est recalculé par lots toutes les quelques secondes au lieu d'un UPDATE par vote.

Exemple de document MongoDB pour la recherche :

```json
{
  "gameId": 12,
  "title": "Starlane Runners",
  "studio": "Nova Pixel Works",
  "platforms": ["PC", "PlayStation 5", "Xbox Series X/S"],
  "genres": ["Action", "Multijoueur"],
  "averageRating": 15.5,
  "reviewCount": 2,
  "searchText": "course futuriste equipes relais lumineux circuits orbitaux"
}
```

La note moyenne n'est plus calculée à la lecture : elle est mise à jour dans le document à chaque nouvel avis (écriture un peu plus chère, lectures beaucoup plus rapides : le bon compromis quand on lit 1000 fois plus qu'on n'écrit).

### Pourquoi MongoDB plutôt qu'une autre techno NoSQL ?

Chaque problème de scalabilité a son outil idéal ; on retient MongoDB comme **compromis pédagogique** parce qu'il couvre le cas le plus large avec un seul modèle (le document) :

| Besoin | Outil le plus adapté | Pourquoi |
| --- | --- | --- |
| Catalogue dénormalisé, lectures sans jointure | **MongoDB** (documents) | un document = un jeu complet, lu en une fois |
| Recherche plein texte sur des millions d'avis | **Elasticsearch** | index inversé, pertinence et tolérance aux fautes que `LIKE` ne sait pas faire |
| Compteurs « chauds » du versus (Elo, votes/s) | **Redis** | compteurs atomiques en mémoire, encaisse les pics d'écriture sans verrou de ligne |

Dans un vrai déploiement on combinerait les trois. Pour cette SAE, **MongoDB** est retenu car il illustre le mieux le passage du relationnel au document (dénormalisation) ; Elasticsearch et Redis sont cités comme compléments pour la recherche et les compteurs.

## Conclusion

Le relationnel est excellent pour garantir la cohérence des données et démontrer les relations de la SAE (1-1, 1-N, N-N). Pour passer à très grande échelle, on ajoute une base NoSQL spécialisée pour la recherche et les statistiques dénormalisées, tout en gardant SQL comme source de vérité.

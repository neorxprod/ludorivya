# Bonus NoSQL — limites du relationnel

## Scénario de forte montée en charge

Imaginons que Ludorivya devienne une grande plateforme publique :

- 3 millions de jeux et d'éditions régionales.
- 200 millions d'utilisateurs.
- 10 milliards d'avis.
- 50 milliards d'entrées de bibliothèque.
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
- Le top 5 (`stats.php`) : `AVG` + `GROUP BY` + `HAVING` sur la totalité de la table `reviews` à chaque visite.
- La recherche (`LIKE` sur titre, description et nom de studio joints) sur des millions de jeux.

## Alternative NoSQL conceptuelle (sans implémentation)

Une solution réaliste serait d'utiliser **MongoDB en complément**, sans remplacer toute la base relationnelle.

### Ce qui resterait en SQL

- Comptes utilisateurs et authentification.
- Le catalogue canonique des jeux (source de vérité).
- Tout ce qui est transactionnel et doit rester cohérent.

### Ce qui passerait en NoSQL

- Des **documents de recherche dénormalisés** : un document par jeu, qui embarque déjà studio, plateformes, genres et note moyenne — le catalogue se lit alors **sans aucune jointure**.
- Des **snapshots de statistiques** précalculés (tops, compteurs) régénérés périodiquement.
- Les avis récents, dénormalisés avec le pseudo de l'auteur.

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

La note moyenne n'est plus calculée à la lecture : elle est mise à jour dans le document à chaque nouvel avis (écriture un peu plus chère, lectures beaucoup plus rapides — le bon compromis quand on lit 1000 fois plus qu'on n'écrit).

## Conclusion

Le relationnel est excellent pour garantir la cohérence des données et démontrer les relations de la SAE (1-1, 1-N, N-N). Pour passer à très grande échelle, on ajoute une base NoSQL spécialisée pour la recherche et les statistiques dénormalisées, tout en gardant SQL comme source de vérité.

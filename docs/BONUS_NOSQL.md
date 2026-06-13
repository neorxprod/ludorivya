# Bonus NoSQL - limites du relationnel

## Scenario de forte montee en charge

Imaginons que Ludorivya devienne une grande plateforme publique:

- 3 millions de jeux et versions regionales.
- 200 millions d'utilisateurs.
- 10 milliards d'avis.
- 50 milliards d'entrees de bibliotheque.
- Statistiques live mises a jour toutes les minutes.
- Filtres combines: plateforme, genre, studio, note, annee, pays, popularite.

## Pourquoi le relationnel devient limitant

Le modele relationnel reste fiable, mais certaines operations deviennent couteuses:

- Beaucoup de jointures entre `games`, `platforms`, `genres`, `reviews`, `library_entries`.
- Agregations frequentes comme les moyennes d'avis ou les tops par plateforme.
- Ecritures massives pour les statistiques live.
- Recherche plein texte avancee sur titres, descriptions, tags et avis.
- Donnees heterogenes selon les plateformes de jeu.

Exemples de requetes problematiques:

- Calculer en direct la note moyenne de millions de jeux.
- Afficher un catalogue filtre avec plusieurs N-N et tri par popularite.
- Recalculer les tops live chaque minute.
- Chercher dans des millions d'avis textuels.

## Alternative NoSQL conceptuelle

Une solution possible serait d'utiliser MongoDB en complement, sans remplacer toute la base relationnelle.

### Donnees qui resteraient en SQL

- Utilisateurs.
- Droits et authentification.
- Jeux canonises.
- Relations fiables et transactionnelles.

### Donnees qui pourraient passer en NoSQL

- Documents de recherche par jeu.
- Snapshots de statistiques live.
- Avis denormalises.
- Evenements d'activite utilisateur.

Exemple de document MongoDB pour la recherche:

```json
{
  "gameId": 12,
  "title": "Starlane Runners",
  "studio": "Nova Pixel Works",
  "platforms": ["PC", "PlayStation 5", "Xbox Series X/S"],
  "genres": ["Action", "Multijoueur"],
  "averageRating": 17.0,
  "livePlayers": 183420,
  "searchText": "course futuriste equipes relais lumineux circuits orbitaux"
}
```

## Conclusion

Le relationnel est excellent pour garantir la coherence des donnees et montrer les relations de la SAE. Pour passer a tres grande echelle, on peut ajouter une base NoSQL specialisee pour la recherche, les statistiques live et les donnees denormalisees.


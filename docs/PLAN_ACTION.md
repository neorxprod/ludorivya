# Plan d'action SAE

## Concept

**Ludorivya** : une médiathèque de jeux vidéo. Catalogue consultable par tous, et pour les joueurs connectés : avis (un par jeu), bibliothèque personnelle (statut + temps de jeu) et contribution au catalogue.

## Objectifs techniques

- Base relationnelle MySQL/MariaDB avec relations 1-1, 1-N et N-N réellement utilisées.
- PHP 8 + PDO : requêtes préparées partout, transactions pour les écritures multi-tables.
- Sécurité : CSRF sur tous les formulaires, mots de passe hachés, sessions durcies, validation serveur systématique.
- CRUD complet : jeux (créer/lire/modifier/supprimer), avis, bibliothèque, profil.
- Interface : Bootstrap 5 + design system personnalisé, JavaScript pour la validation et les interactions.
- Git : commits réguliers et explicites, en français.

## Priorités d'évaluation

1. Modélisation relationnelle solide (25 %).
2. PHP/PDO propre avec requêtes préparées (25 %).
3. Relations SQL visibles dans l'application (20 %).
4. Commits Git réguliers (15 %).
5. README complet (10 %).
6. Bonus NoSQL (+5 %).

## Avant le rendu final

- Vérifier que le dépôt GitHub est à jour et public.
- Tester l'installation complète depuis zéro avec XAMPP (import du schéma, navigation, compte de démo).
- Vérifier que le lien du dépôt est renseigné dans le formulaire de l'enseignant.

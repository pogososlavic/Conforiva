# Reprise locale après réinitialisation du Mac

Le dépôt a été cloné depuis https://github.com/pogososlavic/HOMYA.git dans
`/Applications/XAMPP/xamppfiles/htdocs/HOMYA`, branche `main`.
Les mentions d'installations et de sauvegardes antérieures dans les autres documents
décrivent l'ancien poste et ne prouvent pas leur présence après réinitialisation.

## Démarrer et modifier

Ouvrir le dossier HOMYA dans VS Code. Dans un nouveau terminal intégré :

```sh
php artisan serve --host=127.0.0.1 --port=8000
```

Puis ouvrir http://127.0.0.1:8000/fr. Garder le terminal ouvert ; Ctrl+C arrête le serveur.
Le fichier `demarrer-local.command` lance la même commande depuis le Finder.
Dans VS Code, Terminal → Exécuter la tâche propose aussi le démarrage et la compilation.

Les vues sont dans `resources/views`, les styles et scripts dans `resources/css` et
`resources/js`, les routes dans `routes`. Après modification du CSS/JavaScript :

```sh
npm run build
```

PHP et Composer utilisent Herd Lite dans `~/.config/herd-lite/bin`.
Node et npm utilisent `~/.local/share/homya/node/bin`.
Les réglages propres à cette machine sont dans `.vscode`, ignoré par Git.
XAMPP conserve son PHP d'origine ; le serveur Laravel sert uniquement `public/`.

## Données restaurées et limites

Le dump retrouvé après la reprise initiale a permis de restaurer le catalogue :
3 709 produits métier, 65 catégories et 23 878 traductions. `homya_local` contient
27 tables, dont les tables Laravel et commerce recréées depuis les migrations.
`homya_test` conserve un schéma d'archive vide et `homya_restore_op07` contient
la copie complète du dump récupéré, lots de recette compris.

Le catalogue utilise toujours la source `files`, reconstruite et vérifiée depuis
les données SQL. Les accès SQL locaux sont configurés dans `.env`, avec des comptes
applicatifs dédiés. MariaDB XAMPP utilise son port actuel 3306, sa socket locale
et des paquets de 16 Mio. Les anciennes fiches OP-xx sur 3307 décrivent l'ancien poste.

Les anciennes commandes, les stocks validés, les comptes administrateurs et les
fichiers privés n'étaient pas dans le dump : leurs tables sont recréées vides.
Les 18 454 images CDN et 14 photos constructeur ont ensuite été récupérées :
3 688 produits sur 3 709 disposent d'une photo affichable. Les 20 modèles LG
bloqués par HTTP 403 et Bronpi NICOL restent sans photo utilisable. Les visuels
d'ambiance de l'accueil restent absents. Voir la
[restauration des photos](RESTAURATION-PHOTOS-2026-10-19.md) et sa sauvegarde privée.
Procédure, sauvegardes et vérifications : [restauration des données](RESTAURATION-DONNEES-2026-10-19.md).

Une nouvelle configuration `.env` locale et une nouvelle clé applicative sont préparées
pour cette installation. Paiements, checkout, collecte analytique et indexation restent
désactivés. Les bases et migrations ont été rétablies ensuite dans le cadre de la
restauration demandée. Aucune publication distante n'a été effectuée.

## Vérifications du 19 octobre 2026

- PHP 8.5.0, Node 22.23.3, npm 10.9.9 ; dépendances Composer et npm installées depuis les fichiers de verrouillage.
- `composer check-platform-reqs` : exigences satisfaites.
- `npm run build` : réussi ; audit npm à l'installation : zéro vulnérabilité signalée.
- Tests existants ExampleTest, CatalogueTest et SecurityHeadersTest : 32 tests, 558 assertions réussis ; aucun contrôle de base MariaDB.
- HTTP réel : `/fr`, `/en`, `/de` répondent 200 ; accueil observé dans le navigateur.
- Avant restauration, `/fr/catalogue` répondait 503 en l'absence des archives. Les archives ont depuis été reconstruites et le catalogue local est accessible ; voir les rapports de restauration et les preuves HTTP privées.
- `.env` et `.vscode/settings.json` sont ignorés par Git. Aucun push ni déploiement effectué.
